<?php

namespace MediaWiki\Extension\StructureSync\Schema;

use MediaWiki\Extension\StructureSync\Store\WikiCategoryStore;
use MediaWiki\Extension\StructureSync\Store\WikiPropertyStore;

/**
 * Exports the current wiki ontology to a schema array
 */
class SchemaExporter {

	/** @var WikiCategoryStore */
	private $categoryStore;

	/** @var WikiPropertyStore */
	private $propertyStore;

	/** @var InheritanceResolver|null */
	private $inheritanceResolver;

	/**
	 * @param WikiCategoryStore|null $categoryStore
	 * @param WikiPropertyStore|null $propertyStore
	 */
	public function __construct(
		WikiCategoryStore $categoryStore = null,
		WikiPropertyStore $propertyStore = null
	) {
		$this->categoryStore = $categoryStore ?? new WikiCategoryStore();
		$this->propertyStore = $propertyStore ?? new WikiPropertyStore();
	}

	/**
	 * Export the current wiki state to a schema array
	 *
	 * @param bool $includeInherited Whether to compute and include inherited properties
	 * @return array Schema array
	 */
	public function exportToArray( bool $includeInherited = false ): array {
		$categories = $this->categoryStore->getAllCategories();
		$properties = $this->propertyStore->getAllProperties();

		// Build schema structure
		$schema = [
			'schemaVersion' => '1.0',
			'categories' => [],
			'properties' => [],
		];

		// If including inherited properties, use InheritanceResolver
		if ( $includeInherited && !empty( $categories ) ) {
			$this->inheritanceResolver = new InheritanceResolver( $categories );

			foreach ( $categories as $categoryName => $category ) {
				try {
					// Get effective category with inherited properties
					$effectiveCategory = $this->inheritanceResolver->getEffectiveCategory( $categoryName );
					$schema['categories'][$categoryName] = $effectiveCategory->toArray();
				} catch ( \RuntimeException $e ) {
					// Log error but continue with non-inherited version
					wfLogWarning( "StructureSync: Error resolving inheritance for $categoryName: " . $e->getMessage() );
					$schema['categories'][$categoryName] = $category->toArray();
				}
			}
		} else {
			// Export without inheritance resolution
			foreach ( $categories as $categoryName => $category ) {
				$schema['categories'][$categoryName] = $category->toArray();
			}
		}

		// Export properties
		foreach ( $properties as $propertyName => $property ) {
			$schema['properties'][$propertyName] = $property->toArray();
		}

		return $schema;
	}

	/**
	 * Export specific categories to schema array
	 *
	 * @param string[] $categoryNames
	 * @return array Schema array
	 */
	public function exportCategories( array $categoryNames ): array {
		$schema = [
			'schemaVersion' => '1.0',
			'categories' => [],
			'properties' => [],
		];

		$usedProperties = [];

		foreach ( $categoryNames as $categoryName ) {
			$category = $this->categoryStore->readCategory( $categoryName );
			if ( $category === null ) {
				continue;
			}

			$schema['categories'][$categoryName] = $category->toArray();

			// Track which properties are used
			$usedProperties = array_merge(
				$usedProperties,
				$category->getAllProperties()
			);
		}

		// Export only the properties used by these categories
		$usedProperties = array_unique( $usedProperties );
		foreach ( $usedProperties as $propertyName ) {
			$property = $this->propertyStore->readProperty( $propertyName );
			if ( $property !== null ) {
				$schema['properties'][$propertyName] = $property->toArray();
			}
		}

		return $schema;
	}

	/**
	 * Get statistics about the current ontology
	 *
	 * @return array Statistics array
	 */
	public function getStatistics(): array {
		$categories = $this->categoryStore->getAllCategories();
		$properties = $this->propertyStore->getAllProperties();

		$stats = [
			'categoryCount' => count( $categories ),
			'propertyCount' => count( $properties ),
			'categoriesWithParents' => 0,
			'categoriesWithProperties' => 0,
			'categoriesWithDisplay' => 0,
			'categoriesWithForms' => 0,
		];

		foreach ( $categories as $category ) {
			if ( !empty( $category->getParents() ) ) {
				$stats['categoriesWithParents']++;
			}
			if ( !empty( $category->getAllProperties() ) ) {
				$stats['categoriesWithProperties']++;
			}
			if ( !empty( $category->getDisplayConfig() ) ) {
				$stats['categoriesWithDisplay']++;
			}
			if ( !empty( $category->getFormConfig() ) ) {
				$stats['categoriesWithForms']++;
			}
		}

		return $stats;
	}

	/**
	 * Validate the current wiki state
	 *
	 * @return array Array with 'errors' and 'warnings' keys
	 */
	public function validateWikiState(): array {
		$schema = $this->exportToArray( false );
		$validator = new SchemaValidator();

		$errors = $validator->validateSchema( $schema );
		$warnings = $validator->generateWarnings( $schema );

		return [
			'errors' => $errors,
			'warnings' => $warnings,
		];
	}
}

