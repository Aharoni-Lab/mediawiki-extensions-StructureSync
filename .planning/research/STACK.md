# Technology Stack: SMW Page-Type Property Display Templates

**Project:** SemanticSchemas - Page-type property display templates
**Researched:** 2026-01-19
**Overall Confidence:** HIGH (verified against official SMW documentation and codebase analysis)

## Executive Summary

Rendering Page-type SMW property values as wiki links requires a straightforward template pattern. The key insight is that SemanticSchemas already has the infrastructure for display templates (`Template:Property/Default`, `Template:Property/Email`, `Template:Property/Link`). Adding a `Template:Property/Page` follows the established pattern and requires only standard MediaWiki wikilink syntax `[[{{{value}}}]]` for single values, plus Page Forms' `#arraymap` for multi-value properties.

---

## Recommended Stack

### Core Technology (No New Dependencies)

| Technology | Version | Purpose | Confidence |
|------------|---------|---------|------------|
| MediaWiki Wikilinks | Core | Basic `[[PageName]]` syntax for linking | HIGH |
| Page Forms `#arraymap` | Current | Multi-value property iteration | HIGH |
| SemanticSchemas Template System | Existing | Property display template infrastructure | HIGH |

**No new extensions required.** The functionality uses standard MediaWiki and already-installed Page Forms parser functions.

---

## Implementation Approach

### Single-Value Page Properties

**Pattern:** Use standard wikilink syntax with the template parameter.

```wikitext
<includeonly>[[{{{value|}}}]]</includeonly>
```

**Why this works:**
- MediaWiki parser handles `[[PageName]]` natively
- Empty values produce `[[]]` which renders as nothing (acceptable)
- Page existence checking is automatic (red/blue links)

**Confidence:** HIGH - This is standard MediaWiki wikitext verified in all documentation.

### Multi-Value Page Properties

**Pattern:** Use Page Forms' `#arraymap` to iterate over comma-separated values.

```wikitext
<includeonly>{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}</includeonly>
```

**Why `#arraymap`:**
- Already available via Page Forms (required dependency of SemanticSchemas)
- Handles variable-length lists elegantly
- Produces comma-separated linked list output

**Why `@@item@@` instead of `x` or `var`:**
- The variable `x` interferes with `#ifexist` and other parser functions
- The variable `var` interferes with property names containing "var"
- Using `@@item@@` avoids all collision issues

**Confidence:** HIGH - Documented in [Page Forms templates documentation](https://www.mediawiki.org/wiki/Extension:Page_Forms/Page_Forms_and_templates).

---

## Recommended Template: `Template:Property/Page`

### Basic Implementation (Single Value)

```wikitext
<includeonly>{{#if:{{{value|}}}|[[{{{value}}}]]|}}</includeonly>
```

**Improvements over raw `[[{{{value}}}]]`:**
- `#if` prevents rendering `[[]]` for empty values
- Cleaner output when property is not set

### Full Implementation (Multi-Value Aware)

```wikitext
<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>
```

**Why this pattern:**
1. Outer `#if` handles empty values gracefully
2. `#arraymap` handles both single and multi-value cases identically
3. Comma delimiter matches SMW's default multi-value separator
4. Space after comma in output (`|, |`) improves readability

**Confidence:** HIGH - Combines verified patterns from official documentation.

---

## Integration with SemanticSchemas

### Current Architecture

The `DisplayStubGenerator.php` (lines 197-223) already generates display templates that call property templates:

```php
$renderTemplate = $property->getRenderTemplate();
$valueCall = "{{" . $renderTemplate . " | value={{{" . $paramName . "|}}} }}";
```

### Integration Path

1. **Add `Template:Property/Page` to `extension-config.json`** in the `templates` section (Layer 0)
2. **Set `hasTemplate` on Page-type properties** that should use wiki links

Example `extension-config.json` addition:

```json
"Property/Page": {
  "content": "<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>",
  "description": "Displays Page-type property values as wiki links"
}
```

**Confidence:** HIGH - This follows the exact pattern of existing templates (Property/Email, Property/Link).

---

## What NOT to Use

### Avoid: `#show` Parser Function

```wikitext
{{#show: PageName | ?Property }}
```

**Why NOT:**
- `#show` queries property values from pages, not renders passed values
- Wrong use case - we already have the value, we just need to link it
- Adds unnecessary database queries

**Confidence:** HIGH - `#show` is for querying, not display formatting.

### Avoid: Complex `#ask` Queries in Display Templates

**Why NOT:**
- Performance overhead per property render
- Display templates should be stateless wikitext transforms
- Violates separation between semantic storage and display

**Confidence:** HIGH - Architecture principle from SemanticSchemas design.

### Avoid: Inline `[[Property::Value]]` Syntax in Display Templates

```wikitext
[[Has author::{{{value|}}}]]
```

**Why NOT:**
- This creates semantic annotations, not just links
- SemanticSchemas separates semantic storage (Template:Category/semantic) from display
- Display templates should render, not annotate

**Confidence:** HIGH - Core SemanticSchemas architecture principle.

### Avoid: JavaScript-based Linking

**Why NOT:**
- Breaks parser cache
- Accessibility issues
- Unnecessary complexity for solved problem

**Confidence:** HIGH - Standard best practice.

---

## Handling Namespace Prefixes

### Current Behavior

SemanticSchemas' `TemplateGenerator.php` already handles namespace prefixes for Page-type properties with `allowedNamespace`:

```php
// Single value: conditional prefix
return ' | ' . $propertyName . ' = {{#if:{{{' . $param . '|}}}|' .
    $allowedNamespace . ':{{{' . $param . '|}}}|}}';
```

### Display Template Consideration

When values already include namespace prefixes (e.g., `Category:Person`), the display should:

1. **Link to the full page**: `[[Category:Person]]` links correctly
2. **Display without prefix**: User sees "Person" not "Category:Person"

**Enhanced Pattern:**

```wikitext
<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@{{!}}{{PAGENAME:@@item@@}}]]|, }}|}}</includeonly>
```

**Explanation:**
- `[[Full:Page|DisplayText]]` syntax shows DisplayText but links to Full:Page
- `{{PAGENAME:Full:Page}}` extracts just "Page" without namespace
- `{{!}}` is the escaped pipe character for use inside templates

**Confidence:** MEDIUM - The PAGENAME magic word behavior with namespace prefixes should be verified in testing.

---

## Multi-Value Property Handling Summary

| Scenario | Template Pattern | Notes |
|----------|-----------------|-------|
| Single value, no namespace | `[[{{{value}}}]]` | Simplest case |
| Single value, with namespace | `[[{{{value}}}{{!}}{{PAGENAME:{{{value}}}}}]]` | Shows clean name |
| Multi-value, no namespace | `{{#arraymap:{{{value|}}}|,|@@|[[@@]]|, }}` | Standard multi-value |
| Multi-value, with namespace | `{{#arraymap:{{{value|}}}|,|@@|[[@@{{!}}{{PAGENAME:@@}}]]|, }}` | Full solution |

**Confidence:** HIGH for basic patterns, MEDIUM for namespace-stripping (verify PAGENAME behavior).

---

## Recommended Implementation Strategy

### Phase 1: Basic Template (Minimal Viable)

Add `Template:Property/Page` with basic multi-value support:

```wikitext
<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>
```

This handles:
- Single-value Page properties
- Multi-value Page properties
- Empty values (renders nothing)

### Phase 2: Smart Namespace Display (Enhancement)

If needed, add namespace-aware version:

```wikitext
<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@{{!}}{{PAGENAME:@@item@@}}]]|, }}|}}</includeonly>
```

### Phase 3: Auto-Assignment (Future)

Consider auto-assigning `Template:Property/Page` to all Page-type properties without explicit `hasTemplate` configuration. This would require changes to `PropertyModel.getRenderTemplate()`.

---

## Sources

### Official Documentation (HIGH Confidence)
- [Help:Type Page - semantic-mediawiki.org](https://www.semantic-mediawiki.org/wiki/Help:Type_Page) - Page datatype behavior
- [Help:Semantic templates - semantic-mediawiki.org](https://www.semantic-mediawiki.org/wiki/Help:Semantic_templates) - Template patterns
- [Help:Using templates - semantic-mediawiki.org](https://www.semantic-mediawiki.org/wiki/Help:Using_templates) - Query result formatting

### Page Forms Documentation (HIGH Confidence)
- [Extension:Page Forms/Page Forms and templates](https://www.mediawiki.org/wiki/Extension:Page_Forms/Page_Forms_and_templates) - #arraymap syntax and examples

### Codebase Analysis (HIGH Confidence)
- `/home/daharoni/dev/SemanticSchemas/resources/extension-config.json` - Existing template definitions
- `/home/daharoni/dev/SemanticSchemas/src/Generator/DisplayStubGenerator.php` - Template integration pattern
- `/home/daharoni/dev/SemanticSchemas/src/Schema/PropertyModel.php` - Property template resolution

---

## Confidence Assessment

| Component | Confidence | Rationale |
|-----------|------------|-----------|
| Basic wikilink syntax | HIGH | Core MediaWiki, universally documented |
| `#arraymap` for multi-value | HIGH | Official Page Forms documentation |
| Template integration | HIGH | Existing codebase pattern verified |
| Namespace stripping with PAGENAME | MEDIUM | Needs testing with various namespace formats |
| Empty value handling with #if | HIGH | Standard MediaWiki parser function |

---

## Gaps / Items for Phase-Specific Research

1. **PAGENAME behavior verification** - Test `{{PAGENAME:Category:Foo}}` returns "Foo"
2. **Subobject namespace handling** - Verify custom namespace 3300 (Subobject) works with linking
3. **Performance with many #arraymap calls** - Unlikely issue but worth monitoring on large pages
