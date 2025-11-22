<?php

namespace MediaWiki\Extension\StructureSync\Schema;

/**
 * Resolves multiple inheritance for categories using C3 linearization
 */
class InheritanceResolver {

	/** @var array<string,CategoryModel> */
	private $categoryMap;

	/** @var array<string,string[]> Cache of resolved ancestors */
	private $ancestorCache = [];

	/**
	 * @param array<string,CategoryModel> $categoryMap Map of category name => CategoryModel
	 */
	public function __construct( array $categoryMap ) {
		$this->categoryMap = $categoryMap;
	}

	/**
	 * Get ordered list of ancestors for a category using C3 linearization
	 *
	 * @param string $categoryName
	 * @return string[] Ordered list of ancestor names (includes the category itself)
	 * @throws \RuntimeException If circular dependency detected
	 */
	public function getAncestors( string $categoryName ): array {
		if ( isset( $this->ancestorCache[$categoryName] ) ) {
			return $this->ancestorCache[$categoryName];
		}

		if ( !isset( $this->categoryMap[$categoryName] ) ) {
			return [ $categoryName ];
		}

		$ancestors = $this->c3Linearization( $categoryName, [] );
		$this->ancestorCache[$categoryName] = $ancestors;

		return $ancestors;
	}

	/**
	 * C3 linearization algorithm for multiple inheritance
	 *
	 * @param string $categoryName
	 * @param array $visiting Track categories being visited to detect cycles
	 * @return string[]
	 * @throws \RuntimeException If circular dependency detected
	 */
	private function c3Linearization( string $categoryName, array $visiting ): array {
		// Detect circular dependencies
		if ( in_array( $categoryName, $visiting ) ) {
			throw new \RuntimeException(
				"Circular dependency detected in category: $categoryName"
			);
		}

		if ( !isset( $this->categoryMap[$categoryName] ) ) {
			return [ $categoryName ];
		}

		$category = $this->categoryMap[$categoryName];
		$parents = $category->getParents();

		// Base case: no parents
		if ( empty( $parents ) ) {
			return [ $categoryName ];
		}

		$visiting[] = $categoryName;

		// Get linearizations of all parents
		$parentLinearizations = [];
		foreach ( $parents as $parent ) {
			$parentLinearizations[] = $this->c3Linearization( $parent, $visiting );
		}

		// Merge parent linearizations with the parent list
		$merged = $this->c3Merge( array_merge( $parentLinearizations, [ $parents ] ) );

		// Prepend current category
		array_unshift( $merged, $categoryName );

		return $merged;
	}

	/**
	 * Merge multiple linearizations using C3 algorithm
	 *
	 * @param array $sequences Array of sequences to merge
	 * @return array
	 */
	private function c3Merge( array $sequences ): array {
		$result = [];

		while ( !$this->allSequencesEmpty( $sequences ) ) {
			$candidate = $this->findGoodHead( $sequences );

			if ( $candidate === null ) {
				// Cannot find a good head - inconsistent hierarchy
				// Fall back to simple strategy: take first available
				foreach ( $sequences as $seq ) {
					if ( !empty( $seq ) ) {
						$candidate = $seq[0];
						break;
					}
				}
			}

			if ( $candidate === null ) {
				break;
			}

			$result[] = $candidate;

			// Remove candidate from all sequences
			foreach ( $sequences as $key => $seq ) {
				$sequences[$key] = array_values( array_filter( $seq, static function ( $item ) use ( $candidate ) {
					return $item !== $candidate;
				} ) );
			}
		}

		return $result;
	}

	/**
	 * Check if all sequences are empty
	 *
	 * @param array $sequences
	 * @return bool
	 */
	private function allSequencesEmpty( array $sequences ): bool {
		foreach ( $sequences as $seq ) {
			if ( !empty( $seq ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Find a good head (appears as head but not in tail of any sequence)
	 *
	 * @param array $sequences
	 * @return string|null
	 */
	private function findGoodHead( array $sequences ): ?string {
		// Try each head
		foreach ( $sequences as $seq ) {
			if ( empty( $seq ) ) {
				continue;
			}

			$head = $seq[0];
			$isGood = true;

			// Check if this head appears in the tail of any sequence
			foreach ( $sequences as $otherSeq ) {
				if ( count( $otherSeq ) > 1 && in_array( $head, array_slice( $otherSeq, 1 ) ) ) {
					$isGood = false;
					break;
				}
			}

			if ( $isGood ) {
				return $head;
			}
		}

		return null;
	}

	/**
	 * Get effective properties for a category (merged with ancestors)
	 *
	 * @param string $categoryName
	 * @return CategoryModel Category with inherited properties merged
	 */
	public function getEffectiveCategory( string $categoryName ): CategoryModel {
		if ( !isset( $this->categoryMap[$categoryName] ) ) {
			return new CategoryModel( $categoryName );
		}

		$ancestors = $this->getAncestors( $categoryName );
		// Remove the category itself from ancestors list
		$ancestorsOnly = array_slice( $ancestors, 1 );

		$category = $this->categoryMap[$categoryName];

		// Merge properties from ancestors (in reverse order, so closest ancestors override)
		foreach ( array_reverse( $ancestorsOnly ) as $ancestorName ) {
			if ( isset( $this->categoryMap[$ancestorName] ) ) {
				$ancestor = $this->categoryMap[$ancestorName];
				$category = $category->mergeWithParent( $ancestor );
			}
		}

		return $category;
	}

	/**
	 * Validate all categories for circular dependencies
	 *
	 * @return array Array of error messages
	 */
	public function validateInheritance(): array {
		$errors = [];

		foreach ( array_keys( $this->categoryMap ) as $categoryName ) {
			try {
				$this->getAncestors( $categoryName );
			} catch ( \RuntimeException $e ) {
				$errors[] = $e->getMessage();
			}
		}

		return $errors;
	}

	/**
	 * Check if categoryA is an ancestor of categoryB
	 *
	 * @param string $categoryA
	 * @param string $categoryB
	 * @return bool
	 */
	public function isAncestorOf( string $categoryA, string $categoryB ): bool {
		$ancestors = $this->getAncestors( $categoryB );
		return in_array( $categoryA, $ancestors );
	}
}

