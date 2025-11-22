<?php

namespace MediaWiki\Extension\StructureSync\Special;

use SpecialPage;
use Html;
use MediaWiki\Extension\StructureSync\Schema\SchemaExporter;
use MediaWiki\Extension\StructureSync\Schema\SchemaImporter;
use MediaWiki\Extension\StructureSync\Schema\SchemaValidator;
use MediaWiki\Extension\StructureSync\Schema\SchemaComparer;
use MediaWiki\Extension\StructureSync\Schema\SchemaLoader;
use MediaWiki\Extension\StructureSync\Store\WikiCategoryStore;
use MediaWiki\Extension\StructureSync\Store\WikiPropertyStore;
use MediaWiki\Extension\StructureSync\Generator\TemplateGenerator;
use MediaWiki\Extension\StructureSync\Generator\FormGenerator;
use MediaWiki\Extension\StructureSync\Generator\DisplayStubGenerator;

/**
 * Special page for managing StructureSync schema
 */
class SpecialStructureSync extends SpecialPage {

	public function __construct() {
		parent::__construct( 'StructureSync', 'editinterface' );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();

		$request = $this->getRequest();
		$output = $this->getOutput();

		// Check dependencies
		if ( !defined( 'SMW_VERSION' ) ) {
			$output->addHTML( Html::errorBox(
				$this->msg( 'structuresync-error-no-smw' )->parse()
			) );
			return;
		}

		if ( !defined( 'PF_VERSION' ) ) {
			$output->addHTML( Html::errorBox(
				$this->msg( 'structuresync-error-no-pageforms' )->parse()
			) );
			return;
		}

		// Add custom CSS
		$output->addModuleStyles( 'ext.structuresync.styles' );

		// Navigation tabs
		$action = $subPage ?? 'overview';
		$this->showNavigation( $action );

		// Route to appropriate action
		switch ( $action ) {
			case 'export':
				$this->showExport();
				break;
			case 'import':
				$this->showImport();
				break;
			case 'validate':
				$this->showValidate();
				break;
			case 'generate':
				$this->showGenerate();
				break;
			case 'diff':
				$this->showDiff();
				break;
			case 'overview':
			default:
				$this->showOverview();
				break;
		}
	}

	/**
	 * Show navigation tabs
	 *
	 * @param string $currentAction
	 */
	private function showNavigation( string $currentAction ): void {
		$tabs = [
			'overview' => $this->msg( 'structuresync-overview' )->text(),
			'export' => $this->msg( 'structuresync-export' )->text(),
			'import' => $this->msg( 'structuresync-import' )->text(),
			'validate' => $this->msg( 'structuresync-validate' )->text(),
			'generate' => $this->msg( 'structuresync-generate' )->text(),
			'diff' => $this->msg( 'structuresync-diff' )->text(),
		];

		$html = '<div class="structuresync-nav">';
		$html .= '<ul>';

		foreach ( $tabs as $action => $label ) {
			$class = ( $action === $currentAction ) ? 'active' : '';
			$url = $this->getPageTitle( $action )->getLocalURL();
			$html .= Html::rawElement( 'li', [ 'class' => $class ],
				Html::element( 'a', [ 'href' => $url ], $label )
			);
		}

		$html .= '</ul>';
		$html .= '</div>';

		$this->getOutput()->addHTML( $html );
	}

	/**
	 * Show overview page
	 */
	private function showOverview(): void {
		$output = $this->getOutput();
		$output->setPageTitle( $this->msg( 'structuresync-overview' )->text() );

		$exporter = new SchemaExporter();
		$stats = $exporter->getStatistics();

		$html = Html::element( 'h2', [], $this->msg( 'structuresync-overview-summary' )->text() );

		// Statistics
		$html .= Html::openElement( 'div', [ 'class' => 'structuresync-stats' ] );
		$html .= Html::element( 'p', [],
			$this->msg( 'structuresync-categories-count' )->numParams( $stats['categoryCount'] )->text()
		);
		$html .= Html::element( 'p', [],
			$this->msg( 'structuresync-properties-count' )->numParams( $stats['propertyCount'] )->text()
		);
		$html .= Html::closeElement( 'div' );

		// Category status table
		$html .= Html::element( 'h3', [], 'Categories' );
		$html .= $this->getCategoryStatusTable();

		$output->addHTML( $html );
	}

	/**
	 * Get category status table
	 *
	 * @return string HTML table
	 */
	private function getCategoryStatusTable(): string {
		$categoryStore = new WikiCategoryStore();
		$templateGenerator = new TemplateGenerator();
		$formGenerator = new FormGenerator();
		$displayGenerator = new DisplayStubGenerator();

		$categories = $categoryStore->getAllCategories();

		if ( empty( $categories ) ) {
			return Html::element( 'p', [], 'No categories found.' );
		}

		$html = Html::openElement( 'table', [ 'class' => 'wikitable sortable' ] );
		$html .= Html::openElement( 'thead' );
		$html .= Html::openElement( 'tr' );
		$html .= Html::element( 'th', [], 'Category' );
		$html .= Html::element( 'th', [], 'Parents' );
		$html .= Html::element( 'th', [], 'Properties' );
		$html .= Html::element( 'th', [], 'Template' );
		$html .= Html::element( 'th', [], 'Form' );
		$html .= Html::element( 'th', [], 'Display' );
		$html .= Html::closeElement( 'tr' );
		$html .= Html::closeElement( 'thead' );

		$html .= Html::openElement( 'tbody' );
		foreach ( $categories as $category ) {
			$name = $category->getName();
			$html .= Html::openElement( 'tr' );
			$html .= Html::element( 'td', [], $name );
			$html .= Html::element( 'td', [], count( $category->getParents() ) );
			$html .= Html::element( 'td', [], count( $category->getAllProperties() ) );
			$html .= Html::element( 'td', [],
				$templateGenerator->semanticTemplateExists( $name ) ? '✓' : '✗'
			);
			$html .= Html::element( 'td', [],
				$formGenerator->formExists( $name ) ? '✓' : '✗'
			);
			$html .= Html::element( 'td', [],
				$displayGenerator->displayStubExists( $name ) ? '✓' : '✗'
			);
			$html .= Html::closeElement( 'tr' );
		}
		$html .= Html::closeElement( 'tbody' );
		$html .= Html::closeElement( 'table' );

		return $html;
	}

	/**
	 * Show export page
	 */
	private function showExport(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();

		$output->setPageTitle( $this->msg( 'structuresync-export-title' )->text() );

		// Handle form submission
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'export' ) {
			$format = $request->getVal( 'format', 'json' );

			$exporter = new SchemaExporter();
			$schema = $exporter->exportToArray( false );

			$loader = new SchemaLoader();
			if ( $format === 'yaml' ) {
				$content = $loader->saveToYaml( $schema );
				$filename = 'schema.yaml';
				$contentType = 'text/yaml';
			} else {
				$content = $loader->saveToJson( $schema );
				$filename = 'schema.json';
				$contentType = 'application/json';
			}

			// Send as download
			$request->response()->header( 'Content-Type: ' . $contentType );
			$request->response()->header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			echo $content;
			exit;
		}

		// Show export form
		$html = Html::element( 'p', [], $this->msg( 'structuresync-export-description' )->text() );

		$html .= Html::openElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle( 'export' )->getLocalURL()
		] );

		$html .= Html::openElement( 'div', [ 'class' => 'structuresync-form-group' ] );
		$html .= Html::element( 'label', [], $this->msg( 'structuresync-export-format' )->text() );
		$html .= Html::openElement( 'select', [ 'name' => 'format' ] );
		$html .= Html::element( 'option', [ 'value' => 'json' ],
			$this->msg( 'structuresync-export-format-json' )->text()
		);
		$html .= Html::element( 'option', [ 'value' => 'yaml' ],
			$this->msg( 'structuresync-export-format-yaml' )->text()
		);
		$html .= Html::closeElement( 'select' );
		$html .= Html::closeElement( 'div' );

		$html .= Html::hidden( 'action', 'export' );
		$html .= Html::submitButton( $this->msg( 'structuresync-export-button' )->text(), [
			'class' => 'mw-ui-button mw-ui-progressive'
		] );

		$html .= Html::closeElement( 'form' );

		$output->addHTML( $html );
	}

	/**
	 * Show import page
	 */
	private function showImport(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();

		$output->setPageTitle( $this->msg( 'structuresync-import-title' )->text() );

		// Handle form submission
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'import' ) {
			$this->processImport();
			return;
		}

		// Show import form
		$html = Html::element( 'p', [], $this->msg( 'structuresync-import-description' )->text() );

		$html .= Html::openElement( 'form', [
			'method' => 'post',
			'enctype' => 'multipart/form-data',
			'action' => $this->getPageTitle( 'import' )->getLocalURL()
		] );

		$html .= Html::openElement( 'div', [ 'class' => 'structuresync-form-group' ] );
		$html .= Html::element( 'label', [], $this->msg( 'structuresync-import-file' )->text() );
		$html .= Html::element( 'input', [ 'type' => 'file', 'name' => 'schemafile', 'accept' => '.json,.yaml,.yml' ] );
		$html .= Html::closeElement( 'div' );

		$html .= Html::openElement( 'div', [ 'class' => 'structuresync-form-group' ] );
		$html .= Html::element( 'label', [], $this->msg( 'structuresync-import-text' )->text() );
		$html .= Html::element( 'textarea', [ 'name' => 'schematext', 'rows' => '10', 'cols' => '80' ], '' );
		$html .= Html::closeElement( 'div' );

		$html .= Html::openElement( 'div', [ 'class' => 'structuresync-form-group' ] );
		$html .= Html::check( 'dryrun', false, [ 'id' => 'dryrun' ] );
		$html .= Html::element( 'label', [ 'for' => 'dryrun' ],
			$this->msg( 'structuresync-import-dryrun' )->text()
		);
		$html .= Html::closeElement( 'div' );

		$html .= Html::hidden( 'action', 'import' );
		$html .= Html::submitButton( $this->msg( 'structuresync-import-button' )->text(), [
			'class' => 'mw-ui-button mw-ui-progressive'
		] );

		$html .= Html::closeElement( 'form' );

		$output->addHTML( $html );
	}

	/**
	 * Process import submission
	 */
	private function processImport(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();

		$dryRun = $request->getBool( 'dryrun' );

		try {
			$loader = new SchemaLoader();

			// Try file upload first
			$upload = $request->getUpload( 'schemafile' );
			if ( $upload->exists() ) {
				$content = file_get_contents( $upload->getTempName() );
				$schema = $loader->loadFromContent( $content );
			} else {
				// Try textarea
				$content = $request->getText( 'schematext' );
				if ( empty( $content ) ) {
					$output->addHTML( Html::errorBox( 'No schema provided' ) );
					return;
				}
				$schema = $loader->loadFromContent( $content );
			}

			$importer = new SchemaImporter();
			$result = $importer->importFromArray( $schema, [
				'dryRun' => $dryRun,
				'generateArtifacts' => true,
			] );

			// Show results
			if ( $result['success'] ) {
				$message = $this->msg( 'structuresync-import-success' )->text() . '<br>';
				$message .= $this->msg( 'structuresync-import-created' )->numParams(
					$result['categoriesCreated'] + $result['propertiesCreated']
				)->text() . '<br>';
				$message .= $this->msg( 'structuresync-import-updated' )->numParams(
					$result['categoriesUpdated'] + $result['propertiesUpdated']
				)->text();

				$output->addHTML( Html::successBox( $message ) );
			} else {
				$message = implode( '<br>', $result['errors'] );
				$output->addHTML( Html::errorBox( $message ) );
			}
		} catch ( \Exception $e ) {
			$output->addHTML( Html::errorBox( 'Error: ' . $e->getMessage() ) );
		}
	}

	/**
	 * Show validate page
	 */
	private function showValidate(): void {
		$output = $this->getOutput();
		$output->setPageTitle( $this->msg( 'structuresync-validate-title' )->text() );

		$exporter = new SchemaExporter();
		$result = $exporter->validateWikiState();

		$html = Html::element( 'p', [], $this->msg( 'structuresync-validate-description' )->text() );

		if ( empty( $result['errors'] ) ) {
			$html .= Html::successBox( $this->msg( 'structuresync-validate-success' )->text() );
		} else {
			$html .= Html::element( 'h3', [], $this->msg( 'structuresync-validate-errors' )->text() );
			$html .= Html::openElement( 'ul' );
			foreach ( $result['errors'] as $error ) {
				$html .= Html::element( 'li', [], $error );
			}
			$html .= Html::closeElement( 'ul' );
		}

		if ( !empty( $result['warnings'] ) ) {
			$html .= Html::element( 'h3', [], $this->msg( 'structuresync-validate-warnings' )->text() );
			$html .= Html::openElement( 'ul' );
			foreach ( $result['warnings'] as $warning ) {
				$html .= Html::element( 'li', [], $warning );
			}
			$html .= Html::closeElement( 'ul' );
		}

		$output->addHTML( $html );
	}

	/**
	 * Show generate page
	 */
	private function showGenerate(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();

		$output->setPageTitle( $this->msg( 'structuresync-generate-title' )->text() );

		// Handle form submission
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'generate' ) {
			$this->processGenerate();
			return;
		}

		// Show generate form
		$html = Html::element( 'p', [], $this->msg( 'structuresync-generate-description' )->text() );

		$html .= Html::openElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle( 'generate' )->getLocalURL()
		] );

		$categoryStore = new WikiCategoryStore();
		$categories = $categoryStore->getAllCategories();

		$html .= Html::openElement( 'div', [ 'class' => 'structuresync-form-group' ] );
		$html .= Html::element( 'label', [], $this->msg( 'structuresync-generate-category' )->text() );
		$html .= Html::openElement( 'select', [ 'name' => 'category' ] );
		$html .= Html::element( 'option', [ 'value' => '' ], $this->msg( 'structuresync-generate-all' )->text() );
		foreach ( $categories as $category ) {
			$html .= Html::element( 'option', [ 'value' => $category->getName() ], $category->getName() );
		}
		$html .= Html::closeElement( 'select' );
		$html .= Html::closeElement( 'div' );

		$html .= Html::hidden( 'action', 'generate' );
		$html .= Html::submitButton( $this->msg( 'structuresync-generate-button' )->text(), [
			'class' => 'mw-ui-button mw-ui-progressive'
		] );

		$html .= Html::closeElement( 'form' );

		$output->addHTML( $html );
	}

	/**
	 * Process generate submission
	 */
	private function processGenerate(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();

		$categoryName = $request->getText( 'category' );
		$categoryStore = new WikiCategoryStore();
		$templateGenerator = new TemplateGenerator();
		$formGenerator = new FormGenerator();
		$displayGenerator = new DisplayStubGenerator();

		try {
			if ( empty( $categoryName ) ) {
				// Generate for all
				$categories = $categoryStore->getAllCategories();
			} else {
				$category = $categoryStore->readCategory( $categoryName );
				$categories = $category ? [ $category ] : [];
			}

			foreach ( $categories as $category ) {
				$templateGenerator->generateAllTemplates( $category );
				$formGenerator->generateAndSaveForm( $category );
				$displayGenerator->generateDisplayStubIfMissing( $category );
			}

			$output->addHTML( Html::successBox( $this->msg( 'structuresync-generate-success' )->text() ) );
		} catch ( \Exception $e ) {
			$output->addHTML( Html::errorBox( 'Error: ' . $e->getMessage() ) );
		}
	}

	/**
	 * Show diff page
	 */
	private function showDiff(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();

		$output->setPageTitle( $this->msg( 'structuresync-diff-title' )->text() );

		// Handle form submission
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'diff' ) {
			$this->processDiff();
			return;
		}

		// Show diff form
		$html = Html::element( 'p', [], $this->msg( 'structuresync-diff-description' )->text() );

		$html .= Html::openElement( 'form', [
			'method' => 'post',
			'enctype' => 'multipart/form-data',
			'action' => $this->getPageTitle( 'diff' )->getLocalURL()
		] );

		$html .= Html::openElement( 'div', [ 'class' => 'structuresync-form-group' ] );
		$html .= Html::element( 'label', [], $this->msg( 'structuresync-diff-file' )->text() );
		$html .= Html::element( 'textarea', [ 'name' => 'schematext', 'rows' => '10', 'cols' => '80' ], '' );
		$html .= Html::closeElement( 'div' );

		$html .= Html::hidden( 'action', 'diff' );
		$html .= Html::submitButton( $this->msg( 'structuresync-diff-button' )->text(), [
			'class' => 'mw-ui-button mw-ui-progressive'
		] );

		$html .= Html::closeElement( 'form' );

		$output->addHTML( $html );
	}

	/**
	 * Process diff submission
	 */
	private function processDiff(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();

		try {
			$content = $request->getText( 'schematext' );
			if ( empty( $content ) ) {
				$output->addHTML( Html::errorBox( 'No schema provided' ) );
				return;
			}

			$loader = new SchemaLoader();
			$fileSchema = $loader->loadFromContent( $content );

			$exporter = new SchemaExporter();
			$wikiSchema = $exporter->exportToArray( false );

			$comparer = new SchemaComparer();
			$diff = $comparer->compare( $fileSchema, $wikiSchema );
			$summary = $comparer->generateSummary( $diff );

			$output->addHTML( Html::element( 'pre', [], $summary ) );
		} catch ( \Exception $e ) {
			$output->addHTML( Html::errorBox( 'Error: ' . $e->getMessage() ) );
		}
	}

	protected function getGroupName() {
		return 'wiki';
	}
}

