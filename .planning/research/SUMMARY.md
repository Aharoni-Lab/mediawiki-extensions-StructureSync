# Project Research Summary

**Project:** SemanticSchemas - Page-type Property Display Templates
**Domain:** MediaWiki/SMW template development
**Researched:** 2026-01-19
**Confidence:** HIGH

## Executive Summary

This project adds proper display rendering for Page-type SMW properties within SemanticSchemas. The existing extension already has a three-template system (Dispatcher/Semantic/Display) and property display templates (`Property/Default`, `Property/Email`, `Property/Link`). Adding Page-type support follows established patterns and requires minimal code changes: a new `Template:Property/Page` template (~10 lines of wikitext) and an optional enhancement to `PropertyModel.getRenderTemplate()` for datatype-aware fallback (~8 lines PHP).

The recommended approach uses standard MediaWiki wikilink syntax `[[PageName]]` wrapped in Page Forms' `#arraymap` for multi-value support. No new dependencies are required. The key pattern is: `{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}`. This handles single values, multi-values, and empty values gracefully.

The primary risks are template variable name collision (using `x` in `#arraymap` can corrupt property names containing "x") and namespace prefix display confusion (showing `Property:Name` instead of just `Name`). Both are well-documented issues with established solutions. The variable collision is avoided by using `@@item@@` as the placeholder. Namespace display can be handled with the pipe trick `[[Full:Page|DisplayText]]` if needed.

## Key Findings

### Recommended Stack

No new technologies or dependencies required. The implementation uses existing capabilities:

**Core technologies:**
- **MediaWiki Wikilinks**: Core `[[PageName]]` syntax for rendering page links with automatic red/blue differentiation
- **Page Forms #arraymap**: Already available via required PageForms dependency, handles multi-value iteration
- **SemanticSchemas Template System**: Existing `Property/*` template infrastructure for display formatting

### Expected Features

**Must have (table stakes):**
- Clickable wiki links for Page-type values
- Namespace prefix support (values like `Category:Person` link correctly)
- Red/blue link differentiation (automatic via MediaWiki)
- Multi-value comma separation (readable list output)
- Empty value handling (no broken `[[]]` markup)

**Should have (competitive):**
- Display title hiding namespace prefix (`[[Category:Foo|Foo]]`)
- Configurable display templates per property (already partially implemented via `Has template`)

**Defer (v2+):**
- Tooltips on hover
- Truncation with "show more" for long lists
- Category badge styling

### Architecture Approach

SemanticSchemas uses Generation-Time Resolution for property display. Template selection happens when `DisplayStubGenerator` generates display templates, not at wiki render time. This avoids expensive `#ifexist` calls. The new `Template:Property/Page` integrates by being registered in `extension-config.json` and selected via `PropertyModel.getRenderTemplate()`.

**Major components:**
1. **Template:Property/Page** (new) - Renders Page-type values as wiki links with multi-value support
2. **PropertyModel.getRenderTemplate()** (modification) - Add datatype-aware fallback: Page type -> Property/Page
3. **extension-config.json** (modification) - Register new template in Layer 0

### Critical Pitfalls

1. **#arraymap variable name collision** - Using `x` as the iterator variable corrupts property names containing "x" (e.g., "Data export" becomes "Data eort"). Use `@@item@@` instead.

2. **Namespace prefix display confusion** - Page-type values may include namespace prefixes that shouldn't appear in display text. Use `[[Full:Page|{{PAGENAME:Full:Page}}]]` to show clean names.

3. **Multi-value Page property annotations** - Using `#set` with comma-separated Page values creates a single malformed page reference. Use inline `[[Property::Value]]` syntax or `#arraymap` to create individual annotations.

4. **Empty value display** - Simple template parameters without proper `#if` guards produce broken `[[]]` markup. Always use `{{#if:{{{value|}}}|...|}}` pattern.

5. **Custom namespace configuration order** - `$smwgNamespacesWithSemanticLinks` must be configured AFTER `enableSemantics()` in LocalSettings.php, or Page-type properties targeting custom namespaces fail silently.

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 1: Template Definition
**Rationale:** Template must exist before it can be selected. This is the foundation with zero risk.
**Delivers:** `Template:Property/Page` added to `extension-config.json`
**Addresses:** Clickable wiki links, multi-value support, empty value handling
**Avoids:** #arraymap variable collision (uses `@@item@@`)

Implementation:
```json
"Property/Page": {
  "content": "<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@]]|, }}|}}</includeonly>",
  "description": "Displays Page-type property values as wiki links"
}
```

### Phase 2: Template Selection Logic
**Rationale:** Depends on Phase 1 template existing. Provides automatic Page-type handling without requiring explicit `Has template` configuration.
**Delivers:** Updated `PropertyModel.getRenderTemplate()` with datatype-aware fallback
**Uses:** Existing `getDatatype()` method to detect Page type
**Implements:** Generation-time resolution pattern (no runtime `#ifexist`)

### Phase 3: Artifact Regeneration
**Rationale:** Depends on Phases 1 and 2. Applies changes to existing categories.
**Delivers:** All existing Page-type properties render as clickable links
**Addresses:** Existing schema categories with Page-type properties

### Phase 4: Enhanced Display (Optional)
**Rationale:** Polish features, defer until core functionality proven.
**Delivers:** Namespace-stripped display text using pipe trick
**Addresses:** Clean display for namespaced values

Implementation:
```wikitext
{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@item@@|[[@@item@@{{!}}{{PAGENAME:@@item@@}}]]|, }}|}}
```

### Phase Ordering Rationale

- **Sequential dependency chain:** Template -> Selection Logic -> Regeneration
- **Risk-ordered:** Phase 1 is zero-risk (config-only), Phase 2 is low-risk (single method change), Phase 3 is operational
- **Pitfall awareness:** Phase 1 template uses `@@item@@` to avoid variable collision; Phase 2 uses generation-time resolution to avoid `#ifexist` performance issues

### Research Flags

Phases with standard patterns (skip research-phase):
- **Phase 1:** Well-documented wikitext patterns, verified in official Page Forms documentation
- **Phase 2:** Follows existing `getRenderTemplate()` code pattern, low complexity
- **Phase 3:** Existing regeneration tooling, operational procedure

Phase potentially needing validation:
- **Phase 4:** `{{PAGENAME:Namespace:Page}}` behavior should be tested to confirm it returns "Page" for all namespace formats including custom namespace 3300 (Subobject)

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | No new dependencies; uses core MediaWiki + existing extensions |
| Features | HIGH | Table stakes verified against SMW official documentation |
| Architecture | HIGH | Based on codebase analysis of existing pattern |
| Pitfalls | HIGH | Verified against official docs + codebase evidence |

**Overall confidence:** HIGH

### Gaps to Address

- **PAGENAME magic word with custom namespaces**: Verify `{{PAGENAME:Subobject:Foo}}` returns "Foo" for custom namespace 3300
- **Performance with many #arraymap calls**: Unlikely issue, but monitor on pages with many Page-type properties
- **Auto-assignment scope**: Decision needed on whether ALL Page-type properties should auto-use Property/Page, or only those without explicit `Has template`

## Sources

### Primary (HIGH confidence)
- [Help:Type Page](https://www.semantic-mediawiki.org/wiki/Help:Type_Page) - Page datatype behavior
- [Page Forms/Page Forms and templates](https://www.mediawiki.org/wiki/Extension:Page_Forms/Page_Forms_and_templates) - #arraymap syntax
- [SMW Setting Values](https://www.semantic-mediawiki.org/wiki/Help:Setting_values) - Property annotation patterns
- Codebase: `src/Generator/DisplayStubGenerator.php`, `src/Schema/PropertyModel.php`, `resources/extension-config.json`

### Secondary (MEDIUM confidence)
- [Page Forms Common Problems](https://www.mediawiki.org/wiki/Extension:Page_Forms/Common_problems) - #arraymap variable collision
- [Help:Semantic templates](https://www.semantic-mediawiki.org/wiki/Help:Semantic_templates) - Infobox patterns

---
*Research completed: 2026-01-19*
*Ready for roadmap: yes*
