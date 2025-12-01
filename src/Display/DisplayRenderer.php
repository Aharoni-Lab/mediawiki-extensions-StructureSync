<?php

namespace MediaWiki\Extension\StructureSync\Display;

use MediaWiki\Extension\StructureSync\Store\WikiPropertyStore;
use MediaWiki\Extension\StructureSync\Store\WikiSubobjectStore;
use MediaWiki\Extension\StructureSync\Schema\SubobjectModel;
use MediaWiki\Extension\StructureSync\Util\NamingHelper;
use MediaWiki\Title\Title;
use PPFrame;

/**
 * DisplayRenderer
 * ---------------
 * Renders category display sections with property values, applying custom
 * display templates, patterns, and types as defined on Property pages.
 *
 * Rendering Pipeline:
 * 1. DisplaySpecBuilder provides section structure (which properties in which sections)
 * 2. For each property, retrieve its value from the template parameter
 * 3. Apply display configuration in priority order:
 *    a) Direct display template (custom HTML/wikitext on the property)
 *    b) Display pattern (reference to another property's template)
 *    c) Display type (built-in rendering: Email, URL, Image, Boolean)
 *    d) Plain text (HTML-escaped)
 * 4. Wrap in section/row HTML structure
 *
 * Display Configuration:
 * - Properties can define [[Has display template::...]] with {{{value}}} placeholder
 * - Properties can define [[Has display pattern::Property:Name]] to reuse templates
 * - Properties can define [[Has display type::...]] for built-in rendering
 * - HTML templates are returned as-is; wikitext templates are parsed
 * - Properties with direct templates skip the label (they provide their own)
 * - Properties with patterns/types show the label: value format
 *
 * @since 1.0
 */
class DisplayRenderer {

	/** CSS class prefix for all display HTML elements */
	private const CSS_PREFIX = 'ss-';

	/** Default image size for Image display type */
	private const DEFAULT_IMAGE_SIZE = '200px';

	/** Template placeholder for property value */
	private const PLACEHOLDER_VALUE = '{{{value}}}';

	/** Template placeholder for property name */
	private const PLACEHOLDER_PROPERTY = '{{{property}}}';

	/** Template placeholder for page title */
	private const PLACEHOLDER_PAGE = '{{{page}}}';

	private WikiPropertyStore $propertyStore;
	private DisplaySpecBuilder $specBuilder;
	private WikiSubobjectStore $subobjectStore;

	public function __construct(
		?WikiPropertyStore $propertyStore = null,
		?DisplaySpecBuilder $specBuilder = null,
		?WikiSubobjectStore $subobjectStore = null
	) {
		$this->propertyStore   = $propertyStore   ?? new WikiPropertyStore();
		$this->specBuilder     = $specBuilder     ?? new DisplaySpecBuilder();
		$this->subobjectStore  = $subobjectStore  ?? new WikiSubobjectStore();
	}

	/* =====================================================================
	 * PUBLIC API
	 * ===================================================================== */

	/**
	 * Render all display sections for a category.
	 *
	 * This is the main entry point called by {{#StructureSyncRenderAllProperties:Category}}.
	 * It builds the display specification, renders each section, and returns the combined HTML.
	 *
	 * @param string $categoryName Category name (without "Category:" prefix)
	 * @param PPFrame $frame Parser frame containing template parameters
	 * @return string HTML output for all sections
	 */
	public function renderAllSections( string $categoryName, PPFrame $frame ): string {
		$spec = $this->specBuilder->buildSpec( $categoryName );
		$html = [];

		foreach ( $spec['sections'] as $section ) {
			$rendered = $this->renderSectionHtml( $section, $frame );
			if ( $rendered !== '' ) {
				$html[] = $rendered;
			}
		}

		return implode( "\n", $html );
	}

	/**
	 * Render a specific display section for a category.
	 *
	 * Called by {{#StructureSyncRenderSection:Category|SectionName}}.
	 *
	 * @param string $categoryName Category name (without "Category:" prefix)
	 * @param string $sectionName Section name to render
	 * @param PPFrame $frame Parser frame containing template parameters
	 * @return string HTML output for the section, or empty string if not found
	 */
	public function renderSection( string $categoryName, string $sectionName, PPFrame $frame ): string {
		$spec = $this->specBuilder->buildSpec( $categoryName );

		foreach ( $spec['sections'] as $section ) {
			if ( strcasecmp( $section['name'], $sectionName ) === 0 ) {
				return $this->renderSectionHtml( $section, $frame );
			}
		}

		return '';
	}

	/* =====================================================================
	 * SECTION RENDERING
	 * ===================================================================== */

	/**
	 * Render a single section with its properties.
	 *
	 * @param array $section Section specification with 'name' and 'properties' keys
	 * @param PPFrame $frame Parser frame containing template parameters
	 * @return string HTML for the section, or empty string if no properties have values
	 */
	private function renderSectionHtml( array $section, PPFrame $frame ): string {
		$rows = [];
		$hasValue = false;

		foreach ( $section['properties'] as $propertyName ) {
			// Convert property name to template parameter (e.g., "Has full name" -> "full_name")
			$param = NamingHelper::propertyToParameter( $propertyName );
			$rawValue = trim( $frame->getArgument( $param ) );

			if ( $rawValue === '' ) {
				continue;
			}

			$hasValue = true;

			// Render the property value with display configuration
			$htmlValue = $this->renderValue(
				$rawValue,
				$propertyName,
				$frame->getTitle()?->getText() ?? '',
				$frame
			);

			// Get the property label
			$property = $this->propertyStore->readProperty( $propertyName );
			$label = $property?->getLabel() ??
			         NamingHelper::generatePropertyLabel( $propertyName );

			// Properties with direct display templates provide their own labels/styling
			// Properties with patterns/types use the standard label: value format
			if ( $property && $property->getDisplayTemplate() !== null ) {
				$rows[] = $this->wrapCustomDisplay( $htmlValue );
			} else {
				$rows[] = $this->wrapRow( $label, $htmlValue );
			}
		}

		// Don't render empty sections
		if ( !$hasValue ) {
			return '';
		}

		return $this->wrapSection(
			$section['name'],
			implode( "\n", $rows )
		);
	}

	/**
	 * Wrap section content with heading and container.
	 *
	 * @param string $heading Section heading text
	 * @param string $content Section content HTML
	 * @return string Wrapped HTML
	 */
	private function wrapSection( string $heading, string $content ): string {
		$esc = htmlspecialchars( $heading, ENT_QUOTES );
		return <<<HTML
<div class="ss-section">
  <h2 class="ss-section-title">$esc</h2>
  $content
</div>
HTML;
	}

	/**
	 * Wrap property value in standard label: value row format.
	 *
	 * @param string $label Property label (will be escaped)
	 * @param string $valueHtml Property value HTML (already safe)
	 * @return string Wrapped HTML
	 */
	private function wrapRow( string $label, string $valueHtml ): string {
		$lab = htmlspecialchars( $label, ENT_QUOTES );
		return <<<HTML
<div class="ss-row">
  <span class="ss-label">$lab:</span>
  <span class="ss-value">$valueHtml</span>
</div>
HTML;
	}

	/**
	 * Wrap custom display HTML (no label, property provides its own styling).
	 *
	 * @param string $html Property HTML (already safe)
	 * @return string Wrapped HTML
	 */
	private function wrapCustomDisplay( string $html ): string {
		return <<<HTML
<div class="ss-row ss-custom-display">
  $html
</div>
HTML;
	}

	/* =====================================================================
	 * VALUE RENDERING PIPELINE
	 * ===================================================================== */

	/**
	 * Render a property value with its display configuration.
	 *
	 * Priority order:
	 * 1. Direct display template (inline HTML/wikitext on the property)
	 * 2. Display pattern (reference to another property's template)
	 * 3. Display type (built-in rendering or type-specific pattern)
	 * 4. Plain text (HTML-escaped)
	 *
	 * @param string $value Raw property value
	 * @param string $propertyName Property name (e.g., "Has email")
	 * @param string $pageTitle Current page title
	 * @param PPFrame $frame Parser frame for wikitext parsing
	 * @return string Rendered HTML
	 */
	private function renderValue( string $value, string $propertyName, string $pageTitle, PPFrame $frame ): string {
		$property = $this->propertyStore->readProperty( $propertyName );

		if ( !$property ) {
			return htmlspecialchars( $value );
		}

		// 1. Direct display template - highest priority
		if ( $property->getDisplayTemplate() !== null ) {
			return $this->renderTemplate( $property->getDisplayTemplate(), $value, $propertyName, $pageTitle, $frame );
		}

		// 2. Display pattern - reference to another property's template
		if ( $property->getDisplayPattern() !== null ) {
			$tmpl = $this->resolvePatternTemplate( $property->getDisplayPattern() );
			if ( $tmpl !== null ) {
				return $this->renderTemplate( $tmpl, $value, $propertyName, $pageTitle, $frame );
			}
		}

		// 3. Display type - built-in rendering or type-specific pattern
		if ( $property->getDisplayType() !== null ) {
			// First check if there's a pattern property for this type (e.g., Property:Email)
			$template = $this->loadDisplayTypeTemplate( $property->getDisplayType() );
			if ( $template !== null ) {
				return $this->renderTemplate( $template, $value, $propertyName, $pageTitle, $frame );
			}
			// Fall back to built-in rendering
			return $this->renderBuiltInType( $value, $property->getDisplayType() );
		}

		// 4. Plain text - lowest priority (default)
		return htmlspecialchars( $value );
	}

	/**
	 * Render a display template by substituting placeholders and parsing.
	 *
	 * Placeholders:
	 * - {{{value}}} - The property value
	 * - {{{property}}} - The property name
	 * - {{{page}}} - The current page title
	 *
	 * Template Processing:
	 * - HTML templates (containing < and >) are returned as-is after substitution
	 * - Wikitext templates (like [mailto:...]) are parsed through MediaWiki parser
	 *
	 * @param string $template Template string with placeholders
	 * @param string $value Property value to substitute
	 * @param string $propertyName Property name
	 * @param string $pageTitle Page title
	 * @param PPFrame $frame Parser frame for wikitext parsing
	 * @return string Rendered HTML
	 */
	private function renderTemplate(
		string $template,
		string $value,
		string $propertyName,
		string $pageTitle,
		PPFrame $frame
	): string {
		// Substitute placeholders with escaped values for security
		$result = strtr( $template, [
			self::PLACEHOLDER_VALUE    => htmlspecialchars( $value, ENT_QUOTES ),
			self::PLACEHOLDER_PROPERTY => htmlspecialchars( $propertyName, ENT_QUOTES ),
			self::PLACEHOLDER_PAGE     => htmlspecialchars( $pageTitle, ENT_QUOTES ),
		] );

		// HTML templates (with tags) are already valid HTML - return as-is
		if ( strpos( $result, '<' ) !== false && strpos( $result, '>' ) !== false ) {
			return $result;
		}

		// Wikitext templates need parsing (e.g., [mailto:...] becomes a link)
		return $frame->parser->recursiveTagParse( $result, $frame );
	}

	/* =====================================================================
	 * PATTERN RESOLUTION
	 * ===================================================================== */

	/**
	 * Resolve a display pattern by following the reference chain.
	 *
	 * A display pattern is a reference to another property (e.g., Property:Email)
	 * that provides a reusable display template. This method follows the chain
	 * to find the actual template, with circular reference detection.
	 *
	 * @param string $propertyName Property name to resolve
	 * @param array &$visited Visited properties (for circular reference detection)
	 * @return string|null The resolved template, or null if not found
	 */
	private function resolvePatternTemplate( string $propertyName, array &$visited = [] ): ?string {
		// Detect circular references
		if ( in_array( $propertyName, $visited, true ) ) {
			wfLogWarning( "StructureSync: Circular display pattern detected for $propertyName" );
			return null;
		}
		$visited[] = $propertyName;

		$property = $this->propertyStore->readProperty( $propertyName );
		if ( !$property ) {
			return null;
		}

		// Found a template - return it
		if ( $property->getDisplayTemplate() !== null ) {
			return $property->getDisplayTemplate();
		}

		// Follow the pattern chain
		if ( $property->getDisplayPattern() !== null ) {
			return $this->resolvePatternTemplate(
				$property->getDisplayPattern(),
				$visited
			);
		}

		return null;
	}

	/**
	 * Load a display type template (e.g., Property:Email for display type "Email").
	 *
	 * Display types can reference pattern properties that provide templates.
	 * For example, [[Has display type::Email]] might reference Property:Email.
	 *
	 * @param string $type Display type name
	 * @return string|null The resolved template, or null if not found
	 */
	private function loadDisplayTypeTemplate( string $type ): ?string {
		return $this->resolvePatternTemplate( $type );
	}

	/* =====================================================================
	 * BUILT-IN DISPLAY TYPES
	 * ===================================================================== */

	/**
	 * Render a value using built-in display type rendering.
	 *
	 * Built-in types provide default rendering when no pattern property exists:
	 * - email: Renders as [mailto:value value]
	 * - url: Renders as [value Website]
	 * - image: Renders as [[File:value|thumb|200px]]
	 * - boolean: Renders as "Yes" or "No"
	 *
	 * @param string $value Property value
	 * @param string $type Display type name
	 * @return string Rendered wikitext or HTML
	 */
	private function renderBuiltInType( string $value, string $type ): string {
		$t = strtolower( $type );

		return match ( $t ) {
			'email'   => '[mailto:' . htmlspecialchars( $value ) . ' ' . htmlspecialchars( $value ) . ']',
			'url'     => '[' . htmlspecialchars( $value ) . ' Website]',
			'image'   => '[[File:' . htmlspecialchars( $value ) . '|thumb|' . self::DEFAULT_IMAGE_SIZE . ']]',
			'boolean' => $this->renderBoolean( $value ),
			default   => htmlspecialchars( $value ),
		};
	}

	/**
	 * Render a boolean value as "Yes" or "No".
	 *
	 * Treats the following as true: 1, true, yes, on (case-insensitive)
	 *
	 * @param string $value Boolean value as string
	 * @return string "Yes" or "No"
	 */
	private function renderBoolean( string $value ): string {
		return in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true )
			? 'Yes'
			: 'No';
	}
}
