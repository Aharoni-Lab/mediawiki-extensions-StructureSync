#!/usr/bin/env bash

set -euo pipefail

#
# StructureSync — MediaWiki test environment setup script (SQLite)
#

get_cache_dir() {
    case "$(uname -s)" in
        Darwin*) echo "$HOME/Library/Caches/structuresync" ;;
        MINGW*|MSYS*|CYGWIN*)
            local appdata="${LOCALAPPDATA:-$HOME/AppData/Local}"
            echo "$appdata/structuresync"
            ;;
        *) echo "${XDG_CACHE_HOME:-$HOME/.cache}/structuresync" ;;
    esac
}

# ---------------- CONFIG ----------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CACHE_BASE="$(get_cache_dir)"
MW_DIR="${MW_DIR:-$CACHE_BASE/mediawiki-StructureSync-test}"
EXT_DIR="${EXT_DIR:-$SCRIPT_DIR/..}"
MW_BRANCH=REL1_39
MW_PORT=8889
MW_ADMIN_USER=Admin
MW_ADMIN_PASS=dockerpass

CONTAINER_WIKI="/var/www/html/w"
CONTAINER_LOG_DIR="/var/log/structuresync"
CONTAINER_LOG_FILE="$CONTAINER_LOG_DIR/structuresync.log"
LOG_DIR="$EXT_DIR/logs"

echo "==> Using MW directory: $MW_DIR"

# ---------------- RESET ENV ----------------

if [ -d "$MW_DIR" ]; then
    cd "$MW_DIR"
    docker compose down -v || true
fi

echo "==> Ensuring MediaWiki core exists..."
if [ ! -d "$MW_DIR/.git" ]; then
    mkdir -p "$(dirname "$MW_DIR")"
    git clone https://gerrit.wikimedia.org/r/mediawiki/core.git "$MW_DIR"
fi

cd "$MW_DIR"

git fetch --all
git checkout "$MW_BRANCH"
git reset --hard "$MW_BRANCH"
git clean -fdx
git submodule update --init --recursive || true

# ---------------- DOCKER ENV ----------------

cat > "$MW_DIR/.env" <<EOF
MW_SCRIPT_PATH=/w
MW_SERVER=http://localhost:$MW_PORT
MW_DOCKER_PORT=$MW_PORT
MEDIAWIKI_USER=$MW_ADMIN_USER
MEDIAWIKI_PASSWORD=$MW_ADMIN_PASS
MW_DOCKER_UID=$(id -u)
MW_DOCKER_GID=$(id -g)
EOF

echo "==> Starting MW containers..."
docker compose up -d

echo "==> Installing composer deps (core only)..."
docker compose exec -T mediawiki composer update --no-interaction --no-progress

echo "==> Running MediaWiki install script..."
# IMPORTANT: LocalSettings.php must *not* reference extensions yet
docker compose exec -T mediawiki bash -lc "rm -f $CONTAINER_WIKI/LocalSettings.php"
docker compose exec -T mediawiki /bin/bash /docker/install.sh

echo "==> Fixing SQLite permissions..."
docker compose exec -T mediawiki bash -lc "chmod -R o+rwx $CONTAINER_WIKI/cache/sqlite"

# ---------------- EXTENSION & LOG MOUNTS ----------------

echo "==> Preparing host log directory..."
mkdir -p "$LOG_DIR"
chmod 777 "$LOG_DIR"

echo "==> Writing override file..."
cat > "$MW_DIR/docker-compose.override.yml" <<EOF
services:
  mediawiki:
    user: "$(id -u):$(id -g)"
    volumes:
      - $EXT_DIR:/var/www/html/w/extensions/StructureSync:cached
      - $LOG_DIR:$CONTAINER_LOG_DIR
EOF

echo "==> Restarting with extension mount..."
docker compose down
docker compose up -d

# ---------------- INSTALL SEMANTIC MEDIAWIKI ----------------

echo "==> Installing SMW via composer..."
docker compose exec -T mediawiki bash -lc "
  cd $CONTAINER_WIKI
  composer require mediawiki/semantic-media-wiki:'~4.0' --no-progress
"

echo "==> Enabling SMW..."
docker compose exec -T mediawiki bash -lc "
  sed -i '/SemanticMediaWiki/d' $CONTAINER_WIKI/LocalSettings.php
  {
    echo ''
    echo '// === Semantic MediaWiki ==='
    echo 'wfLoadExtension( \"SemanticMediaWiki\" );'
    echo 'enableSemantics( \"localhost\" );'
  } >> $CONTAINER_WIKI/LocalSettings.php
"

echo "==> Running MW updater..."
docker compose exec -T mediawiki php maintenance/update.php --quick

echo "==> Initializing SMW store..."
docker compose exec -T mediawiki php extensions/SemanticMediaWiki/maintenance/setupStore.php --nochecks

# ---------------- INSTALL PAGE FORMS ----------------

echo "==> Installing PageForms via composer..."
docker compose exec -T mediawiki bash -lc "
  cd $CONTAINER_WIKI
  composer require mediawiki/page-forms:'~5.7' --no-progress
"

echo "==> Enabling PageForms..."
docker compose exec -T mediawiki bash -lc "
  sed -i '/PageForms/d' $CONTAINER_WIKI/LocalSettings.php
  {
    echo ''
    echo '// === PageForms ==='
    echo 'wfLoadExtension( \"PageForms\" );'
  } >> $CONTAINER_WIKI/LocalSettings.php
"

echo "==> Running MW updater for PageForms..."
docker compose exec -T mediawiki php maintenance/update.php --quick

# ---------------- STRUCTURE SYNC ----------------

echo "==> Installing StructureSync dependencies..."
docker compose exec -T mediawiki bash -lc "
  cd $CONTAINER_WIKI/extensions/StructureSync
  composer install --no-dev --no-progress
"

echo "==> Installing StructureSync settings..."
docker compose exec -T mediawiki bash -lc "
  sed -i '/StructureSync/d' $CONTAINER_WIKI/LocalSettings.php

  {
    echo ''
    echo '// === StructureSync ==='
    echo 'wfLoadExtension( \"StructureSync\" );'
    echo '\$wgDebugLogGroups[\"structuresync\"] = \"$CONTAINER_LOG_FILE\";'
  } >> $CONTAINER_WIKI/LocalSettings.php
"

echo "==> Running MW updater for StructureSync schema..."
docker compose exec -T mediawiki php maintenance/update.php --quick

# ---------------- CACHE DIRECTORY ----------------

echo "==> Setting cache directory..."
docker compose exec -T mediawiki bash -lc "
  sed -i '/wgCacheDirectory/d' $CONTAINER_WIKI/LocalSettings.php
  sed -i '/\\$IP = __DIR__/a \$wgCacheDirectory = \"\$IP/cache-structuresync\";' $CONTAINER_WIKI/LocalSettings.php
"

# ---------------- REBUILD L10N ----------------

echo "==> Rebuilding LocalisationCache..."
docker compose exec -T mediawiki php maintenance/rebuildLocalisationCache.php --force

# ---------------- CREATE SCHEMA PROPERTIES ----------------

echo "==> Creating StructureSync schema properties..."
docker compose exec -T mediawiki bash -lc "
  php $CONTAINER_WIKI/maintenance/edit.php -b 'Property:Has_parent_category' <<'PROPEOF'
This property links to parent categories for inheritance.
[[Has type::Page]]
[[Category:StructureSync Properties]]
PROPEOF

  php $CONTAINER_WIKI/maintenance/edit.php -b 'Property:Has_required_property' <<'PROPEOF'
This property links to required properties for a category.
[[Has type::Page]]
[[Category:StructureSync Properties]]
PROPEOF

  php $CONTAINER_WIKI/maintenance/edit.php -b 'Property:Has_optional_property' <<'PROPEOF'
This property links to optional properties for a category.
[[Has type::Page]]
[[Category:StructureSync Properties]]
PROPEOF

  php $CONTAINER_WIKI/maintenance/edit.php -b 'Property:Has_display_section_name' <<'PROPEOF'
This property stores the name of a display section.
[[Has type::Text]]
[[Category:StructureSync Properties]]
PROPEOF

  php $CONTAINER_WIKI/maintenance/edit.php -b 'Property:Has_display_section_property' <<'PROPEOF'
This property links to properties in a display section.
[[Has type::Page]]
[[Category:StructureSync Properties]]
PROPEOF
"

# ---------------- TEST ----------------

echo "==> Testing StructureSync logging..."
docker compose exec -T mediawiki php -r "
define('MW_INSTALL_PATH','/var/www/html/w');
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once MW_INSTALL_PATH . '/includes/WebStart.php';
wfDebugLog('structuresync', 'StructureSync test at '.date('H:i:s'));
echo \"OK\n\";
"

docker compose exec -T mediawiki tail -n 5 "$CONTAINER_LOG_FILE" || echo "Log file not created yet"

# ---------------- POPULATE TEST DATA (OPTIONAL) ----------------

if [ "${POPULATE_TEST_DATA:-}" = "1" ]; then
    echo ""
    echo "==> Populating test data..."
    "$SCRIPT_DIR/populate_test_data.sh" || echo "  Warning: Failed to populate test data (this is optional)"
fi

echo ""
echo "========================================"
echo "DONE - StructureSync test environment ready!"
echo "========================================"
echo ""
echo "Visit: http://localhost:$MW_PORT/w"
echo "Admin: $MW_ADMIN_USER / $MW_ADMIN_PASS"
echo "Logs at: $LOG_DIR"
echo "Special page: http://localhost:$MW_PORT/w/index.php/Special:StructureSync"
echo ""
echo "Quick commands:"
echo "  - Export schema: docker compose exec mediawiki php extensions/StructureSync/maintenance/exportOntology.php"
echo "  - Validate: docker compose exec mediawiki php extensions/StructureSync/maintenance/validateOntology.php"
echo ""
if [ "${POPULATE_TEST_DATA:-}" != "1" ]; then
    echo "To populate test data, run:"
    echo "  POPULATE_TEST_DATA=1 $SCRIPT_DIR/setup_mw_test_env.sh"
    echo "  or"
    echo "  $SCRIPT_DIR/populate_test_data.sh"
fi

