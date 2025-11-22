<?php

namespace MediaWiki\Extension\StructureSync\Schema;

/**
 * Validates schema structure and consistency
 */
class SchemaValidator {

	/**
	 * Validate schema array structure and content
	 *
	 * @param array $schema
	 * @return array Array of errors (empty if valid)
	 */
	public function validateSchema( array $schema ): array {
		$errors = [];

		// Check required top-level keys
		if ( !isset( $schema['schemaVersion'] ) ) {
			$errors[] = 'Missing required field: schemaVersion';
		}

		if ( !isset( $schema['categories'] ) || !is_array( $schema['categories'] ) ) {
			$errors[] = 'Missing or invalid field: categories (must be array)';
			// Can't continue without categories
			return $errors;
		}

		if ( !isset( $schema['properties'] ) || !is_array( $schema['properties'] ) ) {
			$errors[] = 'Missing or invalid field: properties (must be array)';
		}

		$categories = $schema['categories'];
		$properties = $schema['properties'];

		// Validate each category
		foreach ( $categories as $categoryName => $categoryData ) {
			$errors = array_merge( $errors, $this->validateCategory( $categoryName, $categoryData, $categories, $properties ) );
		}

		// Validate each property
		foreach ( $properties as $propertyName => $propertyData ) {
			$errors = array_merge( $errors, $this->validateProperty( $propertyName, $propertyData, $categories ) );
		}

		// Check for circular dependencies
		$errors = array_merge( $errors, $this->checkCircularDependencies( $categories ) );

		return $errors;
	}

	/**
	 * Validate a single category
	 *
	 * @param string $categoryName
	 * @param array $categoryData
	 * @param array $allCategories
	 * @param array $allProperties
	 * @return array Errors found
	 */
	private function validateCategory( string $categoryName, array $categoryData, array $allCategories, array $allProperties ): array {
		$errors = [];

		// Check parents exist
		if ( isset( $categoryData['parents'] ) ) {
			if ( !is_array( $categoryData['parents'] ) ) {
				$errors[] = "Category '$categoryName': parents must be an array";
			} else {
				foreach ( $categoryData['parents'] as $parent ) {
					if ( !isset( $allCategories[$parent] ) ) {
						$errors[] = "Category '$categoryName': parent category '$parent' does not exist";
					}
				}
			}
		}

		// Check properties exist
		if ( isset( $categoryData['properties'] ) ) {
			if ( !is_array( $categoryData['properties'] ) ) {
				$errors[] = "Category '$categoryName': properties must be an array";
			} else {
				// Check required properties
				if ( isset( $categoryData['properties']['required'] ) ) {
					if ( !is_array( $categoryData['properties']['required'] ) ) {
						$errors[] = "Category '$categoryName': properties.required must be an array";
					} else {
						foreach ( $categoryData['properties']['required'] as $prop ) {
							if ( !isset( $allProperties[$prop] ) ) {
								$errors[] = "Category '$categoryName': required property '$prop' does not exist";
							}
						}
					}
				}

				// Check optional properties
				if ( isset( $categoryData['properties']['optional'] ) ) {
					if ( !is_array( $categoryData['properties']['optional'] ) ) {
						$errors[] = "Category '$categoryName': properties.optional must be an array";
					} else {
						foreach ( $categoryData['properties']['optional'] as $prop ) {
							if ( !isset( $allProperties[$prop] ) ) {
								$errors[] = "Category '$categoryName': optional property '$prop' does not exist";
							}
						}
					}
				}
			}
		}

		// Validate display config
		if ( isset( $categoryData['display'] ) ) {
			$errors = array_merge( $errors, $this->validateDisplayConfig( $categoryName, $categoryData['display'], $allProperties ) );
		}

		// Validate form config
		if ( isset( $categoryData['forms'] ) ) {
			$errors = array_merge( $errors, $this->validateFormConfig( $categoryName, $categoryData['forms'], $allProperties ) );
		}

		return $errors;
	}

	/**
	 * Validate display configuration
	 *
	 * @param string $categoryName
	 * @param array $displayConfig
	 * @param array $allProperties
	 * @return array Errors found
	 */
	private function validateDisplayConfig( string $categoryName, array $displayConfig, array $allProperties ): array {
		$errors = [];

		// Validate header properties
		if ( isset( $displayConfig['header'] ) ) {
			if ( !is_array( $displayConfig['header'] ) ) {
				$errors[] = "Category '$categoryName': display.header must be an array";
			} else {
				foreach ( $displayConfig['header'] as $prop ) {
					if ( !isset( $allProperties[$prop] ) ) {
						$errors[] = "Category '$categoryName': display header property '$prop' does not exist";
					}
				}
			}
		}

		// Validate sections
		if ( isset( $displayConfig['sections'] ) ) {
			if ( !is_array( $displayConfig['sections'] ) ) {
				$errors[] = "Category '$categoryName': display.sections must be an array";
			} else {
				foreach ( $displayConfig['sections'] as $idx => $section ) {
					if ( !is_array( $section ) ) {
						$errors[] = "Category '$categoryName': display.sections[$idx] must be an array";
						continue;
					}

					if ( !isset( $section['name'] ) ) {
						$errors[] = "Category '$categoryName': display.sections[$idx] missing 'name'";
					}

					if ( isset( $section['properties'] ) ) {
						if ( !is_array( $section['properties'] ) ) {
							$errors[] = "Category '$categoryName': display.sections[$idx].properties must be an array";
						} else {
							foreach ( $section['properties'] as $prop ) {
								if ( !isset( $allProperties[$prop] ) ) {
									$errors[] = "Category '$categoryName': display section property '$prop' does not exist";
								}
							}
						}
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate form configuration
	 *
	 * @param string $categoryName
	 * @param array $formConfig
	 * @param array $allProperties
	 * @return array Errors found
	 */
	private function validateFormConfig( string $categoryName, array $formConfig, array $allProperties ): array {
		$errors = [];

		// Validate sections
		if ( isset( $formConfig['sections'] ) ) {
			if ( !is_array( $formConfig['sections'] ) ) {
				$errors[] = "Category '$categoryName': forms.sections must be an array";
			} else {
				foreach ( $formConfig['sections'] as $idx => $section ) {
					if ( !is_array( $section ) ) {
						$errors[] = "Category '$categoryName': forms.sections[$idx] must be an array";
						continue;
					}

					if ( !isset( $section['name'] ) ) {
						$errors[] = "Category '$categoryName': forms.sections[$idx] missing 'name'";
					}

					if ( isset( $section['properties'] ) ) {
						if ( !is_array( $section['properties'] ) ) {
							$errors[] = "Category '$categoryName': forms.sections[$idx].properties must be an array";
						} else {
							foreach ( $section['properties'] as $prop ) {
								if ( !isset( $allProperties[$prop] ) ) {
									$errors[] = "Category '$categoryName': form section property '$prop' does not exist";
								}
							}
						}
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate a single property
	 *
	 * @param string $propertyName
	 * @param array $propertyData
	 * @param array $allCategories
	 * @return array Errors found
	 */
	private function validateProperty( string $propertyName, array $propertyData, array $allCategories ): array {
		$errors = [];

		// Check datatype is present
		if ( !isset( $propertyData['datatype'] ) ) {
			$errors[] = "Property '$propertyName': missing datatype";
		}

		// Check rangeCategory exists if specified
		if ( isset( $propertyData['rangeCategory'] ) ) {
			$rangeCategory = $propertyData['rangeCategory'];
			if ( !isset( $allCategories[$rangeCategory] ) ) {
				$errors[] = "Property '$propertyName': rangeCategory '$rangeCategory' does not exist";
			}
		}

		// Check allowedValues is an array if specified
		if ( isset( $propertyData['allowedValues'] ) && !is_array( $propertyData['allowedValues'] ) ) {
			$errors[] = "Property '$propertyName': allowedValues must be an array";
		}

		return $errors;
	}

	/**
	 * Check for circular dependencies in category hierarchy
	 *
	 * @param array $categories
	 * @return array Errors found
	 */
	private function checkCircularDependencies( array $categories ): array {
		$errors = [];

		// Build category models
		$categoryMap = [];
		foreach ( $categories as $name => $data ) {
			$categoryMap[$name] = new CategoryModel( $name, $data );
		}

		// Use InheritanceResolver to detect cycles
		$resolver = new InheritanceResolver( $categoryMap );
		$inheritanceErrors = $resolver->validateInheritance();

		return array_merge( $errors, $inheritanceErrors );
	}

	/**
	 * Generate warnings (non-fatal issues)
	 *
	 * @param array $schema
	 * @return array Array of warning messages
	 */
	public function generateWarnings( array $schema ): array {
		$warnings = [];

		if ( !isset( $schema['categories'] ) || !is_array( $schema['categories'] ) ) {
			return $warnings;
		}

		$categories = $schema['categories'];
		$properties = $schema['properties'] ?? [];

		foreach ( $categories as $categoryName => $categoryData ) {
			// Warn if no properties defined
			$hasRequired = !empty( $categoryData['properties']['required'] ?? [] );
			$hasOptional = !empty( $categoryData['properties']['optional'] ?? [] );

			if ( !$hasRequired && !$hasOptional ) {
				$warnings[] = "Category '$categoryName': no properties defined";
			}

			// Warn if display config is missing
			if ( empty( $categoryData['display'] ) ) {
				$warnings[] = "Category '$categoryName': no display configuration";
			}

			// Warn if form config is missing
			if ( empty( $categoryData['forms'] ) ) {
				$warnings[] = "Category '$categoryName': no form configuration";
			}
		}

		// Warn about unused properties
		$usedProperties = [];
		foreach ( $categories as $categoryData ) {
			if ( isset( $categoryData['properties']['required'] ) ) {
				$usedProperties = array_merge( $usedProperties, $categoryData['properties']['required'] );
			}
			if ( isset( $categoryData['properties']['optional'] ) ) {
				$usedProperties = array_merge( $usedProperties, $categoryData['properties']['optional'] );
			}
		}
		$usedProperties = array_unique( $usedProperties );

		foreach ( array_keys( $properties ) as $propertyName ) {
			if ( !in_array( $propertyName, $usedProperties ) ) {
				$warnings[] = "Property '$propertyName': not used by any category";
			}
		}

		return $warnings;
	}
}

