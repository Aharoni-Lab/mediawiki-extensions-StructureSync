<?php

namespace MediaWiki\Extension\StructureSync\Schema;

/**
 * Immutable value object representing a category's schema metadata
 */
class CategoryModel {

	/** @var string */
	private $name;

	/** @var string[] */
	private $parents;

	/** @var string */
	private $label;

	/** @var string */
	private $description;

	/** @var string[] */
	private $requiredProperties;

	/** @var string[] */
	private $optionalProperties;

	/** @var array */
	private $displayConfig;

	/** @var array */
	private $formConfig;

	/**
	 * @param string $name Category name (without namespace)
	 * @param array $data Associative array with category data
	 */
	public function __construct( string $name, array $data = [] ) {
		$this->name = $name;
		$this->parents = $data['parents'] ?? [];
		$this->label = $data['label'] ?? $name;
		$this->description = $data['description'] ?? '';
		$this->requiredProperties = $data['properties']['required'] ?? [];
		$this->optionalProperties = $data['properties']['optional'] ?? [];
		$this->displayConfig = $data['display'] ?? [];
		$this->formConfig = $data['forms'] ?? [];
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string[]
	 */
	public function getParents(): array {
		return $this->parents;
	}

	/**
	 * @return string
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @return string[]
	 */
	public function getRequiredProperties(): array {
		return $this->requiredProperties;
	}

	/**
	 * @return string[]
	 */
	public function getOptionalProperties(): array {
		return $this->optionalProperties;
	}

	/**
	 * @return string[]
	 */
	public function getAllProperties(): array {
		return array_merge( $this->requiredProperties, $this->optionalProperties );
	}

	/**
	 * @return array
	 */
	public function getDisplayConfig(): array {
		return $this->displayConfig;
	}

	/**
	 * @return array
	 */
	public function getFormConfig(): array {
		return $this->formConfig;
	}

	/**
	 * Get display header properties
	 *
	 * @return string[]
	 */
	public function getDisplayHeaderProperties(): array {
		return $this->displayConfig['header'] ?? [];
	}

	/**
	 * Get display sections
	 *
	 * @return array Array of sections with 'name' and 'properties'
	 */
	public function getDisplaySections(): array {
		return $this->displayConfig['sections'] ?? [];
	}

	/**
	 * Get form sections
	 *
	 * @return array Array of sections with 'name' and 'properties'
	 */
	public function getFormSections(): array {
		return $this->formConfig['sections'] ?? [];
	}

	/**
	 * Check if this category has a specific parent
	 *
	 * @param string $parentName
	 * @return bool
	 */
	public function hasParent( string $parentName ): bool {
		return in_array( $parentName, $this->parents );
	}

	/**
	 * Check if a property is required
	 *
	 * @param string $propertyName
	 * @return bool
	 */
	public function isPropertyRequired( string $propertyName ): bool {
		return in_array( $propertyName, $this->requiredProperties );
	}

	/**
	 * Check if a property is optional
	 *
	 * @param string $propertyName
	 * @return bool
	 */
	public function isPropertyOptional( string $propertyName ): bool {
		return in_array( $propertyName, $this->optionalProperties );
	}

	/**
	 * Convert to array representation suitable for schema export
	 *
	 * @return array
	 */
	public function toArray(): array {
		$data = [
			'parents' => $this->parents,
			'label' => $this->label,
			'description' => $this->description,
			'properties' => [
				'required' => $this->requiredProperties,
				'optional' => $this->optionalProperties,
			],
		];

		if ( !empty( $this->displayConfig ) ) {
			$data['display'] = $this->displayConfig;
		}

		if ( !empty( $this->formConfig ) ) {
			$data['forms'] = $this->formConfig;
		}

		return $data;
	}

	/**
	 * Create a new CategoryModel with merged properties from parent
	 *
	 * @param CategoryModel $parent
	 * @return CategoryModel
	 */
	public function mergeWithParent( CategoryModel $parent ): CategoryModel {
		// Merge required properties (union)
		$mergedRequired = array_unique( array_merge(
			$parent->getRequiredProperties(),
			$this->requiredProperties
		) );

		// Merge optional properties (union, but remove if in required)
		$mergedOptional = array_unique( array_merge(
			$parent->getOptionalProperties(),
			$this->optionalProperties
		) );
		$mergedOptional = array_diff( $mergedOptional, $mergedRequired );

		// Child display/form config takes precedence
		$mergedDisplay = !empty( $this->displayConfig ) ? $this->displayConfig : $parent->getDisplayConfig();
		$mergedForm = !empty( $this->formConfig ) ? $this->formConfig : $parent->getFormConfig();

		return new self( $this->name, [
			'parents' => $this->parents,
			'label' => $this->label,
			'description' => $this->description,
			'properties' => [
				'required' => array_values( $mergedRequired ),
				'optional' => array_values( $mergedOptional ),
			],
			'display' => $mergedDisplay,
			'forms' => $mergedForm,
		] );
	}
}

