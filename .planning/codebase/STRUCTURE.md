# Codebase Structure

**Analysis Date:** 2025-01-19

## Directory Layout

```
SemanticSchemas/
├── src/                    # Main PHP source code
│   ├── Schema/             # Domain models and schema operations
│   ├── Store/              # Wiki persistence layer
│   ├── Generator/          # Template/form generation
│   ├── Special/            # Special page (admin UI)
│   ├── Api/                # API endpoints
│   ├── Parser/             # Parser functions
│   ├── Service/            # Business logic services
│   ├── Hooks/              # MediaWiki hook handlers
│   └── Util/               # Utility helpers
├── resources/              # Frontend assets and config
├── maintenance/            # CLI scripts
├── tests/                  # Test suites
│   ├── phpunit/            # PHPUnit tests
│   │   ├── unit/           # Unit tests
│   │   └── integration/    # Integration tests
│   ├── fixtures/           # Test data files
│   └── scripts/            # Test environment scripts
├── docs/                   # Documentation
├── i18n/                   # Internationalization messages
├── .github/                # GitHub workflows
└── .planning/              # GSD planning documents
```

## Directory Purposes

**src/Schema/:**
- Purpose: Core domain models and schema operations
- Contains: Immutable model classes, validators, loaders, inheritance resolver
- Key files:
  - `CategoryModel.php`: Category schema representation
  - `PropertyModel.php`: SMW property schema representation
  - `SubobjectModel.php`: Subobject schema representation
  - `InheritanceResolver.php`: C3 linearization for multiple inheritance
  - `SchemaLoader.php`: JSON/YAML parsing
  - `SchemaValidator.php`: Schema structure validation
  - `ExtensionConfigInstaller.php`: Base configuration installer
  - `OntologyInspector.php`: Statistics and validation helper

**src/Store/:**
- Purpose: Read/write schema entities to wiki pages
- Contains: Store classes for each entity type, page utilities
- Key files:
  - `WikiCategoryStore.php`: Category page persistence
  - `WikiPropertyStore.php`: Property page persistence
  - `WikiSubobjectStore.php`: Subobject page persistence
  - `PageCreator.php`: Safe wiki page CRUD operations
  - `StateManager.php`: Dirty state and hash tracking
  - `PageHashComputer.php`: SHA256 hash generation for models
  - `WikiFormatStore.php`: Format/template format persistence

**src/Generator/:**
- Purpose: Produce wiki artifacts from schema models
- Contains: Generators for templates, forms, displays
- Key files:
  - `TemplateGenerator.php`: Semantic and dispatcher templates
  - `FormGenerator.php`: PageForms form markup
  - `DisplayStubGenerator.php`: Display templates (table/sidebox/sections)
  - `PropertyInputMapper.php`: Map property types to form inputs

**src/Special/:**
- Purpose: Admin UI for schema management
- Contains: Main special page
- Key files:
  - `SpecialSemanticSchemas.php`: ~1,964 lines, 4 tabs (overview, validate, generate, hierarchy)

**src/Api/:**
- Purpose: External API endpoints
- Contains: MediaWiki API modules
- Key files:
  - `ApiSemanticSchemasHierarchy.php`: Category hierarchy data API
  - `ApiSemanticSchemasInstall.php`: Layer-by-layer installation API

**src/Parser/:**
- Purpose: Custom parser functions
- Contains: Parser function registration and handlers
- Key files:
  - `DisplayParserFunctions.php`: `#semanticschemas_hierarchy`, `#semanticschemas_load_form_preview`

**src/Service/:**
- Purpose: Business logic coordination
- Contains: Service classes
- Key files:
  - `CategoryHierarchyService.php`: Hierarchy computation for API/UI

**src/Hooks/:**
- Purpose: MediaWiki hook handlers
- Contains: Extension initialization and UI hooks
- Key files:
  - `SemanticSchemasSetupHooks.php`: Extension setup, namespace registration
  - `CategoryPageHooks.php`: Category page navigation links

**src/Util/:**
- Purpose: Shared utility functions
- Contains: Helper classes
- Key files:
  - `NamingHelper.php`: Property to parameter name conversion, label generation

## Key File Locations

**Entry Points:**
- `extension.json`: Extension registration and configuration
- `src/Special/SpecialSemanticSchemas.php`: Main admin interface
- `maintenance/installConfig.php`: CLI base config installer
- `maintenance/regenerateArtifacts.php`: CLI artifact regeneration

**Configuration:**
- `extension.json`: Hooks, namespaces, API modules, config options
- `resources/extension-config.json`: Base schema (properties, categories, templates)
- `.phpcs.xml`: PHP CodeSniffer configuration
- `.phan/config.php`: Phan static analysis configuration
- `composer.json`: Dependencies and scripts

**Core Logic:**
- `src/Schema/CategoryModel.php`: Category value object with mergeWithParent()
- `src/Schema/InheritanceResolver.php`: C3 linearization algorithm
- `src/Store/PageCreator.php`: All wiki write operations
- `src/Generator/TemplateGenerator.php`: Three-template system generation

**Testing:**
- `tests/phpunit/unit/`: Unit tests mirroring src/ structure
- `tests/phpunit/integration/`: Integration tests with MediaWiki
- `tests/fixtures/`: JSON schema fixtures
- `tests/scripts/`: Docker environment scripts

## Naming Conventions

**Files:**
- PHP classes: PascalCase matching class name (e.g., `CategoryModel.php`)
- Maintenance scripts: camelCase (e.g., `installConfig.php`)
- Resources: kebab-case with ext prefix (e.g., `ext.semanticschemas.hierarchy.js`)

**Directories:**
- Lowercase for top-level (e.g., `src/`, `resources/`)
- PascalCase for src subdirectories matching namespace (e.g., `Schema/`, `Store/`)

**Classes:**
- Models: `*Model` suffix (e.g., `CategoryModel`, `PropertyModel`)
- Stores: `Wiki*Store` pattern (e.g., `WikiCategoryStore`)
- Generators: `*Generator` suffix (e.g., `TemplateGenerator`)
- API modules: `ApiSemanticSchemas*` prefix

**Methods:**
- camelCase (e.g., `getAllProperties()`, `createOrUpdatePage()`)
- Getters: `get*()` or `is*()` for booleans
- Writers: `write*()` for persistence

## Where to Add New Code

**New Feature (new entity type):**
- Model: `src/Schema/NewEntityModel.php`
- Store: `src/Store/WikiNewEntityStore.php`
- Generator (if needed): `src/Generator/NewEntityGenerator.php`
- Tests: `tests/phpunit/unit/Schema/NewEntityModelTest.php`, `tests/phpunit/unit/Store/WikiNewEntityStoreTest.php`

**New Component/Module:**
- Business logic: `src/Service/NewService.php`
- API endpoint: `src/Api/ApiSemanticSchemasNewFeature.php` (register in extension.json)
- Parser function: Add to `src/Parser/DisplayParserFunctions.php` or new file

**Utilities:**
- Shared helpers: `src/Util/NewHelper.php`

**New Special Page Tab:**
- Add case in `SpecialSemanticSchemas::execute()`
- Add tab config in `showNavigation()`
- Add show method `showNewTab()`

**Frontend Assets:**
- CSS: `resources/ext.semanticschemas.*.css`
- JavaScript: `resources/ext.semanticschemas.*.js`
- Register in `extension.json` under ResourceModules

## Special Directories

**resources/:**
- Purpose: Frontend CSS/JS and base configuration JSON
- Generated: No
- Committed: Yes
- Note: `extension-config.json` defines base schema installed via Special page

**.planning/:**
- Purpose: GSD planning and codebase analysis documents
- Generated: Partially (by Claude)
- Committed: Project-dependent

**vendor/:**
- Purpose: Composer dependencies
- Generated: Yes (via composer install)
- Committed: No

**tests/phpunit/:**
- Purpose: PHPUnit test suites
- Test structure mirrors `src/` directory structure
- Unit tests: `tests/phpunit/unit/`
- Integration tests: `tests/phpunit/integration/`

## Custom Namespace

**Subobject (3300/3301):**
- Purpose: Store subobject entity definitions with semantic annotations
- Defined in: `extension.json` namespaces array
- Constants: `NS_SUBOBJECT`, `NS_SUBOBJECT_TALK`
- Content: Semantic enabled for annotations

---

*Structure analysis: 2025-01-19*
