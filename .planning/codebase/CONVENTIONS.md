# Coding Conventions

**Analysis Date:** 2026-01-19

## Naming Patterns

**Files:**
- PHP files: PascalCase matching class name (e.g., `CategoryModel.php`, `WikiCategoryStore.php`)
- Test files: `*Test.php` suffix with matching class name (e.g., `CategoryModelTest.php`)
- Bootstrap files: lowercase with hyphens (e.g., `integration-bootstrap.php`)

**Classes:**
- PascalCase: `CategoryModel`, `WikiCategoryStore`, `TemplateGenerator`
- Model classes: `*Model` suffix (`CategoryModel`, `PropertyModel`, `SubobjectModel`)
- Store classes: `Wiki*Store` prefix for wiki persistence (`WikiCategoryStore`, `WikiPropertyStore`)
- Generator classes: `*Generator` suffix (`TemplateGenerator`, `FormGenerator`, `DisplayStubGenerator`)
- Test classes: `*Test` suffix (`CategoryModelTest`, `SchemaValidatorTest`)

**Functions/Methods:**
- camelCase for all methods: `getName()`, `getAllProperties()`, `mergeWithParent()`
- Boolean getters: `is*()` or `has*()` prefix (`isPageType()`, `hasSubobjects()`, `hasAllowedValues()`)
- Boolean setters: `set*()` prefix (`setDirty()`, `setPageHashes()`)
- Factory methods: `create*()` or `new*()` prefix (`createEmptySchema()`)
- Validation methods: `validate*()` prefix (`validateSchema()`, `validateCategory()`)
- Generation methods: `generate*()` prefix (`generateSemanticTemplate()`, `generateDispatcherTemplate()`)

**Variables:**
- camelCase: `$categoryName`, `$propertyStore`, `$templateGenerator`
- Private properties with explicit visibility: `private string $name;`, `private array $parents;`
- Constants: UPPER_SNAKE_CASE as class constants (`MARKER_START`, `DEFAULT_RATE_LIMIT_PER_HOUR`)

**Types:**
- Strong typing with PHP 8.1+ features: typed properties, return types, nullable types
- Use `?Type` for nullable (`?string`, `?PropertyModel`)
- Array typing in PHPDoc: `@var PropertyModel[]`, `@param array<string, PropertyModel[]>`

## Code Style

**Formatting:**
- MediaWiki Code Sniffer (phpcs) with custom ruleset
- Config: `.phpcs.xml`
- Tab indentation (not spaces)
- Opening brace on same line for functions/methods/classes
- Single blank line between method groups

**Linting:**
- PHP Parallel Lint for syntax checking
- Phan for static analysis (`.phan/config.php`)
- MediaWiki code standards with exclusions for:
  - Function documentation (MissingDocumentationProtected/Private/Public)
  - File structure (ClassMatchesFilename, OneClassPerFile)
  - Global naming (ValidGlobalName)

**Excluded Rules (from `.phpcs.xml`):**
```xml
<exclude name="MediaWiki.Commenting.FunctionComment.MissingDocumentationProtected" />
<exclude name="MediaWiki.Commenting.FunctionComment.MissingDocumentationPrivate" />
<exclude name="MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic" />
<exclude name="MediaWiki.WhiteSpace.SpaceBeforeSingleLineComment.NewLineComment" />
<exclude name="MediaWiki.Files.ClassMatchesFilename.NotMatch" />
<exclude name="MediaWiki.Files.OneClassPerFile.MultipleFound" />
```

## Import Organization

**Order:**
1. PHP built-in classes (`InvalidArgumentException`, `RuntimeException`)
2. MediaWiki core classes (`MediaWiki\Title\Title`, `MediaWiki\User\User`)
3. Extension classes (`MediaWiki\Extension\SemanticSchemas\...`)
4. Third-party (rare - only Symfony YAML)

**Path Aliases:**
- PSR-4 autoloading: `MediaWiki\Extension\SemanticSchemas\` -> `src/`
- Test namespace: `MediaWiki\Extension\SemanticSchemas\Tests\` -> `tests/phpunit/`

## Error Handling

**Patterns:**
- Constructor validation: Throw `InvalidArgumentException` for invalid input
- Runtime errors: Throw `RuntimeException` for operational failures
- Validation methods: Return error arrays instead of throwing
- Operation methods: Return `bool` success with separate error retrieval (`getLastError()`)

**Example - Constructor Validation:**
```php
public function __construct( string $name, array $data = [] ) {
    $name = trim( $name );
    if ( $name === '' ) {
        throw new InvalidArgumentException( "Category name cannot be empty." );
    }
    if ( preg_match( '/[<>{}|#]/', $name ) ) {
        throw new InvalidArgumentException( "Category '{$name}' contains invalid characters." );
    }
    // ...
}
```

**Example - Operation with Error Tracking:**
```php
public function createOrUpdatePage( Title $title, string $content, string $summary ): bool {
    $this->lastError = null;
    try {
        // operation...
        return true;
    } catch ( \Exception $e ) {
        $this->lastError = $e->getMessage();
        wfLogWarning( "SemanticSchemas: Exception: " . $e->getMessage() );
        return false;
    }
}

public function getLastError(): ?string {
    return $this->lastError;
}
```

## Logging

**Framework:** MediaWiki logging functions

**Patterns:**
- Warnings: `wfLogWarning()` for recoverable errors
- Debug: `wfDebugLog( 'semanticschemas', ... )` for tracing
- Audit: `ManualLogEntry` for user-visible operations

**When to Log:**
- Failed operations (write, delete, update failures)
- Exception handling paths
- Administrative actions (import, export, generate)

## Comments

**When to Comment:**
- Class-level PHPDoc with purpose, responsibilities, patterns used
- Section headers with `/* ===== SECTION NAME ===== */` style
- Complex algorithm explanations
- Non-obvious business logic rationale

**Section Header Pattern:**
```php
/* =========================================================================
 * CONSTRUCTOR VALIDATION
 * ========================================================================= */

/* -------------------------------------------------------------------------
 * ACCESSORS (read-only)
 * ------------------------------------------------------------------------- */
```

**PHPDoc:**
- Required for public methods with complex signatures
- Optional for simple getters/setters
- Array types documented: `@var PropertyModel[]`, `@param array<string,mixed>`
- Return types documented when complex: `@return array{success: bool, errors: string[]}`

## Function Design

**Size:**
- Methods generally 20-50 lines
- Break into private helpers for complex logic
- Special page `execute()` method is dispatcher only

**Parameters:**
- Nullable parameters with defaults: `?PageCreator $pageCreator = null`
- Use constructor injection for dependencies
- Fallback to creating default instances: `$this->pageCreator = $pageCreator ?? new PageCreator();`

**Return Values:**
- Accessors return typed values: `getName(): string`, `getParents(): array`
- Boolean operations return `bool`
- Factory methods return new instances
- Validation returns arrays: `['errors' => [...], 'warnings' => [...]]`

## Module Design

**Exports:**
- One primary class per file (models, generators, stores)
- Utility classes can have multiple static methods (`NamingHelper`)

**Barrel Files:**
- Not used - direct class imports throughout

**Immutability Pattern:**
- Model classes (`CategoryModel`, `PropertyModel`) are immutable after construction
- Use `mergeWithParent()` to create new instances with merged data
- Store state in private properties with read-only accessors

**Dependency Injection:**
- Constructor injection with nullable parameters
- Fallback to default implementations
- Example from `TemplateGenerator`:
```php
public function __construct(
    ?PageCreator $pageCreator = null,
    ?WikiSubobjectStore $subobjectStore = null,
    ?WikiPropertyStore $propertyStore = null
) {
    $this->pageCreator = $pageCreator ?? new PageCreator();
    $this->subobjectStore = $subobjectStore ?? new WikiSubobjectStore();
    $this->propertyStore = $propertyStore ?? new WikiPropertyStore();
}
```

## MediaWiki-Specific Patterns

**Namespace Constants:**
- Use MediaWiki constants: `NS_CATEGORY`, `NS_TEMPLATE`, `NS_MEDIAWIKI`
- Custom namespace defined: `NS_SUBOBJECT` (3300)

**Title Handling:**
- Always use `Title::makeTitleSafe()` for user input
- Check `$title->exists()` before operations
- Use `getPrefixedText()` for display, `getText()` for name only

**Page Operations:**
- Use `WikiPageFactory` for page access
- Use `PageUpdater` for saves (MW 1.36+)
- Always provide edit summaries
- Handle "no change" as success, not error

**Special Pages:**
- Extend `SpecialPage` base class
- Implement permission checks: `$this->checkPermissions()`
- Use CSRF token validation: `matchEditToken()`

---

*Convention analysis: 2026-01-19*
