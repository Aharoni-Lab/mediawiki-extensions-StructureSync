<?php

namespace MediaWiki\Extension\StructureSync\Store;

use MediaWiki\Extension\StructureSync\Schema\PropertyModel;
use Title;

/**
 * Handles reading and writing Property pages
 */
class WikiPropertyStore {

	/** @var PageCreator */
	private $pageCreator;

	/**
	 * @param PageCreator|null $pageCreator
	 */
	public function __construct( PageCreator $pageCreator = null ) {
		$this->pageCreator = $pageCreator ?? new PageCreator();
	}

	/**
	 * Read a property from the wiki
	 *
	 * @param string $propertyName Property name (without "Property:" prefix)
	 * @return PropertyModel|null
	 */
	public function readProperty( string $propertyName ): ?PropertyModel {
		$title = $this->pageCreator->makeTitle( $propertyName, SMW_NS_PROPERTY );
		if ( $title === null || !$this->pageCreator->pageExists( $title ) ) {
			return null;
		}

		$content = $this->pageCreator->getPageContent( $title );
		if ( $content === null ) {
			return null;
		}

		// Parse property metadata from page content
		$data = $this->parsePropertyContent( $content, $propertyName );

		return new PropertyModel( $propertyName, $data );
	}

	/**
	 * Parse property page content to extract metadata
	 *
	 * @param string $content
	 * @param string $propertyName
	 * @return array
	 */
	private function parsePropertyContent( string $content, string $propertyName ): array {
		$data = [];

		// Extract datatype from [[Has type::Type]] annotation
		if ( preg_match( '/\[\[Has type::([^\]]+)\]\]/', $content, $matches ) ) {
			$data['datatype'] = trim( $matches[1] );
		}

		// Extract label from page content or default to property name
		// Look for a line like "This is the property for [description]"
		$lines = explode( "\n", $content );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( !empty( $line ) && !str_starts_with( $line, '[[' ) && !str_starts_with( $line, '{{' ) && !str_starts_with( $line, '<!--' ) ) {
				$data['description'] = $line;
				break;
			}
		}

		// For now, use property name as label
		$data['label'] = $propertyName;

		return $data;
	}

	/**
	 * Write a property to the wiki
	 *
	 * @param PropertyModel $property
	 * @return bool True on success
	 */
	public function writeProperty( PropertyModel $property ): bool {
		$title = $this->pageCreator->makeTitle( $property->getName(), SMW_NS_PROPERTY );
		if ( $title === null ) {
			return false;
		}

		$content = $this->generatePropertyContent( $property );
		$summary = "StructureSync: Update property metadata";

		return $this->pageCreator->createOrUpdatePage( $title, $content, $summary );
	}

	/**
	 * Generate property page content
	 *
	 * @param PropertyModel $property
	 * @return string
	 */
	private function generatePropertyContent( PropertyModel $property ): string {
		$lines = [];

		// Add description
		if ( !empty( $property->getDescription() ) ) {
			$lines[] = $property->getDescription();
			$lines[] = '';
		}

		// Add datatype
		$lines[] = '[[Has type::' . $property->getSMWType() . ']]';

		// Add allowed values if present
		if ( $property->hasAllowedValues() ) {
			$lines[] = '';
			$lines[] = '== Allowed values ==';
			foreach ( $property->getAllowedValues() as $value ) {
				$lines[] = "* [[Allows value::$value]]";
			}
		}

		// Add range category if present (for Page types)
		if ( $property->getRangeCategory() !== null ) {
			$lines[] = '';
			$lines[] = '[[Has domain and range::Category:' . $property->getRangeCategory() . ']]';
		}

		$lines[] = '';
		$lines[] = '[[Category:Properties]]';

		return implode( "\n", $lines );
	}

	/**
	 * Get all properties from the wiki
	 *
	 * @return PropertyModel[]
	 */
	public function getAllProperties(): array {
		$properties = [];

		// Get all pages in the Property namespace
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace' => SMW_NS_PROPERTY ],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$propertyName = str_replace( '_', ' ', $row->page_title );
			$property = $this->readProperty( $propertyName );
			if ( $property !== null ) {
				$properties[$propertyName] = $property;
			}
		}

		return $properties;
	}

	/**
	 * Check if a property exists
	 *
	 * @param string $propertyName
	 * @return bool
	 */
	public function propertyExists( string $propertyName ): bool {
		$title = $this->pageCreator->makeTitle( $propertyName, SMW_NS_PROPERTY );
		return $title !== null && $this->pageCreator->pageExists( $title );
	}
}

