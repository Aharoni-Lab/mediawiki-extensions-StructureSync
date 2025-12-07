<?php

namespace MediaWiki\Extension\StructureSync\Generator;

use MediaWiki\Extension\StructureSync\Schema\PropertyModel;
use MediaWiki\Extension\StructureSync\Store\PageCreator;
use MediaWiki\Extension\StructureSync\Store\WikiPropertyStore;
use MediaWiki\Extension\StructureSync\Util\NamingHelper;

/**
 * PropertyTemplateGenerator
 * -------------------------
 * Generates Template:Property/[Name] pages for property display.
 *
 * Replaces inline HTML templates with proper MediaWiki templates,
 * enabling template transclusion, reuse, and wiki-native editing.
 *
 * Generated templates:
 *   - Template:Property/[PropertyName] - property-specific template
 *   - Template:Property/Default - fallback for properties without custom templates
 *
 * Template parameters:
 *   - {{{1}}} or {{{value|}}} - the property value
 *   - {{{label|}}} - property label (optional, for default template)
 *
 * @since 1.0
 */
class PropertyTemplateGenerator
{

	private PageCreator $pageCreator;
	private WikiPropertyStore $propertyStore;

	public function __construct(
		?PageCreator $pageCreator = null,
		?WikiPropertyStore $propertyStore = null
	) {
		$this->pageCreator = $pageCreator ?? new PageCreator();
		$this->propertyStore = $propertyStore ?? new WikiPropertyStore();
	}

	/* =========================================================================
	 * PUBLIC API
	 * ========================================================================= */

	/**
	 * Generate property template for a specific property.
	 *
	 * Reads the property's [[Has template::]] value and generates the template.
	 * If no custom template is defined, generates a simple passthrough template.
	 *
	 * @param PropertyModel $property Property model
	 * @return array Result with keys: success (bool), message (string), error (string|null)
	 */
	public function generatePropertyTemplate(PropertyModel $property): array
	{
		$propertyName = $property->getName();
		$templateName = 'Property/' . $propertyName;

		// Get template code from property's "Has template" field
		$templateCode = $this->getPropertyTemplateCode($property);

		// Debug logging
		$hasTemplateValue = $property->getHasTemplate();
		if ($hasTemplateValue !== null) {
			wfDebugLog('structuresync', "Property '{$propertyName}' has template value: " . substr($hasTemplateValue, 0, 100));
		}
		if ($templateCode !== null) {
			wfDebugLog('structuresync', "Property '{$propertyName}' resolved template code: " . substr($templateCode, 0, 100));
		}

		if ($templateCode === null) {
			// No custom template defined, generate a simple passthrough template
			$templateCode = '{{{value}}}';
			wfDebugLog('structuresync', "Property '{$propertyName}' using passthrough template");
		}

		$content = $this->wrapTemplateContent($templateCode, $propertyName);

		$title = $this->pageCreator->makeTitle($templateName, NS_TEMPLATE);
		if (!$title) {
			wfDebugLog('structuresync', "FAILED TO CREATE TITLE: {$templateName}");
			return [
				'success' => false,
				'error' => "Failed to create title for {$templateName}",
			];
		}

		wfDebugLog('structuresync', "About to save template: {$templateName}, title valid: yes, content length: " . strlen($content));

		$success = $this->pageCreator->createOrUpdatePage(
			$title,
			$content,
			'StructureSync: Generated property template'
		);

		if (!$success) {
			$lastError = $this->pageCreator->getLastError();
			$errorDetail = $lastError ? ": {$lastError}" : '';
			wfDebugLog('structuresync', "FAILED TO SAVE: {$templateName}{$errorDetail}");
			return [
				'success' => false,
				'error' => "Failed to write template {$templateName}{$errorDetail}",
			];
		}

		wfDebugLog('structuresync', "Successfully saved: {$templateName}");

		return [
			'success' => true,
			'message' => "Generated template {$templateName}",
		];
	}

	/**
	 * Generate property templates for all properties.
	 *
	 * @return array Result summary with counts and details
	 */
	public function generateAllPropertyTemplates(): array
	{
		$properties = $this->propertyStore->getAllProperties();
		wfDebugLog('structuresync', "getAllProperties returned " . count($properties) . " properties");

		$generated = 0;
		$skipped = 0;
		$errors = [];
		$details = [];

		foreach ($properties as $property) {
			$propertyName = $property->getName();

			// Skip meta-properties (used for schema definition, not display)
			if ($this->isMetaProperty($propertyName)) {
				$skipped++;
				wfDebugLog('structuresync', "Skipping meta-property: {$propertyName}");
				continue;
			}

			// Email, URL, Image are now treated as regular properties with templates defined in wiki


			$hasTemplate = $property->getHasTemplate();

			wfDebugLog('structuresync', "Processing property: {$propertyName}, hasTemplate: " . ($hasTemplate ? substr($hasTemplate, 0, 50) : 'null'));

			$result = $this->generatePropertyTemplate($property);

			if (!empty($result['success'])) {
				$generated++;
				// Track which properties have custom templates vs passthrough
				if ($hasTemplate !== null && trim($hasTemplate) !== '') {
					$details[] = "{$propertyName}: custom template";
				} else {
					$details[] = "{$propertyName}: passthrough";
				}
			} else {
				$errors[] = $result['error'] ?? 'Unknown error';
				wfDebugLog('structuresync', "FAILED property {$propertyName}: " . ($result['error'] ?? 'Unknown error'));
			}
		}

		return [
			'success' => empty($errors),
			'generated' => $generated,
			'skipped' => $skipped,
			'errors' => $errors,
			'details' => $details,
		];
	}

	/**
	 * Check if a property is a meta-property (used for schema definition).
	 *
	 * Meta-properties define how other properties and categories behave,
	 * and shouldn't have display templates generated.
	 *
	 * @param string $propertyName Property name
	 * @return bool True if meta-property
	 */
	private function isMetaProperty(string $propertyName): bool
	{
		$metaProperties = [
			// Category schema properties
			'Display label',
			'Has description',
			'Has target namespace',
			'Has parent category',
			'Has required property',
			'Has optional property',
			'Has required subobject',
			'Has optional subobject',
			'Render as',
			'Has display header property',
			'Has display section name',
			'Has display section property',

			// Property schema properties
			'Has type',
			'Allows value',
			'Allows multiple values',
			'Has domain and range',
			'Subproperty of',
			'Allows value from category',
			'Allows value from namespace',
			'Has template',

			// TemplateFormat schema properties
			'Has wrapper template',
			'Has property template pattern',
			'Has section separator',
			'Has empty value behavior',

			// PropertyType schema properties
			'Has template code',
			'Has template parameter',

			// Subobject schema properties
			'Has subobject type',

			// FOAF/Ontology properties
			'Foaf:name',
			'Foaf:homepage',
			'Foaf:knows',
			'Owl:differentFrom',
		];

		return in_array($propertyName, $metaProperties, true);
	}

	/**
	 * Generate the default property template (Template:Property/Default).
	 *
	 * This is used as a fallback for properties without custom templates.
	 *
	 * @return array Result with keys: success (bool), message (string)
	 */
	public function generateDefaultPropertyTemplate(): array
	{
		$templateName = 'Property/Default';
		$content = $this->generateDefaultTemplateContent();

		$title = $this->pageCreator->makeTitle($templateName, NS_TEMPLATE);
		if (!$title) {
			return [
				'success' => false,
				'error' => "Failed to create title for {$templateName}",
			];
		}

		$success = $this->pageCreator->createOrUpdatePage(
			$title,
			$content,
			'StructureSync: Generated default property template'
		);

		if (!$success) {
			return [
				'success' => false,
				'error' => "Failed to write template {$templateName}",
			];
		}

		return [
			'success' => true,
			'message' => "Generated default property template",
		];
	}

	/**
	 * Check if a property template exists.
	 *
	 * @param string $propertyName Property name
	 * @return bool
	 */
	public function propertyTemplateExists(string $propertyName): bool
	{
		$templateName = 'Property/' . $propertyName;
		$title = $this->pageCreator->makeTitle($templateName, NS_TEMPLATE);
		return $title && $this->pageCreator->pageExists($title);
	}

	/* =========================================================================
	 * TEMPLATE CONTENT GENERATION
	 * ========================================================================= */

	/**
	 * Get template code for a property.
	 *
	 * Reads from property's [[Has template::]] field and resolves references.
	 *
	 * @param PropertyModel $property Property model
	 * @param array &$visited Visited properties for circular reference detection
	 * @return string|null Template wikitext, or null if not defined
	 */
	private function getPropertyTemplateCode(PropertyModel $property, array &$visited = []): ?string
	{
		$template = $property->getHasTemplate();

		if ($template === null || trim($template) === '') {
			return null;
		}

		$template = trim($template);

		// Decode URL-encoded templates (e.g. %5Bmailto:{{{value}}}%5D)
		// This allows templates to be stored in SMW properties without breaking parser
		$decoded = rawurldecode($template);
		if ($decoded !== $template) {
			wfDebugLog('structuresync', "Decoded Property template: '$template' -> '$decoded'");
			$template = $decoded;
		}

		// Check if this is inline template code (contains HTML tags or wiki markup)
		if ($this->isInlineTemplateCode($template)) {
			return $template;
		}

		// Otherwise, it's a reference to another property - resolve it
		return $this->resolveTemplateReference($template, $visited);
	}

	/**
	 * Check if a string is inline template code vs a reference.
	 *
	 * @param string $value Template value
	 * @return bool True if inline code, false if reference
	 */
	private function isInlineTemplateCode(string $value): bool
	{
		$trimmed = ltrim($value);

		// If it contains HTML tags, wiki markup, link syntax, or template calls, treat as inline code
		if (strpbrk($trimmed, '<[{{') !== false || strpos($trimmed, '{{{') !== false) {
			return true;
		}

		// Links like [mailto:...] don't contain {{, so check for leading '['
		if ($trimmed !== '' && $trimmed[0] === '[') {
			return true;
		}

		// Otherwise treat as property reference
		return false;
	}

	/**
	 * Resolve a template reference to another property.
	 *
	 * Instead of inlining the template code, we generate a delegation call.
	 * E.g., if "Has email" references "Email", we generate:
	 *   {{Property/Email|{{{1}}}}}
	 *
	 * Note: NO spaces around pipes to prevent whitespace in parameters.
	 * MediaWiki preserves all whitespace in template parameters, which breaks
	 * mailto: links and other whitespace-sensitive outputs.
	 *
	 * We use {{{1}}} (not {{{1|{{{value|}}}}}}}) because each template should
	 * handle its own parameter fallback. Passing complex patterns doesn't work
	 * in nested template contexts.
	 *
	 * @param string $propertyName Property name to resolve
	 * @param array &$visited Visited properties for circular reference detection
	 * @return string|null Template delegation call
	 */
	private function resolveTemplateReference(string $propertyName, array &$visited = []): ?string
	{
		wfDebugLog('structuresync', "Resolving template reference: {$propertyName}");

		// Detect circular references
		if (in_array($propertyName, $visited, true)) {
			wfLogWarning("StructureSync: Circular template reference detected for $propertyName");
			return null;
		}
		$visited[] = $propertyName;

		// Check if the referenced template exists or will be generated
		$templateName = 'Property/' . $propertyName;

		// Generate a simple delegation call with NO spaces around pipes
		// Each template handles its own {{{1|{{{value|}}}}}} fallback pattern
		$delegationCall = '{{' . $templateName . '|{{{1}}}}}';

		wfDebugLog('structuresync', "Resolved '{$propertyName}' to delegation: {$delegationCall}");

		return $delegationCall;
	}

	/**
	 * Wrap template content with includeonly tags and documentation.
	 *
	 * Substitutes {{{value}}} placeholder with proper MW template parameter syntax.
	 *
	 * @param string $templateCode Template wikitext
	 * @param string $propertyName Property name for documentation
	 * @return string Complete template page content
	 */
	private function wrapTemplateContent(string $templateCode, string $propertyName): string
	{
		$lines = [];

		$lines[] = '<noinclude>';
		$lines[] = '<!-- AUTO-GENERATED by StructureSync -->';
		$lines[] = '<!-- Property template for: ' . htmlspecialchars($propertyName) . ' -->';
		$lines[] = '<!-- Safe to edit. Changes are preserved during regeneration. -->';
		$lines[] = '';
		$lines[] = '== Usage ==';
		$lines[] = '<pre>{{ Property/' . htmlspecialchars($propertyName) . ' | value }}</pre>';
		$lines[] = '';
		$lines[] = '== Parameters ==';
		$lines[] = '* {{para|1}} or {{para|value}} - The property value to display';
		$lines[] = '</noinclude>';
		$lines[] = '<includeonly>';

		// Replace {{{value}}} placeholder with proper MW template parameter syntax
		// This allows the template to accept the value as parameter 1 or named parameter 'value'
		$processedCode = str_replace('{{{value}}}', '{{{1|{{{value|}}}}}}', $templateCode);

		$lines[] = $processedCode;
		$lines[] = '</includeonly>';

		return implode("\n", $lines);
	}

	/**
	 * Generate content for the default property template.
	 *
	 * This template accepts both the property value and optionally a label.
	 *
	 * @return string Template page content
	 */
	private function generateDefaultTemplateContent(): string
	{
		$lines = [];

		$lines[] = '<noinclude>';
		$lines[] = '<!-- AUTO-GENERATED by StructureSync -->';
		$lines[] = '<!-- Default property display template -->';
		$lines[] = '<!-- Used when a property does not have a custom template -->';
		$lines[] = '';
		$lines[] = '== Usage ==';
		$lines[] = '<pre>{{ Property/Default | label=Property Name | value }}</pre>';
		$lines[] = '';
		$lines[] = '== Parameters ==';
		$lines[] = '* {{para|label}} - Property label (optional)';
		$lines[] = '* {{para|1}} or {{para|value}} - The property value to display';
		$lines[] = '</noinclude>';
		$lines[] = '<includeonly>{{#if: {{{label|}}}|<span class="ss-label">{{{label}}}:</span> }}{{{1|{{{value|}}}}}}</includeonly>';

		return implode("\n", $lines);
	}

	/* =========================================================================
	 * PROPERTY TYPE TEMPLATES
	 * ========================================================================= */


}

