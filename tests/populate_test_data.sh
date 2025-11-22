#!/usr/bin/env bash

set -euo pipefail

#
# StructureSync — Populate test data script
#

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

CACHE_BASE="$(get_cache_dir)"
MW_DIR="${MW_DIR:-$CACHE_BASE/mediawiki-StructureSync-test}"

if [ ! -d "$MW_DIR" ]; then
    echo "ERROR: MediaWiki directory not found at: $MW_DIR"
    echo "Run setup_mw_test_env.sh first"
    exit 1
fi

cd "$MW_DIR"

echo "==> Creating test properties..."

# Basic properties
docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Property:Has_full_name' <<'EOF'
The full name of a person.
[[Has type::Text]]
[[Category:Properties]]
EOF
"

docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Property:Has_email' <<'EOF'
Email address.
[[Has type::Email]]
[[Category:Properties]]
EOF
"

docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Property:Has_phone' <<'EOF'
Phone number.
[[Has type::Telephone number]]
[[Category:Properties]]
EOF
"

docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Property:Has_biography' <<'EOF'
Biography text.
[[Has type::Text]]
[[Category:Properties]]
EOF
"

docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Property:Has_advisor' <<'EOF'
Academic advisor.
[[Has type::Page]]
[[Category:Properties]]
EOF
"

docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Property:Has_cohort_year' <<'EOF'
Year of cohort.
[[Has type::Number]]
[[Category:Properties]]
EOF
"

docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Property:Has_lab_role' <<'EOF'
Role in the lab.
[[Has type::Text]]
[[Allows value::PI]]
[[Allows value::Postdoc]]
[[Allows value::Graduate Student]]
[[Allows value::Undergraduate]]
[[Category:Properties]]
EOF
"

echo "==> Creating test categories with schema..."

# Base Person category
docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Category:Person' <<'EOF'
A person in our organization.

<!-- StructureSync Schema Start -->
=== Required Properties ===
[[Has required property::Property:Has full name]]
[[Has required property::Property:Has email]]

=== Optional Properties ===
[[Has optional property::Property:Has phone]]
[[Has optional property::Property:Has biography]]

{{#subobject:display_section_0
|Has display section name=Contact Information
|Has display section property=Property:Has email
|Has display section property=Property:Has phone
}}

{{#subobject:display_section_1
|Has display section name=Biography
|Has display section property=Property:Has biography
}}
<!-- StructureSync Schema End -->
EOF
"

# LabMember category
docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Category:LabMember' <<'EOF'
A member of the lab.

<!-- StructureSync Schema Start -->
=== Required Properties ===
[[Has required property::Property:Has lab role]]

=== Optional Properties ===
[[Has optional property::Property:Has biography]]
<!-- StructureSync Schema End -->
EOF
"

# GraduateStudent category (multiple inheritance example)
docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Category:GraduateStudent' <<'EOF'
A graduate student in the lab.

<!-- StructureSync Schema Start -->
[[Has parent category::Category:Person]]
[[Has parent category::Category:LabMember]]

=== Required Properties ===
[[Has required property::Property:Has advisor]]

=== Optional Properties ===
[[Has optional property::Property:Has cohort year]]
<!-- StructureSync Schema End -->

[[Category:Person]]
[[Category:LabMember]]
EOF
"

echo "==> Generating templates and forms..."
docker compose exec -T mediawiki php extensions/StructureSync/maintenance/regenerateArtifacts.php --generate-display

echo "==> Creating example pages..."

# Example person
docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'John_Doe' <<'EOF'
{{Person
|full_name=John Doe
|email=john.doe@example.edu
|phone=555-0100
|biography=John is a researcher with expertise in computational biology.
}}

[[Category:Person]]
EOF
"

# Example graduate student
docker compose exec -T mediawiki bash -c "php maintenance/edit.php -b 'Jane_Smith' <<'EOF'
{{GraduateStudent
|full_name=Jane Smith
|email=jane.smith@example.edu
|phone=555-0101
|advisor=John Doe
|cohort_year=2023
|lab_role=Graduate Student
}}

[[Category:GraduateStudent]]
EOF
"

echo "==> Exporting test schema..."
docker compose exec -T mediawiki php extensions/StructureSync/maintenance/exportOntology.php \
    --format=json \
    --output=/var/www/html/w/extensions/StructureSync/tests/test-schema.json

echo ""
echo "========================================"
echo "Test data populated successfully!"
echo "========================================"
echo ""
echo "Created:"
echo "  - 7 Properties (Has full name, Has email, etc.)"
echo "  - 3 Categories (Person, LabMember, GraduateStudent)"
echo "  - Templates and Forms for all categories"
echo "  - 2 Example pages (John Doe, Jane Smith)"
echo "  - Exported schema to tests/test-schema.json"
echo ""
echo "Try these:"
echo "  - Visit Special:StructureSync to see the overview"
echo "  - View John_Doe page to see template rendering"
echo "  - Use Form:GraduateStudent to create new graduate students"
echo "  - Export schema via Special:StructureSync/export"

