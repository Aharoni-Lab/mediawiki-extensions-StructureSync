<?php

namespace MediaWiki\Extension\StructureSync\Schema;

/**
 * SchemaValidator
 * ----------------
 * Validates category + property definitions for StructureSync.
 *
 * Validates:
 *   - required top-level fields
 *   - category definitions (parents, properties, display, forms)
 *   - property definitions (datatype, allowed values, rangeCategory)
 *   - missing references (properties used by categories but not defined)
 *   - multi-parent inheritance
 *   - circular category dependencies
 */
class SchemaValidator {

	/**
	 * Validate entire schema
	 *
	 * @param array $schema
	 * @return array List of error messages
	 */
	public function validateSchema( array $schema ): array {
		$errors = [];

		// Basic structure
		if ( !isset( $schema['schemaVersion'] ) ) {
			$errors[] = 'Missing required field: schemaVersion';
		}

		if ( !isset( $schema['categories'] ) || !is_array( $schema['categories'] ) ) {
			$errors[] = 'Missing or invalid field: categories (must be array)';
			return $errors; // cannot continue
		}

		if ( !isset( $schema['properties'] ) || !is_array( $schema['properties'] ) ) {
			$errors[] = 'Missing or invalid field: properties (must be array)';
			return $errors;
		}

		$categories = $schema['categories'];
		$properties = $schema['properties'];

		// Validate category definitions
		foreach ( $categories as $categoryName => $categoryData ) {
			$errors = array_merge(
				$errors,
				$this->validateCategory( $categoryName, $categoryData, $categories, $properties )
			);
		}

		// Validate property definitions
		foreach ( $properties as $propertyName => $propertyData ) {
			$errors = array_merge(
				$errors,
				$this->validateProperty( $propertyName, $propertyData, $categories )
			);
		}

		// Detect inheritance cycles
		$errors = array_merge(
			$errors,
			$this->checkCircularDependencies( $categories )
		);

		return $errors;
	}


	/* ======================================================================
	 * CATEGORY VALIDATION
	 * ====================================================================== */

	private function validateCategory(
		string $categoryName,
		array $categoryData,
		array $allCategories,
		array $allProperties
	): array {
		$errors = [];

		// --- parents --------------------------------------------------------
		if ( isset( $categoryData['parents'] ) ) {
			if ( !is_array( $categoryData['parents'] ) ) {
				$errors[] = "Category '$categoryName': parents must be an array";
			} else {
				foreach ( $categoryData['parents'] as $parent ) {
					if ( !isset( $allCategories[$parent] ) ) {
						$errors[] =
							"Category '$categoryName': parent category '$parent' does not exist";
					}
				}
			}
		}

		// --- properties -----------------------------------------------------
		if ( isset( $categoryData['properties'] ) ) {
			$errors = array_merge(
				$errors,
				$this->validateCategoryProperties( $categoryName, $categoryData['properties'], $allProperties )
			);
		}

		// --- display config -----------------------------------------------
		if ( isset( $categoryData['display'] ) ) {
			$errors = array_merge(
				$errors,
				$this->validateDisplayConfig( $categoryName, $categoryData['display'], $allProperties )
			);
		}

		// --- form config ---------------------------------------------------
		if ( isset( $categoryData['forms'] ) ) {
			$errors = array_merge(
				$errors,
				$this->validateFormConfig( $categoryName, $categoryData['forms'], $allProperties )
			);
		}

		return $errors;
	}

	private function validateCategoryProperties(
		string $categoryName,
		array $propertyLists,
		array $allProperties
	): array {
		$errors = [];

		// Required
		if ( isset( $propertyLists['required'] ) ) {
			if ( !is_array( $propertyLists['required'] ) ) {
				$errors[] = "Category '$categoryName': properties.required must be an array";
			} else {
				foreach ( $propertyLists['required'] as $p ) {
					if ( !isset( $allProperties[$p] ) ) {
						$errors[] =
							"Category '$categoryName': required property '$p' does not exist";
					}
				}
			}
		}

		// Optional
		if ( isset( $propertyLists['optional'] ) ) {
			if ( !is_array( $propertyLists['optional'] ) ) {
				$errors[] = "Category '$categoryName': properties.optional must be an array";
			} else {
				foreach ( $propertyLists['optional'] as $p ) {
					if ( !isset( $allProperties[$p] ) ) {
						$errors[] =
							"Category '$categoryName': optional property '$p' does not exist";
					}
				}
			}
		}

		return $errors;
	}


	/* ======================================================================
	 * DISPLAY VALIDATION
	 * ====================================================================== */

	private function validateDisplayConfig(
		string $categoryName,
		array $config,
		array $allProperties
	): array {
		$errors = [];

		// header
		if ( isset( $config['header'] ) ) {
			if ( !is_array( $config['header'] ) ) {
				$errors[] = "Category '$categoryName': display.header must be an array";
			} else {
				foreach ( $config['header'] as $prop ) {
					if ( !isset( $allProperties[$prop] ) ) {
						$errors[] =
							"Category '$categoryName': display header property '$prop' does not exist";
					}
				}
			}
		}

		// sections
		if ( isset( $config['sections'] ) ) {
			if ( !is_array( $config['sections'] ) ) {
				$errors[] = "Category '$categoryName': display.sections must be an array";
			} else {
				foreach ( $config['sections'] as $i => $section ) {
					if ( !isset( $section['name'] ) ) {
						$errors[] = "Category '$categoryName': display.sections[$i] missing 'name'";
					}

					if ( isset( $section['properties'] ) ) {
						if ( !is_array( $section['properties'] ) ) {
							$errors[] =
								"Category '$categoryName': display.sections[$i].properties must be array";
						} else {
							foreach ( $section['properties'] as $prop ) {
								if ( !isset( $allProperties[$prop] ) ) {
									$errors[] =
										"Category '$categoryName': display section property '$prop' does not exist";
								}
							}
						}
					}
				}
			}
		}

		return $errors;
	}


	/* ======================================================================
	 * FORM VALIDATION
	 * ====================================================================== */

	private function validateFormConfig(
		string $categoryName,
		array $config,
		array $allProperties
	): array {
		$errors = [];

		if ( isset( $config['sections'] ) ) {
			if ( !is_array( $config['sections'] ) ) {
				$errors[] = "Category '$categoryName': forms.sections must be an array";
			} else {
				foreach ( $config['sections'] as $i => $section ) {

					if ( !isset( $section['name'] ) ) {
						$errors[] = "Category '$categoryName': forms.sections[$i] missing 'name'";
					}

					if ( isset( $section['properties'] ) ) {
						if ( !is_array( $section['properties'] ) ) {
							$errors[] =
								"Category '$categoryName': forms.sections[$i].properties must be array";
						} else {
							foreach ( $section['properties'] as $prop ) {
								if ( !isset( $allProperties[$prop] ) ) {
									$errors[] =
										"Category '$categoryName': form section property '$prop' does not exist";
								}
							}
						}
					}
				}
			}
		}

		return $errors;
	}


	/* ======================================================================
	 * PROPERTY VALIDATION
	 * ====================================================================== */

	private function validateProperty(
		string $propertyName,
		array $propertyData,
		array $allCategories
	): array {
		$errors = [];

		// datatype required
		if ( !isset( $propertyData['datatype'] ) ) {
			$errors[] = "Property '$propertyName': missing datatype";
		}

		// allowedValues must be array
		if ( isset( $propertyData['allowedValues'] ) &&
			!is_array( $propertyData['allowedValues'] ) ) {

			$errors[] = "Property '$propertyName': allowedValues must be an array";
		}

		// rangeCategory must exist
		if ( isset( $propertyData['rangeCategory'] ) ) {
			$range = $propertyData['rangeCategory'];
			if ( !isset( $allCategories[$range] ) ) {
				$errors[] =
					"Property '$propertyName': rangeCategory '$range' does not exist";
			}
		}

		return $errors;
	}


	/* ======================================================================
	 * CIRCULAR DEPENDENCY DETECTION
	 * ====================================================================== */

	private function checkCircularDependencies( array $categories ): array {
		$categoryModels = [];

		foreach ( $categories as $name => $data ) {
			$categoryModels[$name] = new CategoryModel( $name, $data );
		}

		$resolver = new InheritanceResolver( $categoryModels );
		return $resolver->validateInheritance();
	}


	/* ======================================================================
	 * WARNINGS (non-fatal)
	 * ====================================================================== */

	public function generateWarnings( array $schema ): array {
		$warnings = [];

		if ( !isset( $schema['categories'], $schema['properties'] ) ) {
			return $warnings;
		}

		$categories = $schema['categories'];
		$properties = $schema['properties'];

		// Category warnings
		foreach ( $categories as $name => $data ) {
			$req = $data['properties']['required'] ?? [];
			$opt = $data['properties']['optional'] ?? [];

			if ( empty( $req ) && empty( $opt ) ) {
				$warnings[] = "Category '$name': no properties defined";
			}

			if ( empty( $data['display'] ?? [] ) ) {
				$warnings[] = "Category '$name': missing display configuration";
			}

			if ( empty( $data['forms'] ?? [] ) ) {
				$warnings[] = "Category '$name': missing form configuration";
			}
		}

		// Unused property warnings
		$used = [];
		foreach ( $categories as $cat ) {
			$used = array_merge(
				$used,
				$cat['properties']['required'] ?? [],
				$cat['properties']['optional'] ?? []
			);
		}
		$used = array_unique( $used );

		foreach ( array_keys( $properties ) as $p ) {
			if ( !in_array( $p, $used, true ) ) {
				$warnings[] = "Property '$p': not used by any category";
			}
		}

		return $warnings;
	}
}
