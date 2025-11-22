<?php

namespace MediaWiki\Extension\StructureSync\Schema;

/**
 * Compares two schemas and generates a diff
 */
class SchemaComparer {

	/**
	 * Compare two schemas and return differences
	 *
	 * @param array $schemaA First schema (e.g., from file)
	 * @param array $schemaB Second schema (e.g., from wiki)
	 * @return array Diff results with added, removed, modified items
	 */
	public function compare( array $schemaA, array $schemaB ): array {
		return [
			'categories' => $this->compareCategories(
				$schemaA['categories'] ?? [],
				$schemaB['categories'] ?? []
			),
			'properties' => $this->compareProperties(
				$schemaA['properties'] ?? [],
				$schemaB['properties'] ?? []
			),
		];
	}

	/**
	 * Compare categories between two schemas
	 *
	 * @param array $categoriesA
	 * @param array $categoriesB
	 * @return array
	 */
	private function compareCategories( array $categoriesA, array $categoriesB ): array {
		$added = [];
		$removed = [];
		$modified = [];
		$unchanged = [];

		$allCategories = array_unique( array_merge(
			array_keys( $categoriesA ),
			array_keys( $categoriesB )
		) );

		foreach ( $allCategories as $categoryName ) {
			$inA = isset( $categoriesA[$categoryName] );
			$inB = isset( $categoriesB[$categoryName] );

			if ( $inA && !$inB ) {
				$added[] = [
					'name' => $categoryName,
					'data' => $categoriesA[$categoryName],
				];
			} elseif ( !$inA && $inB ) {
				$removed[] = [
					'name' => $categoryName,
					'data' => $categoriesB[$categoryName],
				];
			} else {
				$diff = $this->diffCategoryData( $categoriesA[$categoryName], $categoriesB[$categoryName] );
				if ( !empty( $diff ) ) {
					$modified[] = [
						'name' => $categoryName,
						'diff' => $diff,
						'old' => $categoriesB[$categoryName],
						'new' => $categoriesA[$categoryName],
					];
				} else {
					$unchanged[] = $categoryName;
				}
			}
		}

		return [
			'added' => $added,
			'removed' => $removed,
			'modified' => $modified,
			'unchanged' => $unchanged,
		];
	}

	/**
	 * Compare properties between two schemas
	 *
	 * @param array $propertiesA
	 * @param array $propertiesB
	 * @return array
	 */
	private function compareProperties( array $propertiesA, array $propertiesB ): array {
		$added = [];
		$removed = [];
		$modified = [];
		$unchanged = [];

		$allProperties = array_unique( array_merge(
			array_keys( $propertiesA ),
			array_keys( $propertiesB )
		) );

		foreach ( $allProperties as $propertyName ) {
			$inA = isset( $propertiesA[$propertyName] );
			$inB = isset( $propertiesB[$propertyName] );

			if ( $inA && !$inB ) {
				$added[] = [
					'name' => $propertyName,
					'data' => $propertiesA[$propertyName],
				];
			} elseif ( !$inA && $inB ) {
				$removed[] = [
					'name' => $propertyName,
					'data' => $propertiesB[$propertyName],
				];
			} else {
				$diff = $this->diffPropertyData( $propertiesA[$propertyName], $propertiesB[$propertyName] );
				if ( !empty( $diff ) ) {
					$modified[] = [
						'name' => $propertyName,
						'diff' => $diff,
						'old' => $propertiesB[$propertyName],
						'new' => $propertiesA[$propertyName],
					];
				} else {
					$unchanged[] = $propertyName;
				}
			}
		}

		return [
			'added' => $added,
			'removed' => $removed,
			'modified' => $modified,
			'unchanged' => $unchanged,
		];
	}

	/**
	 * Diff two category data arrays
	 *
	 * @param array $dataA New data
	 * @param array $dataB Old data
	 * @return array Differences
	 */
	private function diffCategoryData( array $dataA, array $dataB ): array {
		$diff = [];

		// Compare parents
		$parentsA = $dataA['parents'] ?? [];
		$parentsB = $dataB['parents'] ?? [];
		if ( $this->arraysDiffer( $parentsA, $parentsB ) ) {
			$diff['parents'] = [
				'old' => $parentsB,
				'new' => $parentsA,
			];
		}

		// Compare label
		$labelA = $dataA['label'] ?? '';
		$labelB = $dataB['label'] ?? '';
		if ( $labelA !== $labelB ) {
			$diff['label'] = [
				'old' => $labelB,
				'new' => $labelA,
			];
		}

		// Compare description
		$descA = $dataA['description'] ?? '';
		$descB = $dataB['description'] ?? '';
		if ( $descA !== $descB ) {
			$diff['description'] = [
				'old' => $descB,
				'new' => $descA,
			];
		}

		// Compare properties
		$propsA = $dataA['properties'] ?? [];
		$propsB = $dataB['properties'] ?? [];

		$requiredA = $propsA['required'] ?? [];
		$requiredB = $propsB['required'] ?? [];
		if ( $this->arraysDiffer( $requiredA, $requiredB ) ) {
			$diff['properties']['required'] = [
				'old' => $requiredB,
				'new' => $requiredA,
			];
		}

		$optionalA = $propsA['optional'] ?? [];
		$optionalB = $propsB['optional'] ?? [];
		if ( $this->arraysDiffer( $optionalA, $optionalB ) ) {
			$diff['properties']['optional'] = [
				'old' => $optionalB,
				'new' => $optionalA,
			];
		}

		// Compare display config (simplified - just check if different)
		$displayA = $dataA['display'] ?? [];
		$displayB = $dataB['display'] ?? [];
		if ( $this->deepDiffer( $displayA, $displayB ) ) {
			$diff['display'] = [
				'old' => $displayB,
				'new' => $displayA,
			];
		}

		// Compare form config
		$formsA = $dataA['forms'] ?? [];
		$formsB = $dataB['forms'] ?? [];
		if ( $this->deepDiffer( $formsA, $formsB ) ) {
			$diff['forms'] = [
				'old' => $formsB,
				'new' => $formsA,
			];
		}

		return $diff;
	}

	/**
	 * Diff two property data arrays
	 *
	 * @param array $dataA New data
	 * @param array $dataB Old data
	 * @return array Differences
	 */
	private function diffPropertyData( array $dataA, array $dataB ): array {
		$diff = [];

		// Compare datatype
		$datatypeA = $dataA['datatype'] ?? '';
		$datatypeB = $dataB['datatype'] ?? '';
		if ( $datatypeA !== $datatypeB ) {
			$diff['datatype'] = [
				'old' => $datatypeB,
				'new' => $datatypeA,
			];
		}

		// Compare label
		$labelA = $dataA['label'] ?? '';
		$labelB = $dataB['label'] ?? '';
		if ( $labelA !== $labelB ) {
			$diff['label'] = [
				'old' => $labelB,
				'new' => $labelA,
			];
		}

		// Compare description
		$descA = $dataA['description'] ?? '';
		$descB = $dataB['description'] ?? '';
		if ( $descA !== $descB ) {
			$diff['description'] = [
				'old' => $descB,
				'new' => $descA,
			];
		}

		// Compare allowed values
		$allowedA = $dataA['allowedValues'] ?? [];
		$allowedB = $dataB['allowedValues'] ?? [];
		if ( $this->arraysDiffer( $allowedA, $allowedB ) ) {
			$diff['allowedValues'] = [
				'old' => $allowedB,
				'new' => $allowedA,
			];
		}

		// Compare range category
		$rangeA = $dataA['rangeCategory'] ?? null;
		$rangeB = $dataB['rangeCategory'] ?? null;
		if ( $rangeA !== $rangeB ) {
			$diff['rangeCategory'] = [
				'old' => $rangeB,
				'new' => $rangeA,
			];
		}

		return $diff;
	}

	/**
	 * Check if two arrays differ (order-independent for simple arrays)
	 *
	 * @param array $a
	 * @param array $b
	 * @return bool
	 */
	private function arraysDiffer( array $a, array $b ): bool {
		if ( count( $a ) !== count( $b ) ) {
			return true;
		}

		sort( $a );
		sort( $b );

		return $a !== $b;
	}

	/**
	 * Deep comparison of arrays/values
	 *
	 * @param mixed $a
	 * @param mixed $b
	 * @return bool True if different
	 */
	private function deepDiffer( $a, $b ): bool {
		return json_encode( $a ) !== json_encode( $b );
	}

	/**
	 * Generate a human-readable summary of differences
	 *
	 * @param array $diff Result from compare()
	 * @return string
	 */
	public function generateSummary( array $diff ): string {
		$lines = [];

		$catDiff = $diff['categories'] ?? [];
		$propDiff = $diff['properties'] ?? [];

		// Categories
		$addedCount = count( $catDiff['added'] ?? [] );
		$removedCount = count( $catDiff['removed'] ?? [] );
		$modifiedCount = count( $catDiff['modified'] ?? [] );
		$unchangedCount = count( $catDiff['unchanged'] ?? [] );

		$lines[] = "Categories:";
		$lines[] = "  Added: $addedCount";
		$lines[] = "  Removed: $removedCount";
		$lines[] = "  Modified: $modifiedCount";
		$lines[] = "  Unchanged: $unchangedCount";

		// Properties
		$addedCount = count( $propDiff['added'] ?? [] );
		$removedCount = count( $propDiff['removed'] ?? [] );
		$modifiedCount = count( $propDiff['modified'] ?? [] );
		$unchangedCount = count( $propDiff['unchanged'] ?? [] );

		$lines[] = "";
		$lines[] = "Properties:";
		$lines[] = "  Added: $addedCount";
		$lines[] = "  Removed: $removedCount";
		$lines[] = "  Modified: $modifiedCount";
		$lines[] = "  Unchanged: $unchangedCount";

		return implode( "\n", $lines );
	}
}

