<?php

namespace MediaWiki\Extension\StructureSync\Schema;

use MediaWiki\Extension\StructureSync\Store\WikiCategoryStore;
use MediaWiki\Extension\StructureSync\Store\WikiPropertyStore;

/**
 * SchemaExporter
 * --------------
 * Converts the current wiki ontology into a structured schema array.
 *
 * IMPORTANT:
 *   - By default, exportToArray() exports the raw schema (no inheritance expansion).
 *   - Inherited expansion is only optional and intended ONLY for generation/debugging.
 *
 * Output is deterministic and suitable for comparison/diffing.
 */
class SchemaExporter {

    /** @var WikiCategoryStore */
    private $categoryStore;

    /** @var WikiPropertyStore */
    private $propertyStore;

    /** @var InheritanceResolver|null */
    private $inheritanceResolver;

    /** @var string */
    private const SCHEMA_VERSION = '1.0';

    public function __construct(
        WikiCategoryStore $categoryStore = null,
        WikiPropertyStore $propertyStore = null,
        InheritanceResolver $inheritanceResolver = null
    ) {
        $this->categoryStore = $categoryStore ?? new WikiCategoryStore();
        $this->propertyStore = $propertyStore ?? new WikiPropertyStore();
        $this->inheritanceResolver = $inheritanceResolver; // Optional injection
    }

    /**
     * Export the wiki ontology to an array structure.
     *
     * @param bool $includeInherited  If true, expand inherited properties.
     * @return array
     */
    public function exportToArray( bool $includeInherited = false ): array {
        $categories = $this->categoryStore->getAllCategories();
        $properties = $this->propertyStore->getAllProperties();

        // Stabilize key ordering for deterministic diffs
        ksort( $categories );
        ksort( $properties );

        $schema = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'categories'    => [],
            'properties'    => [],
        ];

        // -------------------------------------------------------------
        // CATEGORY EXPORT
        // -------------------------------------------------------------
        if ( $includeInherited && !empty( $categories ) ) {
            $resolver = $this->inheritanceResolver ?? new InheritanceResolver( $categories );

            foreach ( $categories as $name => $category ) {
                try {
                    $effective = $resolver->getEffectiveCategory( $name );
                    $schema['categories'][$name] = $effective->toArray();
                } catch ( \RuntimeException $e ) {
                    wfLogWarning(
                        "StructureSync: Inheritance resolution failed for $name: " . $e->getMessage()
                    );
                    $schema['categories'][$name] = $category->toArray();
                }
            }
        }
        else {
            foreach ( $categories as $name => $category ) {
                $schema['categories'][$name] = $category->toArray();
            }
        }

        // -------------------------------------------------------------
        // PROPERTY EXPORT
        // -------------------------------------------------------------
        foreach ( $properties as $name => $property ) {
            $schema['properties'][$name] = $property->toArray();
        }

        return $schema;
    }

    /**
     * Export only a subset of categories (and the properties they use).
     *
     * @param string[] $categoryNames
     * @return array
     */
    public function exportCategories( array $categoryNames ): array {
        $schema = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'categories'    => [],
            'properties'    => [],
        ];

        $usedProperties = [];

        foreach ( $categoryNames as $name ) {
            $category = $this->categoryStore->readCategory( $name );
            if ( !$category ) {
                continue;
            }

            $schema['categories'][$name] = $category->toArray();
            $usedProperties = array_merge(
                $usedProperties,
                $category->getAllProperties()
            );
        }

        // Deduplicate + sort for stability
        $usedProperties = array_unique( $usedProperties );
        sort( $usedProperties );

        foreach ( $usedProperties as $propertyName ) {
            $property = $this->propertyStore->readProperty( $propertyName );
            if ( $property ) {
                $schema['properties'][$propertyName] = $property->toArray();
            }
        }

        return $schema;
    }

    /**
     * Gather statistics about current ontology.
     *
     * @return array
     */
    public function getStatistics(): array {
        $categories = $this->categoryStore->getAllCategories();
        $properties = $this->propertyStore->getAllProperties();

        $stats = [
            'categoryCount'            => count( $categories ),
            'propertyCount'            => count( $properties ),
            'categoriesWithParents'    => 0,
            'categoriesWithProperties' => 0,
            'categoriesWithDisplay'    => 0,
            'categoriesWithForms'      => 0,
        ];

        foreach ( $categories as $cat ) {
            if ( $cat->getParents() ) {
                $stats['categoriesWithParents']++;
            }
            if ( $cat->getAllProperties() ) {
                $stats['categoriesWithProperties']++;
            }
            if ( $cat->getDisplayConfig() ) {
                $stats['categoriesWithDisplay']++;
            }
            if ( $cat->getFormConfig() ) {
                $stats['categoriesWithForms']++;
            }
        }

        return $stats;
    }

    /**
     * Validate the current wiki ontology using SchemaValidator.
     *
     * @return array ['errors' => [...], 'warnings' => [...]]
     */
    public function validateWikiState(): array {
        $schema = $this->exportToArray( false );
        $validator = new SchemaValidator();

        return [
            'errors'   => $validator->validateSchema( $schema ),
            'warnings' => $validator->generateWarnings( $schema ),
        ];
    }
}
