# Technology Stack

**Analysis Date:** 2025-01-19

## Languages

**Primary:**
- PHP 8.1+ - All server-side logic, MediaWiki extension

**Secondary:**
- JavaScript (ES6+) - Frontend hierarchy visualization (`resources/ext.semanticschemas.hierarchy.js`)
- CSS - Styling for special pages and UI components
- JSON - Schema definitions, i18n messages, configuration
- YAML - Supported schema format via Symfony parser

## Runtime

**Environment:**
- MediaWiki 1.39.0+ (required)
- PHP 8.1, 8.2, 8.3 (tested via CI)

**Package Manager:**
- Composer 2.x
- Lockfile: present (`composer.lock`)

## Frameworks

**Core:**
- MediaWiki Extension Framework - Full integration via `extension.json` manifest v2
- Semantic MediaWiki (SMW) - Semantic annotations, property storage, SPARQL queries
- PageForms - Form generation and wiki page editing

**Testing:**
- PHPUnit 9.6 - Unit and integration testing
- xdebug - Coverage reporting in CI

**Build/Dev:**
- parallel-lint - PHP syntax validation
- mediawiki-codesniffer 43.0 - MediaWiki coding standards
- minus-x 1.1.3 - File permission checking
- Phan 0.14.0 - Static analysis with MediaWiki config

## Key Dependencies

**Critical (runtime):**
- `symfony/yaml` ^5.0|^6.0 - YAML schema parsing in `src/Schema/SchemaLoader.php`

**Development Only:**
- `mediawiki/mediawiki-codesniffer` ^43.0 - PHP_CodeSniffer MediaWiki rules
- `mediawiki/mediawiki-phan-config` 0.14.0 - Phan configuration for MediaWiki
- `phpunit/phpunit` ^9.6 - Test framework

**MediaWiki Extension Dependencies (required):**
- SemanticMediaWiki `*` - Core SMW functionality
- PageForms `*` - Form generation

## Configuration

**Environment:**
- `$wgSemanticSchemasRequireApiAuth` - Boolean, require `read` permission for hierarchy API (default: false)
- `$wgSemanticSchemasRateLimitPerHour` - Integer, max generate operations per user per hour (default: 20)

**Extension Config:**
- `extension.json` - Extension manifest, hooks, API modules, namespaces, ResourceLoader modules
- `.phpcs.xml` - Code style rules (extends mediawiki-codesniffer with exclusions)
- `phpunit.xml.dist` - Test configuration with `tests/phpunit/bootstrap.php`
- `.phan/config.php` - Static analysis configuration

**Base Configuration File:**
- `resources/extension-config.json` - Defines foundational wiki pages (properties, categories, subobjects, templates)

## Scripts

**Composer Scripts:**
```bash
composer test       # parallel-lint + minus-x check + phpcs
composer run fix    # minus-x fix + phpcbf
composer run phan   # Phan static analysis
```

**Development Commands:**
```bash
php vendor/bin/phpunit                     # Run all unit tests
php vendor/bin/phpunit tests/phpunit/unit  # Run unit tests only
```

**Maintenance Scripts:**
- `maintenance/installConfig.php` - CLI installer for base configuration

## Namespaces

**Custom MediaWiki Namespaces:**
- 3300: `NS_SUBOBJECT` (Subobject) - Content namespace for subobject storage
- 3301: `NS_SUBOBJECT_TALK` (Subobject_talk) - Talk pages

**PHP Namespace:**
- `MediaWiki\Extension\SemanticSchemas\` - PSR-4 autoloaded from `src/`
- `MediaWiki\Extension\SemanticSchemas\Tests\` - Test namespace from `tests/phpunit/`

## Platform Requirements

**Development:**
- Docker Compose for test environment
- PHP 8.1+ with extensions: mbstring, intl
- Composer 2.x
- Access to MariaDB 10.11

**Production:**
- MediaWiki 1.39.0+
- Semantic MediaWiki extension
- PageForms extension
- PHP 8.1+ with Composer autoloader

## CI/CD Pipeline

**GitHub Actions:** `.github/workflows/ci.yml`

**Jobs:**
1. `lint` - PHP syntax + code style (PHP 8.1)
2. `test` - PHPUnit on PHP 8.1, 8.2, 8.3
3. `integration-test` - Full MediaWiki integration with MariaDB service container
4. `static-analysis` - Phan (continue-on-error)

**Test Container:**
- `ghcr.io/labki-org/labki-platform:latest-dev` - Pre-configured MediaWiki with SMW/PageForms

---

*Stack analysis: 2025-01-19*
