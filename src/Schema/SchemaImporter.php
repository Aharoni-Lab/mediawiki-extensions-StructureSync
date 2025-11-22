<?php

namespace MediaWiki\Extension\StructureSync\Schema;

use MediaWiki\Extension\StructureSync\Store\WikiCategoryStore;
use MediaWiki\Extension\StructureSync\Store\WikiPropertyStore;

/**
 * Imports a schema array into the wiki
 */
class SchemaImporter {

	/** @var WikiCategoryStore */
	private $categoryStore;

	/** @var WikiPropertyStore */
	private $propertyStore;

	/** @var SchemaValidator */
	private $validator;

	/**
	 * @param WikiCategoryStore|null $categoryStore
	 * @param WikiPropertyStore|null $propertyStore
	 * @param SchemaValidator|null $validator
	 */
	public function __construct(
		WikiCategoryStore $categoryStore = null,
		WikiPropertyStore $propertyStore = null,
		SchemaValidator $validator = null
	) {
		$this->categoryStore = $categoryStore ?? new WikiCategoryStore();
		$this->propertyStore = $propertyStore ?? new WikiPropertyStore();
		$this->validator = $validator ?? new SchemaValidator();
	}

	/**
	 * Import schema into the wiki
	 *
	 * @param array $schema Schema array
	 * @param array $options Import options
	 *        - dryRun: bool - Don't actually write, just report what would be done
	 *        - generateArtifacts: bool - Generate templates and forms after import
	 *        - overwrite: bool - Overwrite existing pages
	 * @return array Result with counts and errors
	 */
	public function importFromArray( array $schema, array $options = [] ): array {
		$dryRun = $options['dryRun'] ?? false;
		$generateArtifacts = $options['generateArtifacts'] ?? true;

		// Validate schema first
		$errors = $this->validator->validateSchema( $schema );
		if ( !empty( $errors ) ) {
			return [
				'success' => false,
				'errors' => $errors,
				'categoriesCreated' => 0,
				'categoriesUpdated' => 0,
				'categoriesUnchanged' => 0,
				'propertiesCreated' => 0,
				'propertiesUpdated' => 0,
				'propertiesUnchanged' => 0,
			];
		}

		$result = [
			'success' => true,
			'errors' => [],
			'categoriesCreated' => 0,
			'categoriesUpdated' => 0,
			'categoriesUnchanged' => 0,
			'propertiesCreated' => 0,
			'propertiesUpdated' => 0,
			'propertiesUnchanged' => 0,
		];

		// Import properties first (categories depend on them)
		if ( isset( $schema['properties'] ) ) {
			$propertyResult = $this->importProperties( $schema['properties'], $dryRun );
			$result['propertiesCreated'] = $propertyResult['created'];
			$result['propertiesUpdated'] = $propertyResult['updated'];
			$result['propertiesUnchanged'] = $propertyResult['unchanged'];
			$result['errors'] = array_merge( $result['errors'], $propertyResult['errors'] );
		}

		// Import categories
		if ( isset( $schema['categories'] ) ) {
			$categoryResult = $this->importCategories( $schema['categories'], $dryRun );
			$result['categoriesCreated'] = $categoryResult['created'];
			$result['categoriesUpdated'] = $categoryResult['updated'];
			$result['categoriesUnchanged'] = $categoryResult['unchanged'];
			$result['errors'] = array_merge( $result['errors'], $categoryResult['errors'] );
		}

		return $result;
	}

	/**
	 * Import properties from schema
	 *
	 * @param array $properties
	 * @param bool $dryRun
	 * @return array
	 */
	private function importProperties( array $properties, bool $dryRun ): array {
		$result = [
			'created' => 0,
			'updated' => 0,
			'unchanged' => 0,
			'errors' => [],
		];

		foreach ( $properties as $propertyName => $propertyData ) {
			try {
				$exists = $this->propertyStore->propertyExists( $propertyName );

				if ( $dryRun ) {
					if ( $exists ) {
						$result['updated']++;
					} else {
						$result['created']++;
					}
					continue;
				}

				$property = new PropertyModel( $propertyName, $propertyData );
				$success = $this->propertyStore->writeProperty( $property );

				if ( $success ) {
					if ( $exists ) {
						$result['updated']++;
					} else {
						$result['created']++;
					}
				} else {
					$result['errors'][] = "Failed to write property: $propertyName";
				}
			} catch ( \Exception $e ) {
				$result['errors'][] = "Error importing property '$propertyName': " . $e->getMessage();
			}
		}

		return $result;
	}

	/**
	 * Import categories from schema
	 *
	 * @param array $categories
	 * @param bool $dryRun
	 * @return array
	 */
	private function importCategories( array $categories, bool $dryRun ): array {
		$result = [
			'created' => 0,
			'updated' => 0,
			'unchanged' => 0,
			'errors' => [],
		];

		// Sort categories by dependency (parents first)
		$sortedCategories = $this->sortCategoriesByDependency( $categories );

		foreach ( $sortedCategories as $categoryName ) {
			$categoryData = $categories[$categoryName];

			try {
				$exists = $this->categoryStore->categoryExists( $categoryName );

				if ( $dryRun ) {
					if ( $exists ) {
						$result['updated']++;
					} else {
						$result['created']++;
					}
					continue;
				}

				$category = new CategoryModel( $categoryName, $categoryData );
				$success = $this->categoryStore->writeCategory( $category );

				if ( $success ) {
					if ( $exists ) {
						$result['updated']++;
					} else {
						$result['created']++;
					}
				} else {
					$result['errors'][] = "Failed to write category: $categoryName";
				}
			} catch ( \Exception $e ) {
				$result['errors'][] = "Error importing category '$categoryName': " . $e->getMessage();
			}
		}

		return $result;
	}

	/**
	 * Sort categories so parents are imported before children
	 *
	 * @param array $categories
	 * @return array Sorted category names
	 */
	private function sortCategoriesByDependency( array $categories ): array {
		$sorted = [];
		$visited = [];
		$visiting = [];

		foreach ( array_keys( $categories ) as $categoryName ) {
			$this->visitCategory( $categoryName, $categories, $visited, $visiting, $sorted );
		}

		return $sorted;
	}

	/**
	 * Visit a category for dependency sorting (topological sort)
	 *
	 * @param string $categoryName
	 * @param array $categories
	 * @param array &$visited
	 * @param array &$visiting
	 * @param array &$sorted
	 */
	private function visitCategory( string $categoryName, array $categories, array &$visited, array &$visiting, array &$sorted ): void {
		if ( isset( $visited[$categoryName] ) ) {
			return;
		}

		if ( isset( $visiting[$categoryName] ) ) {
			// Circular dependency - skip
			return;
		}

		$visiting[$categoryName] = true;

		// Visit parents first
		if ( isset( $categories[$categoryName]['parents'] ) ) {
			foreach ( $categories[$categoryName]['parents'] as $parent ) {
				if ( isset( $categories[$parent] ) ) {
					$this->visitCategory( $parent, $categories, $visited, $visiting, $sorted );
				}
			}
		}

		unset( $visiting[$categoryName] );
		$visited[$categoryName] = true;
		$sorted[] = $categoryName;
	}

	/**
	 * Preview import changes
	 *
	 * @param array $schema
	 * @return array Preview of changes
	 */
	public function previewImport( array $schema ): array {
		$preview = [
			'categories' => [
				'new' => [],
				'existing' => [],
			],
			'properties' => [
				'new' => [],
				'existing' => [],
			],
		];

		// Check categories
		if ( isset( $schema['categories'] ) ) {
			foreach ( array_keys( $schema['categories'] ) as $categoryName ) {
				if ( $this->categoryStore->categoryExists( $categoryName ) ) {
					$preview['categories']['existing'][] = $categoryName;
				} else {
					$preview['categories']['new'][] = $categoryName;
				}
			}
		}

		// Check properties
		if ( isset( $schema['properties'] ) ) {
			foreach ( array_keys( $schema['properties'] ) as $propertyName ) {
				if ( $this->propertyStore->propertyExists( $propertyName ) ) {
					$preview['properties']['existing'][] = $propertyName;
				} else {
					$preview['properties']['new'][] = $propertyName;
				}
			}
		}

		return $preview;
	}
}

