# Testing Patterns

**Analysis Date:** 2026-01-19

## Test Framework

**Runner:**
- PHPUnit 9.6
- Config: `phpunit.xml.dist`

**Assertion Library:**
- PHPUnit built-in assertions

**Run Commands:**
```bash
php vendor/bin/phpunit                           # Run all unit tests
php vendor/bin/phpunit tests/phpunit/unit        # Run unit tests only
php vendor/bin/phpunit tests/phpunit/YourTest.php  # Run specific test file
composer test                                     # Full test suite (lint + phpcs + phpunit)
```

## Test File Organization

**Location:**
- Unit tests: `tests/phpunit/unit/` (mirrors src/ structure)
- Integration tests: `tests/phpunit/integration/`

**Naming:**
- Test files: `*Test.php` suffix
- Test class name matches file name: `CategoryModelTest.php` -> `class CategoryModelTest`

**Structure:**
```
tests/
├── phpunit/
│   ├── bootstrap.php                    # Unit test bootstrap (standalone)
│   ├── integration-bootstrap.php        # Integration test bootstrap (MediaWiki)
│   ├── unit/
│   │   ├── Schema/
│   │   │   ├── CategoryModelTest.php
│   │   │   ├── SchemaValidatorTest.php
│   │   │   ├── SchemaLoaderTest.php
│   │   │   └── InheritanceResolverTest.php
│   │   ├── Store/
│   │   │   └── StateManagerTest.php
│   │   └── Generator/
│   │       └── TemplateGeneratorTest.php
│   └── integration/
│       ├── Schema/
│       │   └── ExtensionConfigInstallerTest.php
│       └── Store/
│           ├── WikiCategoryStoreTest.php
│           ├── WikiPropertyStoreTest.php
│           └── PageCreatorTest.php
└── scripts/
    └── reinstall_test_env.sh            # Docker test environment setup
```

## Test Structure

**Suite Organization:**
```php
<?php

namespace MediaWiki\Extension\SemanticSchemas\Tests\Unit\Schema;

use InvalidArgumentException;
use MediaWiki\Extension\SemanticSchemas\Schema\CategoryModel;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\SemanticSchemas\Schema\CategoryModel
 */
class CategoryModelTest extends TestCase {

    /* =========================================================================
     * CONSTRUCTOR VALIDATION
     * ========================================================================= */

    public function testEmptyNameThrowsException(): void {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'empty' );
        new CategoryModel( '' );
    }

    /* =========================================================================
     * BASIC ACCESSORS
     * ========================================================================= */

    public function testGetNameReturnsCorrectValue(): void {
        $model = new CategoryModel( 'TestCategory' );
        $this->assertEquals( 'TestCategory', $model->getName() );
    }
}
```

**Patterns:**
- Section headers with `/* ===== SECTION ===== */` comments
- Test method naming: `test<Behavior>` or `test<Method><Scenario>`
- Return type `void` on all test methods
- Use `@covers` annotation at class level
- Group related tests under section headers

## Setup/Teardown

**Unit Test Bootstrap (`tests/phpunit/bootstrap.php`):**
```php
<?php
// Load Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Define MediaWiki constants tests might need
if ( !defined( 'NS_TEMPLATE' ) ) {
    define( 'NS_TEMPLATE', 10 );
}
if ( !defined( 'NS_CATEGORY' ) ) {
    define( 'NS_CATEGORY', 14 );
}
if ( !defined( 'NS_SUBOBJECT' ) ) {
    define( 'NS_SUBOBJECT', 3300 );
}

// Mock MediaWiki functions if not defined
if ( !function_exists( 'wfLogWarning' ) ) {
    function wfLogWarning( $msg ) {
        // Silent in tests
    }
}

if ( !function_exists( 'wfTimestamp' ) ) {
    function wfTimestamp( $type, $ts = null ) {
        if ( $ts === null ) {
            $ts = time();
        }
        return gmdate( 'Y-m-d\TH:i:s\Z', $ts );
    }
}
```

**Per-Test Setup:**
```php
protected function setUp(): void {
    parent::setUp();
    $this->validator = new SchemaValidator();
}
```

**Conditional Skip for Environment Dependencies:**
```php
protected function setUp(): void {
    parent::setUp();

    // Skip tests if MediaWiki classes aren't available
    if ( !class_exists( 'MediaWiki\Title\Title' ) ) {
        $this->markTestSkipped( 'StateManager tests require MediaWiki environment' );
    }
}
```

**Integration Test Cleanup:**
```php
protected function setUp(): void {
    parent::setUp();
    $this->tempDir = sys_get_temp_dir() . '/schemaloader_test_' . uniqid();
    mkdir( $this->tempDir, 0755, true );
}

protected function tearDown(): void {
    // Clean up temp directory
    if ( is_dir( $this->tempDir ) ) {
        $files = glob( $this->tempDir . '/*' );
        foreach ( $files as $file ) {
            unlink( $file );
        }
        rmdir( $this->tempDir );
    }
    parent::tearDown();
}
```

## Mocking

**Framework:** PHPUnit built-in mocks

**Patterns:**
```php
public function testGenerateSemanticTemplateWithEmptyNameThrowsException(): void {
    $this->expectException( InvalidArgumentException::class );

    // Create a mock that returns empty name
    $category = $this->createMock( CategoryModel::class );
    $category->method( 'getName' )->willReturn( '' );
    $category->method( 'getAllProperties' )->willReturn( [] );

    $this->generator->generateSemanticTemplate( $category );
}
```

**What to Mock:**
- External dependencies (MediaWiki services) in unit tests
- Complex collaborators when testing specific behavior
- Title objects when not testing title handling

**What NOT to Mock:**
- Model classes in most tests (use real instances)
- Value objects that are cheap to construct
- Classes under test

## Fixtures and Factories

**Test Data - Helper Methods:**
```php
private function getValidSchema(): array {
    return [
        'schemaVersion' => '1.0',
        'categories' => [
            'TestCategory' => [
                'label' => 'Test Category',
                'description' => 'A test category',
                'parents' => [],
                'properties' => [
                    'required' => [ 'Has name' ],
                    'optional' => [ 'Has description' ],
                ],
                'display' => [
                    'header' => [ 'Has name' ],
                    'sections' => [
                        [ 'name' => 'Basic Info', 'properties' => [ 'Has name', 'Has description' ] ],
                    ],
                ],
                'forms' => [
                    'sections' => [
                        [ 'name' => 'Basic Info', 'properties' => [ 'Has name', 'Has description' ] ],
                    ],
                ],
            ],
        ],
        'properties' => [
            'Has name' => [ 'datatype' => 'Text' ],
            'Has description' => [ 'datatype' => 'Text' ],
        ],
    ];
}
```

**Unique Test Data:**
```php
public function testWriteCategoryCreatesNewCategory(): void {
    $category = new CategoryModel( 'TestCat ' . uniqid(), [
        'description' => 'A test category',
    ] );
    // ...
}
```

**Location:**
- Helper methods within test class (private)
- Shared fixtures in dedicated methods

## Coverage

**Requirements:** No enforced coverage threshold

**View Coverage:**
```bash
php vendor/bin/phpunit --coverage-html coverage/
```

**Configuration (from `phpunit.xml.dist`):**
```xml
<coverage>
    <include>
        <directory suffix=".php">src</directory>
    </include>
    <exclude>
        <directory>vendor</directory>
        <directory>tests</directory>
    </exclude>
</coverage>
```

## Test Types

**Unit Tests:**
- Location: `tests/phpunit/unit/`
- Scope: Single class, isolated from dependencies
- Environment: Standalone PHPUnit (no MediaWiki)
- Bootstrap: `tests/phpunit/bootstrap.php`
- Can test: Models, validators, loaders, pure logic

**Integration Tests:**
- Location: `tests/phpunit/integration/`
- Scope: Multiple classes interacting with MediaWiki/SMW
- Environment: Full MediaWiki with Docker
- Bootstrap: `tests/phpunit/integration-bootstrap.php`
- Group annotations: `@group Database`, `@group Broken` for skipped tests
- Can test: Wiki stores, page operations, SMW integration

**Integration Test Base Class:**
```php
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\SemanticSchemas\Store\WikiCategoryStore
 * @group Database
 * @group Broken
 */
class WikiCategoryStoreTest extends MediaWikiIntegrationTestCase {
    // ...
}
```

## Common Patterns

**Async Testing (N/A):**
- Not applicable - PHP is synchronous

**Error Testing:**
```php
public function testMissingDatatypeReturnsError(): void {
    $schema = $this->getValidSchema();
    unset( $schema['properties']['Has name']['datatype'] );

    $errors = $this->validator->validateSchema( $schema );
    $this->assertNotEmpty( $errors );
    $this->assertStringContainsString( 'datatype', $errors[0] );
}
```

**Exception Testing:**
```php
public function testLoadFromJsonWithEmptyStringThrows(): void {
    $this->expectException( RuntimeException::class );
    $this->expectExceptionMessage( 'Empty JSON' );

    $this->loader->loadFromJson( '' );
}
```

**Testing Immutability:**
```php
public function testMergeWithParentReturnsNewInstance(): void {
    $parent = new CategoryModel( 'Parent' );
    $child = new CategoryModel( 'Child', [ 'parents' => [ 'Parent' ] ] );

    $merged = $child->mergeWithParent( $parent );
    $this->assertNotSame( $child, $merged );
    $this->assertNotSame( $parent, $merged );
}

public function testMergeDoesNotModifyOriginalChild(): void {
    $parent = new CategoryModel( 'Parent', [
        'properties' => [ 'required' => [ 'Has parent prop' ], 'optional' => [] ],
    ] );
    $child = new CategoryModel( 'Child', [
        'properties' => [ 'required' => [ 'Has child prop' ], 'optional' => [] ],
    ] );

    $originalChildProps = $child->getRequiredProperties();
    $child->mergeWithParent( $parent );

    $this->assertEquals( $originalChildProps, $child->getRequiredProperties() );
}
```

**Testing Edge Cases:**
```php
public function testGetNameTrimmed(): void {
    $model = new CategoryModel( '  TestCategory  ' );
    $this->assertEquals( 'TestCategory', $model->getName() );
}

public function testMakeTitleReturnsNullForWhitespaceOnly(): void {
    $title = $this->pageCreator->makeTitle( '   ', NS_MAIN );
    $this->assertNull( $title );
}
```

## Running Integration Tests

**Docker Environment:**
```bash
# Setup/reset Docker test environment
bash ./tests/scripts/reinstall_test_env.sh

# Populate test data
bash tests/scripts/populate_test_data.sh

# View logs
docker compose logs -f wiki
```

**Access wiki at:** http://localhost:8889 (Admin/dockerpass)

**Integration tests require:**
- Semantic MediaWiki extension
- PageForms extension
- Database connection
- SMW property tables initialized

---

*Testing analysis: 2026-01-19*
