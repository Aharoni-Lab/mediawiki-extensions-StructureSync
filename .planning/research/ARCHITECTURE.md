# Architecture Patterns: SMW Display Templates for Page-Type Properties

**Research Date:** 2026-01-19
**Confidence:** HIGH (based on codebase analysis)

## Executive Summary

SemanticSchemas uses a Generation-Time Resolution pattern for property display. Templates like `Template:Property/Default` are statically referenced during display template generation. Adding Page-type support requires:

1. A new `Template:Property/Page` to render wiki links
2. Updated template selection logic in `PropertyModel.getRenderTemplate()`
3. Multi-value handling via `#arraymap` parser function (already used elsewhere)

## Existing Template System

### Template Parameter Convention

All property display templates receive a single parameter:

```wikitext
{{Template:Property/Email | value={{{email|}}} }}
```

**Parameter:** `value` - The property value(s) passed from the display template.

**Empty handling:** Uses `{{{value|}}}` pattern - default to empty string if not provided.

### Current Display Templates

| Template | Content | Use Case |
|----------|---------|----------|
| `Property/Default` | `{{{value}}}` | Plain text passthrough |
| `Property/Email` | `[mailto:{{{value|}}} {{{value|}}}]` | Mailto links |
| `Property/Link` | `[{{{value|}}} {{{value|}}}]` | External URL links |

### Template Selection Flow

```
PropertyModel.getRenderTemplate()
    |
    v
Has hasTemplate set?
    |-- YES --> Return hasTemplate value
    |-- NO  --> Return 'Template:Property/Default'
```

**Code reference:** `src/Schema/PropertyModel.php:263-265`

```php
public function getRenderTemplate(): string {
    return $this->hasTemplate ?? 'Template:Property/Default';
}
```

### Display Template Generation

`DisplayStubGenerator` creates display templates like `Template:Category/display`:

```php
// src/Generator/DisplayStubGenerator.php:208-214
$renderTemplate = $property->getRenderTemplate();
$valueCall = "{{" . $renderTemplate . " | value={{{" . $paramName . "|}}} }}";
```

Generated output example:
```wikitext
|-
! Parent Category
| {{Template:Property/Default | value={{{parent_category|}}} }}
```

## Recommended Architecture for Page-Type Template

### Component 1: Template:Property/Page

**Location:** `resources/extension-config.json` (templates section)

**Content:**
```wikitext
<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>
```

**Behavior:**
- Empty value: Renders nothing
- Single value: `[[PageName]]`
- Multi-value: `[[Page1]], [[Page2]], [[Page3]]`

**Key patterns used:**
- `#if` - Conditional to avoid empty brackets
- `#arraymap` - PageForms function to iterate over comma-separated values
- `@@item@@` - Arraymap placeholder for each value

### Component 2: Updated Template Selection Logic

**Location:** `src/Schema/PropertyModel.php`

**Pattern:** Add datatype-aware fallback before Default template:

```
getRenderTemplate()
    |
    v
Has hasTemplate? --> YES --> Return hasTemplate
    |
    NO
    |
    v
isPageType()? --> YES --> Return 'Template:Property/Page'
    |
    NO
    |
    v
Return 'Template:Property/Default'
```

**Rationale:** This preserves custom template overrides (via `Has template` property) while providing smart defaults for Page-type properties.

### Component 3: Base Config Registration

**Location:** `resources/extension-config.json`

Add to `templates` section:
```json
"Property/Page": {
  "content": "<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>",
  "description": "Displays Page-type property values as wiki links with multi-value support"
}
```

## Data Flow Direction

```
Schema Definition
       |
       v
PropertyModel created (with datatype, hasTemplate fields)
       |
       v
DisplayStubGenerator calls getRenderTemplate()
       |
       v
Datatype-aware selection: Page --> Property/Page
       |
       v
Generated display template includes:
{{ Template:Property/Page | value={{{property_param|}}} }}
       |
       v
At render time, #arraymap splits comma-separated values
       |
       v
Each value wrapped in [[...]] link syntax
```

## Multi-Value Handling Pattern

SemanticSchemas already uses `#arraymap` for multi-value handling in semantic templates:

```php
// src/Generator/TemplateGenerator.php:92-94
return '{{#arraymap:{{{' . $param .
    '|}}}|,|@@item@@|[[' . $propertyName . '::' . $allowedNamespace . ':@@item@@]]|}}';
```

The same pattern applies for display:
```wikitext
{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}
```

**Parameters:**
1. `{{{value|}}}` - Input string
2. `,` - Delimiter (comma)
3. `@@item@@` - Placeholder
4. `[[@@item@@]]` - Output pattern per item
5. `, ` - Output separator (comma-space)

## Component Boundaries

| Component | Responsibility | Modifies |
|-----------|---------------|----------|
| `extension-config.json` | Define template content | Config file only |
| `PropertyModel.php` | Template selection logic | `getRenderTemplate()` method |
| `ExtensionConfigInstaller.php` | Install templates to wiki | No changes needed (already handles templates) |
| `DisplayStubGenerator.php` | Use selected template | No changes needed (already calls `getRenderTemplate()`) |

## Build Order Implications

**Phase 1: Template Definition**
- Add `Property/Page` to `extension-config.json`
- Test: Manually create template, verify link rendering

**Phase 2: Selection Logic**
- Update `PropertyModel.getRenderTemplate()` for datatype fallback
- Test: Generate display for category with Page-type properties

**Phase 3: Regeneration**
- Run artifact regeneration for existing categories
- Test: Verify Page-type properties render as links

**Why this order:**
1. Template must exist before it can be selected
2. Selection logic references template by name
3. Regeneration applies changes to existing artifacts

## Namespace Prefix Handling

**Current state:** Values passed to display templates may or may not include namespace prefixes depending on how they were stored.

**Analysis from `TemplateGenerator.php:73-74`:**
```php
// Single value: conditional prefix
return ' | ' . $propertyName . ' = {{#if:{{{' . $param . '|}}}|' .
    $allowedNamespace . ':{{{' . $param . '|}}}|}}';
```

**Implication for display:** The template receives values that may already have namespace prefixes (e.g., `Property:Has type`). The wiki link syntax `[[Property:Has type]]` handles this correctly.

**No special namespace handling needed** in `Template:Property/Page` because:
1. Wiki syntax `[[Namespace:Page]]` works with or without namespace
2. SMW stores Page-type values with appropriate namespace info
3. The display template just wraps in `[[...]]`

## Anti-Patterns to Avoid

### Anti-Pattern 1: Runtime Template Selection

**Bad:** Selecting templates at wiki render time based on property lookup
```wikitext
{{#switch: {{Property:Has type of {{PAGENAME}}}}
| Page = ...
| Email = ...
}}
```

**Why bad:** Performance (extra queries), complexity, cache invalidation issues

**Instead:** Generation-time resolution (current pattern) - template selected when display stub is generated

### Anti-Pattern 2: Hardcoded Namespace in Template

**Bad:**
```wikitext
[[Property:{{{value}}}]]
```

**Why bad:** Assumes all Page-type properties link to Property namespace. Many link to Category, Main, or other namespaces.

**Instead:** Use value as-is (namespace should be in value already or derived from context)

### Anti-Pattern 3: Complex PHP in Display Logic

**Bad:** Moving display rendering to PHP code in DisplayStubGenerator

**Why bad:** Violates separation of concerns, makes wiki less customizable

**Instead:** Keep display logic in wiki templates, use generator only for wiring

## Integration Points

### With Extension Config Installer

`ExtensionConfigInstaller` already handles template installation:

```php
// resources/extension-config.json structure
"templates": {
  "Property/Page": { "content": "...", "description": "..." }
}
```

No PHP changes needed - installer reads templates from config and creates wiki pages.

### With DisplayStubGenerator

No changes needed. Generator already:
1. Looks up property via WikiPropertyStore
2. Calls `$property->getRenderTemplate()`
3. Embeds returned template name in output

### With Existing Custom Templates

Users who set `[[Has template::Template:MyCustomPage]]` on a property will continue to get their custom template. The datatype fallback only applies when `hasTemplate` is null.

## Test Scenarios

| Scenario | Input | Expected Output |
|----------|-------|-----------------|
| Single Page value | `Has parent category=Person` | `[[Person]]` |
| Multi-value | `required_property=Name, Age, Status` | `[[Name]], [[Age]], [[Status]]` |
| Empty value | `parent_category=` | (nothing) |
| Namespaced value | `required_property=Property:Has type` | `[[Property:Has type]]` |
| Custom template override | Property has `[[Has template::Template:MyLink]]` | Uses `Template:MyLink` |

## Summary of Required Changes

| File | Change | LOC Estimate |
|------|--------|--------------|
| `resources/extension-config.json` | Add Property/Page template definition | ~5 |
| `src/Schema/PropertyModel.php` | Update getRenderTemplate() with datatype fallback | ~8 |

**Total estimated changes:** ~13 lines of code

---

*Architecture research: 2026-01-19 | Confidence: HIGH*
