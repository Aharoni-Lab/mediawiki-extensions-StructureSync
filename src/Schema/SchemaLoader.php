<?php

namespace MediaWiki\Extension\StructureSync\Schema;

use Symfony\Component\Yaml\Yaml;

/**
 * Handles loading and saving schema from/to JSON and YAML formats
 */
class SchemaLoader {

	/**
	 * Load schema from JSON string
	 *
	 * @param string $json
	 * @return array
	 * @throws \RuntimeException If JSON is invalid
	 */
	public function loadFromJson( string $json ): array {
		$data = json_decode( $json, true );

		if ( $data === null && json_last_error() !== JSON_ERROR_NONE ) {
			throw new \RuntimeException( 'Invalid JSON: ' . json_last_error_msg() );
		}

		return $data;
	}

	/**
	 * Load schema from YAML string
	 *
	 * @param string $yaml
	 * @return array
	 * @throws \RuntimeException If YAML is invalid
	 */
	public function loadFromYaml( string $yaml ): array {
		try {
			$data = Yaml::parse( $yaml );
			return $data ?? [];
		} catch ( \Exception $e ) {
			throw new \RuntimeException( 'Invalid YAML: ' . $e->getMessage() );
		}
	}

	/**
	 * Save schema to JSON string (pretty-printed)
	 *
	 * @param array $schema
	 * @return string
	 */
	public function saveToJson( array $schema ): string {
		return json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Save schema to YAML string
	 *
	 * @param array $schema
	 * @return string
	 */
	public function saveToYaml( array $schema ): string {
		return Yaml::dump( $schema, 4, 2 );
	}

	/**
	 * Load schema from file (auto-detects format from extension)
	 *
	 * @param string $filePath
	 * @return array
	 * @throws \RuntimeException If file cannot be read or format is invalid
	 */
	public function loadFromFile( string $filePath ): array {
		if ( !file_exists( $filePath ) ) {
			throw new \RuntimeException( "File not found: $filePath" );
		}

		$content = file_get_contents( $filePath );
		if ( $content === false ) {
			throw new \RuntimeException( "Cannot read file: $filePath" );
		}

		$extension = strtolower( pathinfo( $filePath, PATHINFO_EXTENSION ) );

		if ( $extension === 'json' ) {
			return $this->loadFromJson( $content );
		} elseif ( in_array( $extension, [ 'yaml', 'yml' ] ) ) {
			return $this->loadFromYaml( $content );
		} else {
			// Try JSON first, then YAML
			try {
				return $this->loadFromJson( $content );
			} catch ( \RuntimeException $e ) {
				return $this->loadFromYaml( $content );
			}
		}
	}

	/**
	 * Save schema to file (format determined by extension)
	 *
	 * @param array $schema
	 * @param string $filePath
	 * @return bool
	 * @throws \RuntimeException If file cannot be written
	 */
	public function saveToFile( array $schema, string $filePath ): bool {
		$extension = strtolower( pathinfo( $filePath, PATHINFO_EXTENSION ) );

		if ( $extension === 'json' ) {
			$content = $this->saveToJson( $schema );
		} elseif ( in_array( $extension, [ 'yaml', 'yml' ] ) ) {
			$content = $this->saveToYaml( $schema );
		} else {
			// Default to JSON
			$content = $this->saveToJson( $schema );
		}

		$result = file_put_contents( $filePath, $content );
		if ( $result === false ) {
			throw new \RuntimeException( "Cannot write to file: $filePath" );
		}

		return true;
	}

	/**
	 * Detect format from content
	 *
	 * @param string $content
	 * @return string 'json' or 'yaml'
	 */
	public function detectFormat( string $content ): string {
		$trimmed = trim( $content );

		// JSON typically starts with { or [
		if ( $trimmed[0] === '{' || $trimmed[0] === '[' ) {
			return 'json';
		}

		// Otherwise assume YAML
		return 'yaml';
	}

	/**
	 * Load from content with auto-detection
	 *
	 * @param string $content
	 * @return array
	 * @throws \RuntimeException If content cannot be parsed
	 */
	public function loadFromContent( string $content ): array {
		$format = $this->detectFormat( $content );

		if ( $format === 'json' ) {
			return $this->loadFromJson( $content );
		} else {
			return $this->loadFromYaml( $content );
		}
	}

	/**
	 * Create an empty schema structure
	 *
	 * @return array
	 */
	public function createEmptySchema(): array {
		return [
			'schemaVersion' => '1.0',
			'categories' => [],
			'properties' => [],
		];
	}

	/**
	 * Validate basic schema structure
	 *
	 * @param array $schema
	 * @return bool
	 */
	public function hasValidStructure( array $schema ): bool {
		return isset( $schema['schemaVersion'] ) &&
			isset( $schema['categories'] ) &&
			isset( $schema['properties'] ) &&
			is_array( $schema['categories'] ) &&
			is_array( $schema['properties'] );
	}
}

