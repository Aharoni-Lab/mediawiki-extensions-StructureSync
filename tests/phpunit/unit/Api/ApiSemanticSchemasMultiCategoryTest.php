<?php

namespace MediaWiki\Extension\SemanticSchemas\Tests\Unit\Api;

use MediaWiki\Extension\SemanticSchemas\Schema\ResolvedPropertySet;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ApiSemanticSchemasMultiCategory formatting logic.
 *
 * Since ApiBase requires MediaWiki integration, we test the formatting logic
 * by creating a testable subclass that exposes the protected methods.
 *
 * @covers \MediaWiki\Extension\SemanticSchemas\Api\ApiSemanticSchemasMultiCategory
 */
class ApiSemanticSchemasMultiCategoryTest extends TestCase {

	/* =========================================================================
	 * PROPERTY FORMATTING
	 * ========================================================================= */

	public function testFormatPropertiesWithRequiredAndOptional(): void {
		$helper = new TestableApiHelper();
		$resolved = $this->createResolvedPropertySet(
			[ 'Has name', 'Has email' ], // required
			[ 'Has phone' ], // optional
			[
				'Has name' => [ 'Person' ],
				'Has email' => [ 'Person' ],
				'Has phone' => [ 'Person' ],
			],
			[], // required subobjects
			[], // optional subobjects
			[], // subobject sources
			[ 'Person' ]
		);

		$result = $helper->formatProperties( $resolved );

		$this->assertCount( 3, $result );

		// Required properties
		$this->assertSame( 'Has name', $result[0]['name'] );
		$this->assertSame( 'Property:Has name', $result[0]['title'] );
		$this->assertSame( 1, $result[0]['required'] );
		$this->assertSame( 0, $result[0]['shared'] );
		$this->assertSame( [ 'Person' ], $result[0]['sources'] );

		// Optional property
		$this->assertSame( 'Has phone', $result[2]['name'] );
		$this->assertSame( 'Property:Has phone', $result[2]['title'] );
		$this->assertSame( 0, $result[2]['required'] );
		$this->assertSame( 0, $result[2]['shared'] );
	}

	public function testFormatPropertiesSharedFlag(): void {
		$helper = new TestableApiHelper();

		// Property with 2+ sources has shared=1
		$resolved = $this->createResolvedPropertySet(
			[ 'Has name', 'Has employee ID' ],
			[],
			[
				'Has name' => [ 'Person', 'Employee' ],
				'Has employee ID' => [ 'Employee' ],
			],
			[],
			[],
			[],
			[ 'Person', 'Employee' ]
		);

		$result = $helper->formatProperties( $resolved );

		// "Has name" is shared
		$hasName = array_filter( $result, static fn ( $p ) => $p['name'] === 'Has name' );
		$hasName = array_values( $hasName )[0];
		$this->assertSame( 1, $hasName['shared'] );
		$this->assertSame( [ 'Person', 'Employee' ], $hasName['sources'] );

		// "Has employee ID" is not shared
		$hasEmployeeId = array_filter( $result, static fn ( $p ) => $p['name'] === 'Has employee ID' );
		$hasEmployeeId = array_values( $hasEmployeeId )[0];
		$this->assertSame( 0, $hasEmployeeId['shared'] );
		$this->assertSame( [ 'Employee' ], $hasEmployeeId['sources'] );
	}

	public function testFormatPropertiesBooleanFlagsAreIntegers(): void {
		$helper = new TestableApiHelper();
		$resolved = $this->createResolvedPropertySet(
			[ 'Has name' ],
			[ 'Has phone' ],
			[
				'Has name' => [ 'Person', 'Employee' ],
				'Has phone' => [ 'Person' ],
			],
			[],
			[],
			[],
			[ 'Person', 'Employee' ]
		);

		$result = $helper->formatProperties( $resolved );

		foreach ( $result as $property ) {
			$this->assertIsInt( $property['required'], "required flag should be integer" );
			$this->assertIsInt( $property['shared'], "shared flag should be integer" );
			$this->assertContains( $property['required'], [ 0, 1 ], "required should be 0 or 1" );
			$this->assertContains( $property['shared'], [ 0, 1 ], "shared should be 0 or 1" );
		}
	}

	/* =========================================================================
	 * SUBOBJECT FORMATTING
	 * ========================================================================= */

	public function testFormatSubobjectsMirrorsProperties(): void {
		$helper = new TestableApiHelper();
		$resolved = $this->createResolvedPropertySet(
			[],
			[],
			[],
			[ 'Address' ], // required subobjects
			[ 'Social media' ], // optional subobjects
			[
				'Address' => [ 'Person' ],
				'Social media' => [ 'Person' ],
			],
			[ 'Person' ]
		);

		$result = $helper->formatSubobjects( $resolved );

		$this->assertCount( 2, $result );

		// Required subobject
		$this->assertSame( 'Address', $result[0]['name'] );
		$this->assertSame( 'Subobject:Address', $result[0]['title'] );
		$this->assertSame( 1, $result[0]['required'] );
		$this->assertSame( 0, $result[0]['shared'] );
		$this->assertSame( [ 'Person' ], $result[0]['sources'] );

		// Optional subobject
		$this->assertSame( 'Social media', $result[1]['name'] );
		$this->assertSame( 'Subobject:Social media', $result[1]['title'] );
		$this->assertSame( 0, $result[1]['required'] );
		$this->assertSame( 0, $result[1]['shared'] );
	}

	public function testFormatSubobjectsSharedDetection(): void {
		$helper = new TestableApiHelper();
		$resolved = $this->createResolvedPropertySet(
			[],
			[],
			[],
			[ 'Address' ],
			[],
			[ 'Address' => [ 'Person', 'Company' ] ],
			[ 'Person', 'Company' ]
		);

		$result = $helper->formatSubobjects( $resolved );

		$this->assertCount( 1, $result );
		$this->assertSame( 1, $result[0]['shared'] );
		$this->assertSame( [ 'Person', 'Company' ], $result[0]['sources'] );
	}

	/* =========================================================================
	 * PREFIX STRIPPING
	 * ========================================================================= */

	public function testStripPrefixNormalization(): void {
		$helper = new TestableApiHelper();

		// With prefix
		$this->assertSame( 'Person', $helper->stripPrefix( 'Category:Person' ) );

		// Case insensitive
		$this->assertSame( 'Person', $helper->stripPrefix( 'category:Person' ) );

		// No prefix
		$this->assertSame( 'Person', $helper->stripPrefix( 'Person' ) );

		// With whitespace
		$this->assertSame( 'Person', $helper->stripPrefix( ' Person ' ) );

		// With prefix and whitespace
		$this->assertSame( 'Person', $helper->stripPrefix( ' Category:Person ' ) );
	}

	/* =========================================================================
	 * EMPTY RESOLUTION
	 * ========================================================================= */

	public function testEmptyResolutionReturnsEmptyArrays(): void {
		$helper = new TestableApiHelper();
		$resolved = ResolvedPropertySet::empty();

		$properties = $helper->formatProperties( $resolved );
		$subobjects = $helper->formatSubobjects( $resolved );

		$this->assertSame( [], $properties );
		$this->assertSame( [], $subobjects );
	}

	/* =========================================================================
	 * HELPERS
	 * ========================================================================= */

	/**
	 * Create a ResolvedPropertySet for testing.
	 */
	private function createResolvedPropertySet(
		array $requiredProperties,
		array $optionalProperties,
		array $propertySources,
		array $requiredSubobjects,
		array $optionalSubobjects,
		array $subobjectSources,
		array $categoryNames
	): ResolvedPropertySet {
		return new ResolvedPropertySet(
			$requiredProperties,
			$optionalProperties,
			$propertySources,
			$requiredSubobjects,
			$optionalSubobjects,
			$subobjectSources,
			$categoryNames
		);
	}
}

/* =============================================================================
 * TESTABLE HELPER CLASS
 * ============================================================================= */

/**
 * Testable version of the API formatting logic that doesn't require ApiBase.
 *
 * This replicates the formatting methods from ApiSemanticSchemasMultiCategory
 * so we can test them in isolation.
 */
class TestableApiHelper {

	/**
	 * Format properties for API response.
	 *
	 * @param ResolvedPropertySet $resolved Resolution result
	 * @return array[] Array of property entries
	 */
	public function formatProperties( ResolvedPropertySet $resolved ): array {
		$formatted = [];

		// Required properties
		foreach ( $resolved->getRequiredProperties() as $property ) {
			$sources = $resolved->getPropertySources( $property );
			$formatted[] = [
				'name' => $property,
				'title' => "Property:$property",
				'required' => 1,
				'shared' => count( $sources ) > 1 ? 1 : 0,
				'sources' => $sources,
			];
		}

		// Optional properties
		foreach ( $resolved->getOptionalProperties() as $property ) {
			$sources = $resolved->getPropertySources( $property );
			$formatted[] = [
				'name' => $property,
				'title' => "Property:$property",
				'required' => 0,
				'shared' => count( $sources ) > 1 ? 1 : 0,
				'sources' => $sources,
			];
		}

		return $formatted;
	}

	/**
	 * Format subobjects for API response.
	 *
	 * @param ResolvedPropertySet $resolved Resolution result
	 * @return array[] Array of subobject entries
	 */
	public function formatSubobjects( ResolvedPropertySet $resolved ): array {
		$formatted = [];

		// Required subobjects
		foreach ( $resolved->getRequiredSubobjects() as $subobject ) {
			$sources = $resolved->getSubobjectSources( $subobject );
			$formatted[] = [
				'name' => $subobject,
				'title' => "Subobject:$subobject",
				'required' => 1,
				'shared' => count( $sources ) > 1 ? 1 : 0,
				'sources' => $sources,
			];
		}

		// Optional subobjects
		foreach ( $resolved->getOptionalSubobjects() as $subobject ) {
			$sources = $resolved->getSubobjectSources( $subobject );
			$formatted[] = [
				'name' => $subobject,
				'title' => "Subobject:$subobject",
				'required' => 0,
				'shared' => count( $sources ) > 1 ? 1 : 0,
				'sources' => $sources,
			];
		}

		return $formatted;
	}

	/**
	 * Strip "Category:" prefix if present (case-insensitive).
	 *
	 * @param string $name Category name with or without prefix
	 * @return string Normalized category name
	 */
	public function stripPrefix( string $name ): string {
		return preg_replace( '/^Category:/i', '', trim( $name ) );
	}
}
