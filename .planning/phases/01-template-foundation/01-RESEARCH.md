# Phase 1: Template Foundation - Research

**Researched:** 2026-01-19
**Domain:** MediaWiki property display templates, Page Forms #arraymap
**Confidence:** HIGH

## Summary

Phase 1 implements `Template:Property/Page` to render Page-type SMW property values as clickable wiki links. This is a minimal, focused phase that adds a single template definition to the existing extension configuration system.

The implementation is straightforward because SemanticSchemas already has the complete infrastructure for property display templates (`Template:Property/Default`, `Template:Property/Email`, `Template:Property/Link`). Adding `Template:Property/Page` follows the exact same pattern - a single entry in `resources/extension-config.json` with ~5 lines of JSON and 1 line of wikitext.

**Primary recommendation:** Add `Template:Property/Page` to Layer 0 templates in `extension-config.json` with content: `<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>`

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| MediaWiki Wikilinks | Core | `[[PageName]]` syntax for linking | Native MediaWiki, universally documented |
| Page Forms `#arraymap` | Current | Multi-value property iteration | Already required dependency of SemanticSchemas |
| MediaWiki `#if` | Core | Conditional rendering for empty values | Native parser function |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `{{!}}` magic word | MW 1.24+ | Pipe character escaping | When values contain pipes (not needed for basic Page links) |
| `{{PAGENAME}}` | Core | Extract page name without namespace | Future enhancement for cleaner display |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `#arraymap` | Manual comma parsing | `#arraymap` is cleaner, already used in codebase |
| `@@item@@` placeholder | `x` or `xx` | `@@item@@` avoids ALL collision issues, clearer intent |
| `#if` wrapper | Raw `[[{{{value}}}]]` | `#if` prevents empty `[[]]` output |

**Installation:**
No installation needed - all components are already available via existing dependencies.

## Architecture Patterns

### Recommended Project Structure
```
resources/
  extension-config.json    # Add template here in "templates" section
```

### Pattern 1: Property Display Template Convention
**What:** All property display templates receive `{{{value|}}}` parameter and return formatted output.
**When to use:** Always for display templates.
**Example:**
```wikitext
<!-- Source: extension-config.json existing templates -->
<includeonly>{{{value}}}</includeonly>                <!-- Property/Default -->
<includeonly>[mailto:{{{value|}}} {{{value|}}}]</includeonly>  <!-- Property/Email -->
<includeonly>[{{{value|}}} {{{value|}}}]</includeonly>         <!-- Property/Link -->
```

### Pattern 2: Empty Value Handling
**What:** Wrap output in `#if` to produce nothing for empty values.
**When to use:** Always when empty values should render nothing.
**Example:**
```wikitext
<!-- Source: Standard MediaWiki pattern -->
{{#if:{{{value|}}}|
  <!-- Output when value exists -->
|}}
```

### Pattern 3: Multi-Value Array Mapping
**What:** Use `#arraymap` to process comma-separated values individually.
**When to use:** When property may contain multiple comma-separated values.
**Example:**
```wikitext
<!-- Source: Page Forms documentation, existing TemplateGenerator.php -->
{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}
```
**Parameters:**
1. `{{{value|}}}` - Input string
2. `,` - Input delimiter (comma)
3. `@@item@@` - Variable placeholder
4. `[[@@item@@]]` - Output pattern per item
5. `, ` - Output separator (comma-space)

### Anti-Patterns to Avoid
- **Using `x` as #arraymap variable:** Causes collision with property names containing "x" (e.g., "Data export" becomes "Data eort"). Use `@@item@@` instead.
- **Semantic annotations in display templates:** `[[Property::Value]]` creates annotations. Display templates should only render, not store data.
- **Runtime #ifexist checks:** Expensive parser function limits. Template selection happens at generation time via `PropertyModel.getRenderTemplate()`.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Multi-value iteration | Custom comma parsing | `#arraymap` | Already used in codebase, handles edge cases |
| Empty value handling | Various workarounds | `#if` with `{{{value|}}}` pattern | Standard MediaWiki pattern |
| Template installation | Manual wiki edits | ExtensionConfigInstaller | Already handles Layer 0 templates |

**Key insight:** The entire infrastructure exists. This phase is purely adding one template definition.

## Common Pitfalls

### Pitfall 1: #arraymap Variable Collision
**What goes wrong:** Using `x` as the variable causes text substitution in property names containing "x".
**Why it happens:** `#arraymap` does simple string replacement of the variable throughout the formula.
**How to avoid:** Use `@@item@@` as the variable placeholder - unique enough to never appear in property names.
**Warning signs:** Strange property names appearing, values linked to wrong pages.

### Pitfall 2: Empty Value Produces Broken Link
**What goes wrong:** `[[{{{value}}}]]` with empty value produces `[[]]` which renders as broken link text.
**Why it happens:** No conditional check before creating wiki link.
**How to avoid:** Wrap in `{{#if:{{{value|}}}|...|}}` to output nothing for empty values.
**Warning signs:** Empty brackets `[[]]` visible in page output.

### Pitfall 3: Incorrect Layer for Template
**What goes wrong:** Adding template to wrong layer causes installation order issues.
**Why it happens:** Misunderstanding of ExtensionConfigInstaller's 5-layer system.
**How to avoid:** Property display templates go in Layer 0 (the `templates` section), which has no SMW dependencies.
**Warning signs:** Template not created during installation.

## Code Examples

Verified patterns from official sources and existing codebase:

### Complete Template:Property/Page Content
```wikitext
<!-- Source: Derived from STACK.md, matches existing template patterns -->
<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>
```

### extension-config.json Addition
```json
// Source: Follows existing pattern in resources/extension-config.json
"Property/Page": {
  "content": "<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>",
  "description": "Displays Page-type property values as wiki links"
}
```

### How Template Will Be Used (Context)
```wikitext
<!-- Source: DisplayStubGenerator.php generates this pattern -->
|-
! Parent Category
| {{Template:Property/Page | value={{{parent_category|}}} }}
```

### Behavior Examples
| Input | Output |
|-------|--------|
| `value=Person` | `[[Person]]` |
| `value=Person, Place, Thing` | `[[Person]], [[Place]], [[Thing]]` |
| `value=` (empty) | (nothing) |
| `value=Category:Items` | `[[Category:Items]]` |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No Page-type display template | Using Property/Default (plain text) | Current state | Page values display as plain text |
| `x` variable in #arraymap | `@@item@@` or unique variables | Discovered as best practice | Avoids property name corruption |

**Deprecated/outdated:**
- None relevant to this phase.

## Open Questions

Things that couldn't be fully resolved:

1. **Namespace prefix display**
   - What we know: Values may include namespace prefixes (e.g., `Property:Has type`)
   - What's unclear: Whether users want to see namespace in link text
   - Recommendation: Start with simple `[[value]]` which shows full link. Enhancement to strip namespace (`{{PAGENAME}}`) can be added later if needed. This is out of scope for Phase 1.

## Sources

### Primary (HIGH confidence)
- Codebase: `resources/extension-config.json` - Existing template definitions pattern
- Codebase: `src/Generator/DisplayStubGenerator.php:208-214` - Template usage pattern
- Codebase: `src/Generator/TemplateGenerator.php:92-94` - #arraymap usage pattern

### Secondary (MEDIUM confidence)
- [Extension:Page Forms/Page Forms and templates](https://www.mediawiki.org/wiki/Extension:Page_Forms/Page_Forms_and_templates) - #arraymap syntax documentation
- Existing project research: `.planning/research/STACK.md` - Comprehensive technology stack analysis
- Existing project research: `.planning/research/PITFALLS.md` - #arraymap collision documentation
- Existing project research: `.planning/research/ARCHITECTURE.md` - System architecture patterns

### Tertiary (LOW confidence)
- None - all claims verified against codebase or official documentation.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Uses existing dependencies only
- Architecture: HIGH - Follows exact pattern of existing templates
- Pitfalls: HIGH - Verified against Page Forms documentation and existing codebase

**Research date:** 2026-01-19
**Valid until:** No expiration - uses stable MediaWiki/Page Forms features

## Implementation Checklist

Based on this research, the planner should ensure:

- [ ] Template added to `templates` section of extension-config.json (Layer 0)
- [ ] Template content uses `@@item@@` variable (not `x`)
- [ ] Template wraps output in `#if` for empty value handling
- [ ] Template uses `#arraymap` for multi-value support
- [ ] Description field explains template purpose
- [ ] JSON is valid (test with JSON parser)

## Files to Modify

| File | Change | Lines |
|------|--------|-------|
| `resources/extension-config.json` | Add Property/Page template to templates section | ~5 |

**Total estimated changes:** ~5 lines of JSON
