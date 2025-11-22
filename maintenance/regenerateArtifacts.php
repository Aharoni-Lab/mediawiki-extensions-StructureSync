<?php

namespace MediaWiki\Extension\StructureSync\Maintenance;

use Maintenance;
use MediaWiki\Extension\StructureSync\Store\WikiCategoryStore;
use MediaWiki\Extension\StructureSync\Generator\TemplateGenerator;
use MediaWiki\Extension\StructureSync\Generator\FormGenerator;
use MediaWiki\Extension\StructureSync\Generator\DisplayStubGenerator;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to regenerate templates and forms
 */
class RegenerateArtifacts extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Regenerate templates and forms for categories' );
		$this->addOption( 'category', 'Regenerate artifacts for a specific category', false, true );
		$this->addOption( 'generate-display', 'Generate display stubs for missing templates', false, false );
		$this->requireExtension( 'StructureSync' );
	}

	public function execute() {
		$categoryName = $this->getOption( 'category' );
		$generateDisplay = $this->hasOption( 'generate-display' );

		$categoryStore = new WikiCategoryStore();
		$templateGenerator = new TemplateGenerator();
		$formGenerator = new FormGenerator();
		$displayGenerator = new DisplayStubGenerator();

		if ( $categoryName !== null ) {
			// Regenerate for specific category
			$this->output( "Regenerating artifacts for category: $categoryName\n" );
			$category = $categoryStore->readCategory( $categoryName );

			if ( $category === null ) {
				$this->fatalError( "Category not found: $categoryName" );
			}

			$this->regenerateCategory( $category, $templateGenerator, $formGenerator, $displayGenerator, $generateDisplay );
		} else {
			// Regenerate for all categories
			$this->output( "Regenerating artifacts for all categories...\n\n" );
			$categories = $categoryStore->getAllCategories();

			$this->output( "Found " . count( $categories ) . " categories\n\n" );

			foreach ( $categories as $category ) {
				$this->regenerateCategory( $category, $templateGenerator, $formGenerator, $displayGenerator, $generateDisplay );
			}
		}

		$this->output( "\nRegeneration complete!\n" );
	}

	/**
	 * Regenerate artifacts for a single category
	 *
	 * @param \MediaWiki\Extension\StructureSync\Schema\CategoryModel $category
	 * @param TemplateGenerator $templateGenerator
	 * @param FormGenerator $formGenerator
	 * @param DisplayStubGenerator $displayGenerator
	 * @param bool $generateDisplay
	 */
	private function regenerateCategory( $category, $templateGenerator, $formGenerator, $displayGenerator, $generateDisplay ) {
		$name = $category->getName();
		$this->output( "Processing: $name\n" );

		// Generate semantic template
		$result = $templateGenerator->generateAllTemplates( $category );
		if ( $result['success'] ) {
			$this->output( "  ✓ Generated semantic and dispatcher templates\n" );
		} else {
			$this->output( "  ✗ Template generation failed\n" );
			foreach ( $result['errors'] as $error ) {
				$this->output( "    - $error\n" );
			}
		}

		// Generate form
		if ( $formGenerator->generateAndSaveForm( $category ) ) {
			$this->output( "  ✓ Generated form\n" );
		} else {
			$this->output( "  ✗ Form generation failed\n" );
		}

		// Generate display stub if requested and missing
		if ( $generateDisplay ) {
			$displayResult = $displayGenerator->generateDisplayStubIfMissing( $category );
			if ( $displayResult['created'] ) {
				$this->output( "  ✓ Generated display template stub\n" );
			} else {
				$this->output( "  - Display template: {$displayResult['message']}\n" );
			}
		}

		$this->output( "\n" );
	}
}

$maintClass = RegenerateArtifacts::class;
require_once RUN_MAINTENANCE_IF_MAIN;

