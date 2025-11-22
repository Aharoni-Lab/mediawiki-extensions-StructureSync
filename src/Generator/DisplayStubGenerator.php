<?php

namespace MediaWiki\Extension\StructureSync\Generator;

use MediaWiki\Extension\StructureSync\Schema\CategoryModel;
use MediaWiki\Extension\StructureSync\Store\PageCreator;

/**
 * DisplayStubGenerator
 * --------------------
 * Generates human-editable display templates in:
 *     Template:<Category>/display
 *
 * These templates are intentionally NOT overwritten after creation.
 * Regenerating them could destroy user customizations.
 *
 * This generator:
 *   - Uses inherited display sections/header from CategoryModel
 *   - Falls back to a generic "Details" block listing all properties
 *   - Wraps each property in a safe #if so hidden = not set
 *   - Provides consistent HTML/CSS structure for easy styling
 *   - Normalizes property → parameter conversions identically to other generators
 */
class DisplayStubGenerator {

    /** @var PageCreator */
    private $pageCreator;

    public function __construct( PageCreator $pageCreator = null ) {
        $this->pageCreator = $pageCreator ?? new PageCreator();
    }

    /* =====================================================================
     * MAIN GENERATION
     * ===================================================================== */

    /**
     * Generate display template stub content.
     *
     * @param CategoryModel $category Effective category (inherited)
     * @return string
     */
    public function generateDisplayStub( CategoryModel $category ): string {

        $lines = [];

        /* ------------------------------------------------------------------
         * NOINCLUDE header
         * ------------------------------------------------------------------ */
        $lines[] = '<noinclude>';
        $lines[] = '<!-- DISPLAY TEMPLATE STUB (AUTO-CREATED by StructureSync) -->';
        $lines[] = '<!-- This template is SAFE TO EDIT and will NOT be overwritten. -->';
        $lines[] = '<!-- Customize the visual layout for pages in [[Category:' . $category->getName() . ']]. -->';
        $lines[] = '<!-- You may reorganize sections, add wikitables, images, etc. -->';
        $lines[] = '</noinclude><includeonly>';

        /* ------------------------------------------------------------------
         * HEADER SECTION (if configured)
         * ------------------------------------------------------------------ */
        $headerProps = $category->getDisplayHeaderProperties();
        if ( !empty( $headerProps ) ) {
            $lines[] = '<div class="ss-header">';

            foreach ( $headerProps as $propertyName ) {
                $param = $this->propertyToParameter( $propertyName );
                $lines[] = '  {{#if:{{{' . $param . '|}}}|';
                $lines[] = '    <h1 class="ss-header-field">{{{' . $param . '}}}</h1>';
                $lines[] = '  }}';
            }

            $lines[] = '</div>';
            $lines[] = '';
        }

        /* ------------------------------------------------------------------
         * SECTIONS from display config
         * ------------------------------------------------------------------ */
        $sections = $category->getDisplaySections();
        if ( !empty( $sections ) ) {
            foreach ( $sections as $section ) {
                $lines = array_merge( $lines, $this->generateDisplaySection( $section ) );
            }
        }
        else {
            /* --------------------------------------------------------------
             * FALLBACK SECTION
             * -------------------------------------------------------------- */
            $lines = array_merge( $lines, $this->generateDefaultDisplaySection( $category ) );
        }

        $lines[] = '</includeonly>';

        return implode( "\n", $lines );
    }

    /* =====================================================================
     * SECTION GENERATORS
     * ===================================================================== */

    /**
     * Generate a structured section defined by schema "display.sections".
     *
     * @param array<string,mixed> $section
     * @return string[]
     */
    private function generateDisplaySection( array $section ): array {
        $lines = [];

        $name = $section['name'] ?? 'Section';
        $properties = $section['properties'] ?? [];

        // Sort for stable regeneration
        $properties = array_values( array_unique( array_map( 'strval', $properties ) ) );
        sort( $properties );

        $lines[] = "== $name ==";
        $lines[] = '<div class="ss-section">';

        foreach ( $properties as $propertyName ) {
            $param = $this->propertyToParameter( $propertyName );
            $label = $this->propertyToLabel( $propertyName );

            $lines[] = '  {{#if:{{{' . $param . '|}}}|';
            $lines[] = '    <div class="ss-row">';
            $lines[] = '      <span class="ss-label">\'\'\'' . $label . ':\'\'\'</span>';
            $lines[] = '      <span class="ss-value">{{{' . $param . '}}}</span>';
            $lines[] = '    </div>';
            $lines[] = '  }}';
        }

        $lines[] = '</div>';
        $lines[] = '';

        return $lines;
    }

    /**
     * Default fallback display (if no display config exists).
     *
     * @param CategoryModel $category
     * @return array
     */
    private function generateDefaultDisplaySection( CategoryModel $category ): array {

        $properties = $category->getAllProperties();
        sort( $properties );

        $lines = [];
        $lines[] = '== Details ==';
        $lines[] = '<div class="ss-section">';

        foreach ( $properties as $propertyName ) {
            $param = $this->propertyToParameter( $propertyName );
            $label = $this->propertyToLabel( $propertyName );

            $lines[] = '  {{#if:{{{' . $param . '|}}}|';
            $lines[] = '    <div class="ss-row">';
            $lines[] = '      <span class="ss-label">\'\'\'' . $label . ':\'\'\'</span>';
            $lines[] = '      <span class="ss-value">{{{' . $param . '}}}</span>';
            $lines[] = '    </div>';
            $lines[] = '  }}';
        }

        $lines[] = '</div>';
        $lines[] = '';

        return $lines;
    }

    /* =====================================================================
     * CREATION WRAPPERS
     * ===================================================================== */

    public function displayStubExists( string $categoryName ): bool {
        $title = $this->pageCreator
            ->makeTitle( $categoryName . '/display', NS_TEMPLATE );
        return $title && $this->pageCreator->pageExists( $title );
    }

    /**
     * Create the stub if missing (never overwrite existing display templates).
     *
     * @param CategoryModel $category
     * @return array{created:bool,message?:string,error?:string}
     */
    public function generateDisplayStubIfMissing( CategoryModel $category ): array {

        $name = $category->getName();

        if ( $this->displayStubExists( $name ) ) {
            return [
                'created' => false,
                'message' => 'Display template already exists; not overwriting.'
            ];
        }

        $content = $this->generateDisplayStub( $category );

        $title = $this->pageCreator->makeTitle(
            $name . '/display',
            NS_TEMPLATE
        );

        if ( !$title ) {
            return [
                'created' => false,
                'error' => 'Failed to create Title object.'
            ];
        }

        $summary = 'StructureSync: Initial display template stub (safe to edit)';
        $success = $this->pageCreator->createOrUpdatePage( $title, $content, $summary );

        if ( !$success ) {
            return [
                'created' => false,
                'error' => 'Failed to write display template.'
            ];
        }

        return [
            'created' => true,
            'message' => 'Display template stub created.'
        ];
    }

    /* =====================================================================
     * PROPERTY NAME HELPERS
     * ===================================================================== */

    /**
     * Convert property name → template parameter name (consistent with Forms + Templates).
     *
     * @param string $propertyName
     * @return string
     */
    private function propertyToParameter( string $propertyName ): string {

        // Remove "Has "
        $param = $propertyName;
        if ( str_starts_with( $param, 'Has ' ) ) {
            $param = substr( $param, 4 );
        }

        // Replace problematic characters
        $param = str_replace( ':', '_', $param );

        // Normalize
        $param = strtolower( trim( $param ) );
        $param = str_replace( ' ', '_', $param );

        return $param;
    }

    /**
     * Convert a property name into a human-readable label for display.
     *
     * @param string $propertyName
     * @return string
     */
    private function propertyToLabel( string $propertyName ): string {

        // Strip "Has "
        if ( str_starts_with( $propertyName, 'Has ' ) ) {
            return substr( $propertyName, 4 );
        }

        // If property is namespaced (Foaf:name) display "Foaf:name"
        return $propertyName;
    }
}
