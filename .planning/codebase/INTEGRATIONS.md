# External Integrations

**Analysis Date:** 2025-01-19

## APIs & External Services

**MediaWiki API:**
- Extension registers API modules via `extension.json` → `APIModules`
- `api.php?action=semanticschemas-hierarchy` - Returns category hierarchy data
  - Handler: `src/Api/ApiSemanticSchemasHierarchy.php`
  - Read-only, optionally authenticated via `$wgSemanticSchemasRequireApiAuth`
- `api.php?action=semanticschemas-install` - Layer-by-layer base config installation
  - Handler: `src/Api/ApiSemanticSchemasInstall.php`
  - Requires CSRF token, write permission

**Semantic MediaWiki (SMW):**
- Direct SMW store access via `\SMW\StoreFactory::getStore()` in `src/Store/WikiCategoryStore.php`
- SMW DIProperty/DIWikiPage for semantic data extraction
- SMW namespace 102 for properties
- `{{#set:...}}` for semantic annotations in generated templates
- `{{#subobject:...}}` for display sections and nested data
- Depends on SMW job queue for property type registration

**PageForms:**
- Form namespace (PF_NS_FORM) for generated forms
- `{{{for template}}}` / `{{{end template}}}` syntax in form generation
- `{{#formlink:...}}` for create/edit links
- Config: `$wgPageFormsLinkAllRedLinksToForms`

## Data Storage

**Databases:**
- MariaDB 10.11 (via MediaWiki)
  - Connection: Standard MediaWiki `$wgDBserver`, `$wgDBname`, etc.
  - Client: MediaWiki's `DBLoadBalancer` → `getConnection(DB_REPLICA)`
  - Used in: `src/Store/WikiCategoryStore.php::getAllCategories()`

**Wiki Pages as Storage:**
- Category pages store schema metadata via SMW annotations
- Property pages store datatype and constraint definitions
- Template pages store generated form/display logic
- Pages tracked with `[[Category:SemanticSchemas-managed]]`

**State Management:**
- `src/Store/StateManager.php` - Tracks generation state, hashes, dirty flags
- Hash-based dirty detection via SHA256 in `src/Store/PageHashComputer.php`
- Stored in MediaWiki page content (marker-delimited blocks)

**File Storage:**
- Local filesystem only
- `resources/extension-config.json` - Base configuration definitions
- Schema files can be loaded from filesystem via `SchemaLoader::loadFromFile()`

**Caching:**
- MediaWiki ObjectCache for rate limiting
  - Key pattern: `semanticschemas-ratelimit-{userId}-{operation}`
  - TTL: 3600 seconds (1 hour)
- `$wgCacheDirectory` for general MediaWiki caching (test config: `$IP/cache-semanticschemas`)

## Authentication & Identity

**Auth Provider:**
- MediaWiki built-in
  - Special page requires `editinterface` permission
  - Rate limit bypass via `semanticschemas-bypass-ratelimit` or `protect` permission
  - CSRF token validation via `matchEditToken()` on all POST actions

**Permission Checks:**
- `SpecialPage::checkPermissions()` for page access
- `$user->isAllowed('semanticschemas-bypass-ratelimit')` for rate limit exemption
- Optional API auth via `$wgSemanticSchemasRequireApiAuth` configuration

## Monitoring & Observability

**Logging:**
- MediaWiki log system (`semanticschemas` log type)
- Actions logged: `semanticschemas/generate`, install operations
- Log handlers defined in `extension.json` → `LogActionsHandlers`
- Debug logging: `$wgDebugLogGroups['semanticschemas']`
- `wfLogWarning()` for runtime warnings

**Error Tracking:**
- None (relies on MediaWiki error handling)
- `$wgShowExceptionDetails` for development

## CI/CD & Deployment

**Hosting:**
- Designed for any MediaWiki 1.39+ installation
- Docker Compose provided for local development (`docker-compose.yml`)
- CI uses `ghcr.io/labki-org/labki-platform:latest-dev` container

**CI Pipeline:**
- GitHub Actions (`.github/workflows/ci.yml`)
- Triggers: push/PR to main, develop branches
- Integration tests use MariaDB service container

**Deployment:**
- Standard MediaWiki extension installation
- Composer autoloader OR MediaWiki's `wfLoadExtension()`
- Post-install: Run `Special:SemanticSchemas` → "Install Configuration" or `maintenance/installConfig.php`

## Webhooks & Callbacks

**Incoming:**
- None

**Outgoing:**
- None

## MediaWiki Hooks

**Registered Hooks (extension.json):**
- `SetupAfterCache` - Extension initialization (`SemanticSchemasSetupHooks::onSetupAfterCache`)
- `LoadExtensionSchemaUpdates` - Database schema updates (`SemanticSchemasSetupHooks::onLoadExtensionSchemaUpdates`)
- `ParserFirstCallInit` - Register parser functions (`DisplayParserFunctions::onParserFirstCallInit`)
- `SkinTemplateNavigation::Universal` - Add "Create form" link to category pages (`CategoryPageHooks::onSkinTemplateNavigation`)

**Parser Functions:**
- `{{#SemanticSchemasRenderAllProperties:Category}}` - Render all properties for display
- `{{#SemanticSchemasRenderSection:Category|Section}}` - Render specific display section

## Environment Configuration

**Required env vars (Docker):**
- `MW_DB_HOST` - Database hostname
- `MW_DB_NAME` - Database name
- `MW_DB_USER` - Database username
- `MW_DB_PASSWORD` - Database password
- `MW_ADMIN_USER` - Wiki admin username
- `MW_ADMIN_PASS` - Wiki admin password
- `MW_DISABLE_PLATFORM_EXTENSIONS` - Disable bundled extensions (for testing)

**Wiki Configuration:**
- `$wgSemanticSchemasRequireApiAuth` - Require auth for API (default: false)
- `$wgSemanticSchemasRateLimitPerHour` - Rate limit (default: 20)
- SMW: `$smwgChangePropagationProtection`, `$smwgEnabledDeferredUpdate`, `$smwgAutoSetupStore`
- PageForms: `$wgPageFormsAllowCreateInRestrictedNamespaces`, `$wgPageFormsLinkAllRedLinksToForms`

**Secrets location:**
- Standard MediaWiki LocalSettings.php
- No extension-specific secrets

## Resource Loader Modules

**Frontend Assets (extension.json → ResourceModules):**
- `ext.semanticschemas.styles` - Base CSS styles
- `ext.semanticschemas.hierarchy` - Hierarchy visualization JS/CSS
  - Dependencies: `mediawiki.api`, `mediawiki.util`, `jquery`
- `ext.semanticschemas.hierarchy.formpreview` - Form preview JS/CSS

---

*Integration audit: 2025-01-19*
