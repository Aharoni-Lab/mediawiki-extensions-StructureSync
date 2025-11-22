<?php

namespace MediaWiki\Extension\StructureSync\Store;

use MediaWiki\Extension\StructureSync\Schema\CategoryModel;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SMW\DIWikiPage;
use SMW\DIProperty;

/**
 * Handles reading and writing Category pages with schema metadata
 */
class WikiCategoryStore {

	/** @var PageCreator */
	private $pageCreator;

	/** Schema content markers */
	private const MARKER_START = '<!-- StructureSync Schema Start -->';
	private const MARKER_END = '<!-- StructureSync Schema End -->';

	/**
	 * @param PageCreator|null $pageCreator
	 */
	public function __construct( PageCreator $pageCreator = null ) {
		$this->pageCreator = $pageCreator ?? new PageCreator();
	}

	/**
	 * Read a category from the wiki
	 *
	 * @param string $categoryName Category name (without "Category:" prefix)
	 * @return CategoryModel|null
	 */
	public function readCategory( string $categoryName ): ?CategoryModel {
		$title = $this->pageCreator->makeTitle( $categoryName, NS_CATEGORY );
		if ( $title === null || !$this->pageCreator->pageExists( $title ) ) {
			return null;
		}

		$content = $this->pageCreator->getPageContent( $title );
		if ( $content === null ) {
			return null;
		}

		// Parse category metadata from page content and SMW
		$data = $this->parseCategoryContent( $content, $categoryName );

		// Also try to read from SMW if available
		if ( defined( 'SMW_VERSION' ) ) {
			$data = array_merge( $data, $this->readFromSMW( $categoryName ) );
		}

		return new CategoryModel( $categoryName, $data );
	}

	/**
	 * Parse category page content to extract metadata
	 *
	 * @param string $content
	 * @param string $categoryName
	 * @return array
	 */
	private function parseCategoryContent( string $content, string $categoryName ): array {
		$data = [
			'parents' => [],
			'properties' => [
				'required' => [],
				'optional' => [],
			],
			'display' => [],
			'forms' => [],
		];

		// Extract parent categories from [[Category:Parent]] links
		preg_match_all( '/\[\[Category:([^\]|]+)(?:\|[^\]]+)?\]\]/', $content, $matches );
		if ( !empty( $matches[1] ) ) {
			$data['parents'] = array_map( 'trim', $matches[1] );
		}

		// Extract SMW property annotations
		// Has parent category
		preg_match_all( '/\[\[Has parent category::Category:([^\]]+)\]\]/', $content, $matches );
		if ( !empty( $matches[1] ) ) {
			$data['parents'] = array_unique( array_merge( $data['parents'], array_map( 'trim', $matches[1] ) ) );
		}

		// Has required property
		preg_match_all( '/\[\[Has required property::Property:([^\]]+)\]\]/', $content, $matches );
		if ( !empty( $matches[1] ) ) {
			$data['properties']['required'] = array_map( 'trim', $matches[1] );
		}

		// Has optional property
		preg_match_all( '/\[\[Has optional property::Property:([^\]]+)\]\]/', $content, $matches );
		if ( !empty( $matches[1] ) ) {
			$data['properties']['optional'] = array_map( 'trim', $matches[1] );
		}

		$data['label'] = $categoryName;
		$data['description'] = $this->extractDescription( $content );

		return $data;
	}

	/**
	 * Extract description from content
	 *
	 * @param string $content
	 * @return string
	 */
	private function extractDescription( string $content ): string {
		$lines = explode( "\n", $content );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			// Find first non-empty, non-annotation line
			if ( !empty( $line ) &&
				!str_starts_with( $line, '[[' ) &&
				!str_starts_with( $line, '{{' ) &&
				!str_starts_with( $line, '<!--' ) &&
				!str_starts_with( $line, '=' ) ) {
				return $line;
			}
		}
		return '';
	}

	/**
	 * Read category metadata from SMW store
	 *
	 * @param string $categoryName
	 * @return array
	 */
	private function readFromSMW( string $categoryName ): array {
		$data = [];

		// This would use SMW's store to query for property values
		// For now, we rely on parsing the page content
		// In a full implementation, you would use:
		// $store = \SMW\StoreFactory::getStore();
		// $subject = DIWikiPage::newFromTitle( Title::makeTitle( NS_CATEGORY, $categoryName ) );
		// Then query for property values

		return $data;
	}

	/**
	 * Write a category to the wiki
	 *
	 * @param CategoryModel $category
	 * @return bool True on success
	 */
	public function writeCategory( CategoryModel $category ): bool {
		$title = $this->pageCreator->makeTitle( $category->getName(), NS_CATEGORY );
		if ( $title === null ) {
			return false;
		}

		// Get existing content or create new
		$existingContent = $this->pageCreator->getPageContent( $title ) ?? '';

		// Generate schema metadata
		$schemaContent = $this->generateSchemaMetadata( $category );

		// Update content within markers to preserve non-schema content
		$newContent = $this->pageCreator->updateWithinMarkers(
			$existingContent,
			$schemaContent,
			self::MARKER_START,
			self::MARKER_END
		);

		$summary = "StructureSync: Update category schema metadata";

		return $this->pageCreator->createOrUpdatePage( $title, $newContent, $summary );
	}

	/**
	 * Generate schema metadata for category page
	 *
	 * @param CategoryModel $category
	 * @return string
	 */
	private function generateSchemaMetadata( CategoryModel $category ): string {
		$lines = [];

		// Add description if present
		if ( !empty( $category->getDescription() ) ) {
			$lines[] = $category->getDescription();
			$lines[] = '';
		}

		// Add parent category links
		foreach ( $category->getParents() as $parent ) {
			$lines[] = "[[Has parent category::Category:$parent]]";
		}

		if ( !empty( $category->getParents() ) ) {
			$lines[] = '';
		}

		// Add required properties
		if ( !empty( $category->getRequiredProperties() ) ) {
			$lines[] = '=== Required Properties ===';
			foreach ( $category->getRequiredProperties() as $prop ) {
				$lines[] = "[[Has required property::Property:$prop]]";
			}
			$lines[] = '';
		}

		// Add optional properties
		if ( !empty( $category->getOptionalProperties() ) ) {
			$lines[] = '=== Optional Properties ===';
			foreach ( $category->getOptionalProperties() as $prop ) {
				$lines[] = "[[Has optional property::Property:$prop]]";
			}
			$lines[] = '';
		}

		// Add display configuration as subobjects
		$displaySections = $category->getDisplaySections();
		if ( !empty( $displaySections ) ) {
			$lines[] = '=== Display Configuration ===';
			foreach ( $displaySections as $idx => $section ) {
				$lines[] = '{{#subobject:display_section_' . $idx;
				$lines[] = '|Has display section name=' . ( $section['name'] ?? '' );
				if ( !empty( $section['properties'] ) ) {
					foreach ( $section['properties'] as $prop ) {
						$lines[] = '|Has display section property=Property:' . $prop;
					}
				}
				$lines[] = '}}';
			}
			$lines[] = '';
		}

		// Add actual category membership
		foreach ( $category->getParents() as $parent ) {
			$lines[] = "[[Category:$parent]]";
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get all categories from the wiki
	 *
	 * @return CategoryModel[]
	 */
	public function getAllCategories(): array {
		$categories = [];

		// Get all pages in the Category namespace
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnection( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( 'page_title' )
			->from( 'page' )
			->where( [ 'page_namespace' => NS_CATEGORY ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$categoryName = str_replace( '_', ' ', $row->page_title );
			$category = $this->readCategory( $categoryName );
			if ( $category !== null ) {
				$categories[$categoryName] = $category;
			}
		}

		return $categories;
	}

	/**
	 * Check if a category exists
	 *
	 * @param string $categoryName
	 * @return bool
	 */
	public function categoryExists( string $categoryName ): bool {
		$title = $this->pageCreator->makeTitle( $categoryName, NS_CATEGORY );
		return $title !== null && $this->pageCreator->pageExists( $title );
	}
}

