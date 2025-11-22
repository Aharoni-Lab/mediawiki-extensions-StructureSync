<?php

namespace MediaWiki\Extension\StructureSync\Schema;

use Symfony\Component\Yaml\Yaml;

/**
 * SchemaLoader
 * -------------
 * Responsible for loading and saving StructureSync schema definitions
 * from JSON/YAML strings or files.
 *
 * Features:
 *   - Strict JSON + YAML parsing with clear error messages
 *   - Auto-format detection (JSON vs YAML)
 *   - Safe file I/O with consistent exceptions
 *   - Pretty JSON and YAML output for readability
 *   - Minimal structural validation helpers
 */
class SchemaLoader {

	/**
	 * Load schema from a JSON string
	 *
	 * @param string $json
	 * @return array
	 * @throws \RuntimeException
	 */
	public function loadFromJson( string $json ): array {
		if ( trim( $json ) === '' ) {
			throw new \RuntimeException( 'Empty JSON content' );
		}

		$data = json_decode( $json, true );

		if ( $data === null && json_last_error() !== JSON_ERROR_NONE ) {
			throw new \RuntimeException( 'Invalid JSON: ' . json_last_error_msg() );
		}

		if ( !is_array( $data ) ) {
			throw new \RuntimeException( 'JSON did not decode to an array' );
		}

		return $data;
	}

	/**
	 * Load schema from a YAML string
	 *
	 * @param string $yaml
	 * @return array
	 * @throws \RuntimeException
	 */
	public function loadFromYaml( string $yaml ): array {
		if ( trim( $yaml ) === '' ) {
			throw new \RuntimeException( 'Empty YAML content' );
		}

		try {
			$data = Yaml::parse( $yaml );
		} catch ( \Exception $e ) {
			throw new \RuntimeException( 'Invalid YAML: ' . $e->getMessage() );
		}

		if ( !is_array( $data ) ) {
			throw new \RuntimeException( 'YAML did not parse to an array' );
		}

		return $data;
	}

	/**
	 * Write schema to JSON
	 */
	public function saveToJson( array $schema ): string {
		return json_encode(
			$schema,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) ?: '{}';
	}

	/**
	 * Write schema to YAML
	 */
	public function saveToYaml( array $schema ): string {
		// depth 4, indent 2 – readable for humans
		return Yaml::dump( $schema, 4, 2 );
	}

	/**
	 * Auto-detect based on file extension and parse
	 *
	 * @param string $filePath
	 * @return array
	 * @throws \RuntimeException
	 */
	public function loadFromFile( string $filePath ): array {
		if ( !is_file( $filePath ) ) {
			throw new \RuntimeException( "File not found: $filePath" );
		}

		$content = file_get_contents( $filePath );
		if ( $content === false ) {
			throw new \RuntimeException( "Cannot read file: $filePath" );
		}

		$ext = strtolower( pathinfo( $filePath, PATHINFO_EXTENSION ) );

		if ( $ext === 'json' ) {
			return $this->loadFromJson( $content );
		}

		if ( $ext === 'yaml' || $ext === 'yml' ) {
			return $this->loadFromYaml( $content );
		}

		// Try JSON first, then YAML
		try {
			return $this->loadFromJson( $content );
		} catch ( \RuntimeException $e ) {
			return $this->loadFromYaml( $content );
		}
	}

	/**
	 * Save to file
	 *
	 * @param array $schema
	 * @param string $filePath
	 * @return bool
	 * @throws \RuntimeException
	 */
	public function saveToFile( array $schema, string $filePath ): bool {
		$ext = strtolower( pathinfo( $filePath, PATHINFO_EXTENSION ) );

		if ( $ext === 'json' ) {
			$content = $this->saveToJson( $schema );
		} elseif ( $ext === 'yaml' || $ext === 'yml' ) {
			$content = $this->saveToYaml( $schema );
		} else {
			// Default to JSON
			$content = $this->saveToJson( $schema );
		}

		$ok = file_put_contents( $filePath, $content );

		if ( $ok === false ) {
			throw new \RuntimeException( "Failed to write file: $filePath" );
		}

		return true;
	}

	/**
	 * Detect content format from the first non-whitespace character
	 *
	 * @param string $content
	 * @return string 'json' or 'yaml'
	 */
	public function detectFormat( string $content ): string {
		$content = ltrim( $content );

		if ( $content === '' ) {
			// ambiguous but treat empty as YAML (JSON empty isn't valid)
			return 'yaml';
		}

		$firstChar = $content[0];

		// JSON objects/arrays begin with { or [
		if ( $firstChar === '{' || $firstChar === '[' ) {
			return 'json';
		}

		// YAML is the fallback
		return 'yaml';
	}

	/**
	 * Load from content with format auto-detection
	 */
	public function loadFromContent( string $content ): array {
		$format = $this->detectFormat( $content );

		if ( $format === 'json' ) {
			// On failure, try YAML to allow minimal ambiguity handling
			try {
				return $this->loadFromJson( $content );
			} catch ( \RuntimeException $e ) {
				return $this->loadFromYaml( $content );
			}
		}

		return $this->loadFromYaml( $content );
	}

	/**
	 * Return an empty valid schema structure
	 */
	public function createEmptySchema(): array {
		return [
			'schemaVersion' => '1.0',
			'categories'    => [],
			'properties'    => [],
		];
	}

	/**
	 * Minimal structural check. Full validation happens in SchemaValidator.
	 */
	public function hasValidStructure( array $schema ): bool {
		if ( !is_array( $schema ) ) {
			return false;
		}

		return isset( $schema['schemaVersion'] )
			&& array_key_exists( 'categories', $schema )
			&& array_key_exists( 'properties', $schema )
			&& is_array( $schema['categories'] )
			&& is_array( $schema['properties'] );
	}
}
