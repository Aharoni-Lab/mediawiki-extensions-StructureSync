<?php

namespace MediaWiki\Extension\StructureSync\Generator;

use MediaWiki\Extension\StructureSync\Schema\PropertyModel;

/**
 * Maps property datatypes to PageForms input types
 */
class PropertyInputMapper {

	/**
	 * Get the PageForms input type for a property
	 *
	 * @param PropertyModel $property
	 * @return string Input type for PageForms
	 */
	public function getInputType( PropertyModel $property ): string {
		$datatype = $property->getDatatype();

		// Map SMW datatypes to PageForms input types
		$typeMap = [
			'Text' => 'text',
			'Page' => 'combobox',
			'Date' => 'datepicker',
			'Number' => 'text',
			'Email' => 'text',
			'URL' => 'text',
			'Boolean' => 'checkbox',
			'Code' => 'textarea',
			'Geographic coordinate' => 'text',
			'Quantity' => 'text',
			'Temperature' => 'text',
			'Telephone number' => 'text',
		];

		// Check for special cases
		if ( $property->hasAllowedValues() ) {
			return 'dropdown';
		}

		if ( $property->isPageType() && $property->getRangeCategory() !== null ) {
			return 'combobox';
		}

		return $typeMap[$datatype] ?? 'text';
	}

	/**
	 * Get additional parameters for the input type
	 *
	 * @param PropertyModel $property
	 * @return array
	 */
	public function getInputParameters( PropertyModel $property ): array {
		$params = [];

		// Size for text inputs
		$datatype = $property->getDatatype();
		if ( in_array( $datatype, [ 'Text', 'Email', 'URL' ] ) ) {
			$params['size'] = '50';
		}

		// Rows/cols for textarea
		if ( $datatype === 'Code' ) {
			$params['rows'] = '10';
			$params['cols'] = '80';
		}

		// Values for dropdown
		if ( $property->hasAllowedValues() ) {
			$params['values'] = implode( ',', $property->getAllowedValues() );
		}

		// Category for combobox (Page type with range)
		if ( $property->isPageType() && $property->getRangeCategory() !== null ) {
			$params['values from category'] = $property->getRangeCategory();
		}

		// Mandatory indicator
		$params['mandatory'] = 'false'; // Will be set per-category in form generation

		return $params;
	}

	/**
	 * Generate input definition for PageForms
	 *
	 * @param PropertyModel $property
	 * @param bool $isMandatory
	 * @return string
	 */
	public function generateInputDefinition( PropertyModel $property, bool $isMandatory = false ): string {
		$inputType = $this->getInputType( $property );
		$params = $this->getInputParameters( $property );

		// Override mandatory parameter
		if ( $isMandatory ) {
			$params['mandatory'] = 'true';
		}

		// Build parameter string
		$paramParts = [];
		foreach ( $params as $key => $value ) {
			$paramParts[] = "$key=$value";
		}

		$paramString = '';
		if ( !empty( $paramParts ) ) {
			$paramString = '|' . implode( '|', $paramParts );
		}

		return "input type=$inputType$paramString";
	}
}

