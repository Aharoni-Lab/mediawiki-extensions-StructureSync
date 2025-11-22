<?php

namespace MediaWiki\Extension\StructureSync\Schema;

/**
 * Immutable value object representing a property's schema metadata
 */
class PropertyModel {

	/** @var string */
	private $name;

	/** @var string */
	private $datatype;

	/** @var string */
	private $label;

	/** @var string */
	private $description;

	/** @var string[] */
	private $allowedValues;

	/** @var string|null */
	private $rangeCategory;

	/** @var string|null */
	private $subpropertyOf;

	/**
	 * @param string $name Property name (without namespace)
	 * @param array $data Associative array with property data
	 */
	public function __construct( string $name, array $data = [] ) {
		$this->name = $name;
		$this->datatype = $data['datatype'] ?? 'Text';
		$this->label = $data['label'] ?? $name;
		$this->description = $data['description'] ?? '';
		$this->allowedValues = $data['allowedValues'] ?? [];
		$this->rangeCategory = $data['rangeCategory'] ?? null;
		$this->subpropertyOf = $data['subpropertyOf'] ?? null;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getDatatype(): string {
		return $this->datatype;
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
	public function getAllowedValues(): array {
		return $this->allowedValues;
	}

	/**
	 * @return string|null
	 */
	public function getRangeCategory(): ?string {
		return $this->rangeCategory;
	}

	/**
	 * @return string|null
	 */
	public function getSubpropertyOf(): ?string {
		return $this->subpropertyOf;
	}

	/**
	 * Check if this property has allowed values
	 *
	 * @return bool
	 */
	public function hasAllowedValues(): bool {
		return !empty( $this->allowedValues );
	}

	/**
	 * Check if this property is a Page type
	 *
	 * @return bool
	 */
	public function isPageType(): bool {
		return $this->datatype === 'Page';
	}

	/**
	 * Convert to array representation suitable for schema export
	 *
	 * @return array
	 */
	public function toArray(): array {
		$data = [
			'datatype' => $this->datatype,
			'label' => $this->label,
			'description' => $this->description,
		];

		if ( !empty( $this->allowedValues ) ) {
			$data['allowedValues'] = $this->allowedValues;
		}

		if ( $this->rangeCategory !== null ) {
			$data['rangeCategory'] = $this->rangeCategory;
		}

		if ( $this->subpropertyOf !== null ) {
			$data['subpropertyOf'] = $this->subpropertyOf;
		}

		return $data;
	}

	/**
	 * Get the SMW type string for this property
	 *
	 * @return string
	 */
	public function getSMWType(): string {
		// Map common datatypes to SMW type strings
		$typeMap = [
			'Text' => 'Text',
			'Page' => 'Page',
			'Date' => 'Date',
			'Number' => 'Number',
			'Email' => 'Email',
			'URL' => 'URL',
			'Boolean' => 'Boolean',
			'Code' => 'Code',
			'Geographic coordinate' => 'Geographic coordinate',
			'Quantity' => 'Quantity',
			'Temperature' => 'Temperature',
			'Telephone number' => 'Telephone number',
		];

		return $typeMap[$this->datatype] ?? 'Text';
	}
}

