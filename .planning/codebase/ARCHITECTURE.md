# Architecture

**Analysis Date:** 2025-01-19

## Pattern Overview

**Overall:** Schema-Driven Code Generation with Semantic Data Layer

**Key Characteristics:**
- Schema definitions (JSON/YAML or wiki pages) are the single source of truth
- Wiki pages (Category, Property, Template, Form) are treated as compiled artifacts
- Immutable value objects (models) represent schema entities
- C3 linearization for multiple inheritance resolution
- Hash-based dirty detection for tracking external modifications

## Layers

**Schema Layer:**
- Purpose: Define and validate ontology structure
- Location: `src/Schema/`
- Contains: Immutable models (CategoryModel, PropertyModel, SubobjectModel), inheritance resolution, validation, configuration installation
- Depends on: Nothing (pure domain logic)
- Used by: Store layer, Generator layer, Special page

**Store Layer:**
- Purpose: Persist and retrieve schema entities from MediaWiki pages
- Location: `src/Store/`
- Contains: WikiCategoryStore, WikiPropertyStore, WikiSubobjectStore, PageCreator, StateManager, PageHashComputer
- Depends on: Schema models, MediaWiki APIs, SMW APIs
- Used by: Generators, Special page, API endpoints

**Generator Layer:**
- Purpose: Produce wiki artifacts (templates, forms, displays) from schema models
- Location: `src/Generator/`
- Contains: TemplateGenerator, FormGenerator, DisplayStubGenerator, PropertyInputMapper
- Depends on: Schema models, Store layer (for reading property definitions)
- Used by: Special page, maintenance scripts

**Presentation Layer:**
- Purpose: User interface and external API
- Location: `src/Special/`, `src/Api/`, `src/Parser/`
- Contains: SpecialSemanticSchemas (admin UI), API endpoints, parser functions
- Depends on: All other layers
- Used by: End users, external integrations

**Service Layer:**
- Purpose: Business logic coordination
- Location: `src/Service/`
- Contains: CategoryHierarchyService
- Depends on: Schema models, Store layer
- Used by: API endpoints

## Data Flow

**Schema Import (from JSON/YAML file):**

1. SchemaLoader parses JSON/YAML to array
2. SchemaValidator validates structure and constraints
3. ExtensionConfigInstaller creates model instances
4. Wiki*Store classes write to wiki pages via PageCreator
5. SMW processes semantic annotations asynchronously (job queue)
6. StateManager records page hashes for dirty detection

**Artifact Generation:**

1. WikiCategoryStore reads CategoryModel from wiki
2. InheritanceResolver computes effective category (merged properties)
3. TemplateGenerator produces semantic/dispatcher templates
4. FormGenerator produces PageForms form markup
5. DisplayStubGenerator produces display template (table/sidebox/sections)
6. PageCreator writes all artifacts to wiki

**State Management:**
- StateManager stores state in `MediaWiki:SemanticSchemasState.json`
- PageHashComputer creates SHA256 hashes of model content
- Hashes compared to detect external modifications

## Key Abstractions

**CategoryModel:**
- Purpose: Immutable representation of a category schema
- Examples: `src/Schema/CategoryModel.php`
- Pattern: Value object with mergeWithParent() for inheritance
- Key methods: getAllProperties(), getRequiredProperties(), getDisplaySections(), mergeWithParent()

**PropertyModel:**
- Purpose: Immutable representation of an SMW property
- Examples: `src/Schema/PropertyModel.php`
- Pattern: Value object with datatype normalization
- Key methods: getDatatype(), getAllowedValues(), isPageType(), getRenderTemplate()

**SubobjectModel:**
- Purpose: Immutable representation of a subobject (repeatable data group)
- Examples: `src/Schema/SubobjectModel.php`
- Pattern: Simple value object (no inheritance)
- Key methods: getRequiredProperties(), getOptionalProperties()

**InheritanceResolver:**
- Purpose: Resolve multiple inheritance using C3 linearization
- Examples: `src/Schema/InheritanceResolver.php`
- Pattern: Topological sort with cycle detection
- Key methods: getAncestors(), getEffectiveCategory(), validateInheritance()

**PageCreator:**
- Purpose: Safe wiki page creation/update/deletion
- Examples: `src/Store/PageCreator.php`
- Pattern: Facade over MediaWiki page APIs
- Key methods: createOrUpdatePage(), getPageContent(), updateWithinMarkers()

## Entry Points

**Special:SemanticSchemas:**
- Location: `src/Special/SpecialSemanticSchemas.php`
- Triggers: User visits Special:SemanticSchemas
- Responsibilities: Overview dashboard, validation, generation, hierarchy visualization
- Tabs: overview, validate, generate, hierarchy
- Actions: install-config, generate-form

**API: semanticschemas-hierarchy:**
- Location: `src/Api/ApiSemanticSchemasHierarchy.php`
- Triggers: `api.php?action=semanticschemas-hierarchy&category=Name`
- Responsibilities: Return category inheritance tree and properties for visualization

**API: semanticschemas-install:**
- Location: `src/Api/ApiSemanticSchemasInstall.php`
- Triggers: Layer-by-layer installation from UI
- Responsibilities: Install base configuration in 5 layers (templates, property types, property annotations, subobjects, categories)

**Parser Functions:**
- Location: `src/Parser/DisplayParserFunctions.php`
- Functions: `{{#semanticschemas_hierarchy:Category}}`, `{{#semanticschemas_load_form_preview:}}`
- Responsibilities: Inject hierarchy widget, load form preview JavaScript

**Maintenance Scripts:**
- Location: `maintenance/installConfig.php`, `maintenance/regenerateArtifacts.php`
- Triggers: CLI execution
- Responsibilities: Install base config, regenerate all artifacts

## Error Handling

**Strategy:** Exception-based with logging to MediaWiki warning log

**Patterns:**
- Schema validation errors collected and returned as arrays
- Page creation failures logged via wfLogWarning()
- Rate limiting enforced per user per operation type
- CSRF token validation on all form submissions

## Cross-Cutting Concerns

**Logging:**
- Debug logging via wfDebugLog('semanticschemas', ...)
- Warning logging via wfLogWarning()
- Operation audit trail via ManualLogEntry to semanticschemas log

**Validation:**
- SchemaValidator checks structure and constraints
- CategoryModel/PropertyModel constructors enforce invariants
- InheritanceResolver detects circular dependencies

**Authentication:**
- Special page requires 'editinterface' permission
- API authentication optional via $wgSemanticSchemasRequireApiAuth
- Sysops can bypass rate limits via 'protect' permission

**Caching:**
- Parser output cached by MediaWiki
- Form pages purged after update for PageForms compatibility
- SMW caches cleared during installation for correct property types

---

*Architecture analysis: 2025-01-19*
