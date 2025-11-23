<?php

namespace MediaWiki\Extension\StructureSync\Generator;

use MediaWiki\Extension\StructureSync\Schema\PropertyModel;

/**
 * PropertyInputMapper
 * --------------------
 * Converts SMW property metadata into PageForms input definitions.
 *
 * Responsibilities:
 *   - Map SMW datatype → PageForms input type
 *   - Add PageForms parameters (values, size, ranges, etc.)
 *   - Produce syntactically valid PF input strings
 *   - Support required/optional fields based on CategoryModel requirements
 */
class PropertyInputMapper {

    /* =====================================================================
     * PageForms INPUT TYPE RESOLUTION
     * ===================================================================== */

    /**
     * Determine PageForms input type for a given property.
     *
     * @param PropertyModel $property
     * @return string
     */
    public function getInputType( PropertyModel $property ): string {

        $datatype = $property->getDatatype();

        // Hard mapping based on SMW datatypes
        static $map = [
            'Text'                  => 'text',
            'URL'                   => 'text',
            'Email'                 => 'text',
            'Telephone number'      => 'text',
            'Number'                => 'number',
            'Quantity'              => 'text',  // Could convert to number later
            'Temperature'           => 'number',
            'Date'                  => 'datepicker',
            'Boolean'               => 'checkbox',
            'Code'                  => 'textarea',
            'Geographic coordinate' => 'text',
        ];

        // Special cases override defaults
        // Priority order: enum values → autocomplete sources → Page-type → defaults

        // 1. Dropdown enum (highest priority)
        if ( $property->hasAllowedValues() ) {
            return 'dropdown';
        }

        // 2. Autocomplete from category/namespace
        if ( $property->shouldAutocomplete() ) {
            return 'combobox';
        }

        // 3. Page-type reference (lookup/autocomplete)
        if ( $property->isPageType() ) {

            // If restricted to a category (range), use "combobox"
            if ( $property->getRangeCategory() !== null ) {
                return 'combobox';
            }

            // Else, still a Page → PageForms combobox
            return 'combobox';
        }

        // 4. Fallback to datatype mapping
        return $map[$datatype] ?? 'text';
    }

    /* =====================================================================
     * ADDITIONAL INPUT PARAMETERS
     * ===================================================================== */

    /**
     * Return PageForms input parameters for the property (except mandatory).
     *
     * @param PropertyModel $property
     * @return array<string,string>
     */
    public function getInputParameters( PropertyModel $property ): array {

        $params = [];
        $datatype = $property->getDatatype();

        /* ------------------------------------------------------------------
         * TEXT-LIKE FIELDS
         * ------------------------------------------------------------------ */
        if ( in_array( $datatype, [ 'Text', 'Email', 'URL', 'Telephone number' ] ) ) {
            $params['size'] = '60';
        }

        /* ------------------------------------------------------------------
         * TEXTAREA (code blocks)
         * ------------------------------------------------------------------ */
        if ( $datatype === 'Code' ) {
            $params['rows'] = '10';
            $params['cols'] = '80';
        }

        /* ------------------------------------------------------------------
         * ENUMERATED VALUES (highest priority)
         * ------------------------------------------------------------------ */
        if ( $property->hasAllowedValues() ) {
            // PageForms expects comma-separated list with NO SPACES
            $params['values'] = implode( ',', array_map( 'trim', $property->getAllowedValues() ) );
        }

        /* ------------------------------------------------------------------
         * AUTOCOMPLETE SOURCES (category/namespace)
         * ------------------------------------------------------------------ */
        elseif ( $property->shouldAutocomplete() ) {
            // Autocomplete from category
            if ( $property->getAllowedCategory() !== null ) {
                $params['values from category'] = $property->getAllowedCategory();
                $params['autocomplete'] = 'on';
            }
            // Autocomplete from namespace
            elseif ( $property->getAllowedNamespace() !== null ) {
                $params['values from namespace'] = $property->getAllowedNamespace();
                $params['autocomplete'] = 'on';
            }
        }

        /* ------------------------------------------------------------------
         * PAGE / COMBOBOX LOOKUPS (Page-type with rangeCategory)
         * ------------------------------------------------------------------ */
        elseif ( $property->isPageType() && $property->getRangeCategory() !== null ) {
            $params['values from category'] = $property->getRangeCategory();
            $params['autocomplete'] = 'on';
        }

        /* ------------------------------------------------------------------
         * BOOLEAN OVERRIDES
         * ------------------------------------------------------------------ */
        if ( $datatype === 'Boolean' ) {
            // No additional params needed; PF checkbox is simple
        }

        return $params;
    }

    /* =====================================================================
     * GENERATE INPUT STRING
     * ===================================================================== */

    /**
     * Build the PageForms input definition string.
     *
     * @param PropertyModel $property
     * @param bool $isMandatory Whether the category requires this property
     * @return string PageForms input definition
     */
    public function generateInputDefinition( PropertyModel $property, bool $isMandatory = false ): string {

        $inputType = $this->getInputType( $property );
        $params = $this->getInputParameters( $property );

        // Only set mandatory parameter if field is required
        // For optional fields, omit the parameter entirely
        if ( $isMandatory ) {
            $params['mandatory'] = 'true';
        }

        // Build "key=value" segments
        $paramText = '';
        foreach ( $params as $key => $value ) {
            // Avoid empty or null parameters
            if ( $value === '' || $value === null ) {
                continue;
            }
            $paramText .= "|$key=$value";
        }

        return "input type=$inputType$paramText";
    }
}
