<?php

namespace MediaWiki\Extension\StructureSync\Service;

use MediaWiki\Extension\StructureSync\Schema\CategoryModel;
use MediaWiki\Extension\StructureSync\Schema\InheritanceResolver;
use MediaWiki\Extension\StructureSync\Store\WikiCategoryStore;

/**
 * CategoryHierarchyService
 * -------------------------
 * Service for generating category hierarchy visualization data.
 * 
 * Responsibilities:
 * - Wrap InheritanceResolver to provide hierarchy data for UI consumption
 * - Build tree structure with parent relationships
 * - Extract inherited properties with source category and required/optional status
 * - Format data for API and frontend rendering
 * 
 * Usage:
 * - Called by API module to serve hierarchy data
 * - Called by parser functions for server-side rendering
 * 
 * Data Format:
 * Returns array with:
 *   - rootCategory: The category name being queried
 *   - nodes: Map of category names to node data (title, parents)
 *   - inheritedProperties: List of properties with source and required flag
 * 
 * Performance:
 * - Leverages InheritanceResolver's memoization
 * - Single pass through ancestor chain
 * - Results suitable for JSON serialization
 */
class CategoryHierarchyService
{
    /** @var WikiCategoryStore */
    private $categoryStore;

    /**
     * @param WikiCategoryStore|null $categoryStore
     */
    public function __construct(WikiCategoryStore $categoryStore = null)
    {
        $this->categoryStore = $categoryStore ?? new WikiCategoryStore();
    }

    /**
     * Get hierarchy data for a single category.
     * 
     * Returns a structure suitable for visualization:
     * [
     *   'rootCategory' => 'Category:Name',
     *   'nodes' => [
     *     'Category:Name' => ['title' => 'Category:Name', 'parents' => ['Category:Parent1', ...]],
     *     'Category:Parent1' => ['title' => 'Category:Parent1', 'parents' => [...]],
     *     ...
     *   ],
     *   'inheritedProperties' => [
     *     ['propertyTitle' => 'Property:Has email', 'sourceCategory' => 'Category:Person', 'required' => true],
     *     ...
     *   ]
     * ]
     *
     * @param string $categoryName Category name (without "Category:" prefix)
     * @return array Hierarchy data structure, or empty structure if category not found
     */
    public function getHierarchyData(string $categoryName): array
    {
        // Initialize empty result
        $result = [
            'rootCategory' => "Category:$categoryName",
            'nodes' => [],
            'inheritedProperties' => [],
        ];

        // Load the category
        $category = $this->categoryStore->readCategory($categoryName);
        if ($category === null) {
            return $result;
        }

        // Load all categories for inheritance resolution
        $allCategories = $this->categoryStore->getAllCategories();
        if (empty($allCategories)) {
            return $result;
        }

        // Create inheritance resolver
        $resolver = new InheritanceResolver($allCategories);

        // Get ancestor chain (includes self)
        $ancestors = $resolver->getAncestors($categoryName);

        // Build node map by walking ancestors
        $visited = [];
        foreach ($ancestors as $ancestorName) {
            $this->buildNodeTree($ancestorName, $allCategories, $result['nodes'], $visited);
        }

        // Extract inherited properties with source information
        $result['inheritedProperties'] = $this->extractInheritedProperties(
            $categoryName,
            $resolver,
            $allCategories
        );

        return $result;
    }

    /**
     * Recursively build node tree structure.
     * 
     * Each node contains:
     * - title: Full category title with "Category:" prefix
     * - parents: Array of parent category titles
     *
     * @param string $categoryName Category name (without prefix)
     * @param array<string,CategoryModel> $allCategories Map of all categories
     * @param array &$nodes Node map being built
     * @param array &$visited Set of visited categories to prevent infinite loops
     */
    private function buildNodeTree(
        string $categoryName,
        array $allCategories,
        array &$nodes,
        array &$visited
    ): void {
        // Prevent infinite loops
        if (isset($visited[$categoryName])) {
            return;
        }
        $visited[$categoryName] = true;

        // Get category model
        $category = $allCategories[$categoryName] ?? null;
        if ($category === null) {
            return;
        }

        // Build node data
        $fullTitle = "Category:$categoryName";
        $parents = [];
        foreach ($category->getParents() as $parentName) {
            $parents[] = "Category:$parentName";
        }

        $nodes[$fullTitle] = [
            'title' => $fullTitle,
            'parents' => $parents,
        ];

        // Recursively process parents
        foreach ($category->getParents() as $parentName) {
            $this->buildNodeTree($parentName, $allCategories, $nodes, $visited);
        }
    }

    /**
     * Extract inherited properties with source category and required/optional status.
     * 
     * Walks through the ancestor chain and collects properties from each level,
     * tracking which category contributed each property and whether it's required.
     *
     * @param string $categoryName Category name (without prefix)
     * @param InheritanceResolver $resolver Inheritance resolver
     * @param array<string,CategoryModel> $allCategories Map of all categories
     * @return array List of property data: [propertyTitle, sourceCategory, required]
     */
    private function extractInheritedProperties(
        string $categoryName,
        InheritanceResolver $resolver,
        array $allCategories
    ): array {
        $properties = [];
        $seenProperties = []; // Track which properties we've already added

        // Get ancestor chain (includes self, most specific first)
        $ancestors = $resolver->getAncestors($categoryName);

        // Walk through ancestors and collect properties
        foreach ($ancestors as $ancestorName) {
            $ancestor = $allCategories[$ancestorName] ?? null;
            if ($ancestor === null) {
                continue;
            }

            $sourceCategoryTitle = "Category:$ancestorName";

            // Add required properties from this ancestor
            foreach ($ancestor->getRequiredProperties() as $propName) {
                // Only add if we haven't seen this property yet (first occurrence wins for source)
                if (!isset($seenProperties[$propName])) {
                    $properties[] = [
                        'propertyTitle' => "Property:$propName",
                        'sourceCategory' => $sourceCategoryTitle,
                        'required' => true,
                    ];
                    $seenProperties[$propName] = true;
                }
            }

            // Add optional properties from this ancestor
            foreach ($ancestor->getOptionalProperties() as $propName) {
                // Only add if we haven't seen this property yet
                if (!isset($seenProperties[$propName])) {
                    $properties[] = [
                        'propertyTitle' => "Property:$propName",
                        'sourceCategory' => $sourceCategoryTitle,
                        'required' => false,
                    ];
                    $seenProperties[$propName] = true;
                }
            }
        }

        return $properties;
    }
}

