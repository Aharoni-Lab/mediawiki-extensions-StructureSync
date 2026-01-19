# Phase 2: System Integration - Research

**Researched:** 2026-01-19
**Domain:** PHP method modification, template selection logic
**Confidence:** HIGH

## Summary

Phase 2 implements smart template fallback logic so Page-type properties automatically use `Template:Property/Page`. This is a minimal modification to `PropertyModel.getRenderTemplate()` - adding approximately 5 lines of PHP code to check the property datatype before falling back to the default template.

The implementation is straightforward because:
1. **`isPageType()` already exists** - PropertyModel has a working method to detect Page-type properties
2. **DisplayStubGenerator already calls `getRenderTemplate()`** - No changes needed in the generator
3. **Template:Property/Page already exists** - Created and verified in Phase 1

The only complexity consideration is namespace prefix restoration for properties with `Allows_value_from_namespace`. However, based on codebase analysis, the existing template (with leading colon `[[:@@item@@]]`) already handles namespaced values correctly. The namespace prefix restoration discussed in CONTEXT.md may require additional template parameters if users want the namespace "added back" to values that were stored without it.

**Primary recommendation:** Add 5 lines to `getRenderTemplate()` implementing: custom template -> Page-type -> default fallback chain.

## Standard Stack

The established patterns for this domain:

### Core
| Component | Location | Purpose | Why Standard |
|-----------|----------|---------|--------------|
| `isPageType()` | PropertyModel.php:233-235 | Detects Page datatype | Already exists and tested |
| `getRenderTemplate()` | PropertyModel.php:263-265 | Returns template name | Current implementation to modify |
| `getHasTemplate()` | PropertyModel.php:259-261 | Returns custom template | Used for Has_template override check |

### Supporting
| Component | Location | Purpose | When to Use |
|-----------|----------|---------|-------------|
| `getAllowedNamespace()` | PropertyModel.php:281-283 | Returns namespace restriction | Potentially for enhanced template logic |
| `allowsMultipleValues()` | PropertyModel.php:285-287 | Detects multi-value properties | If different templates needed for single vs multi |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Modifying PropertyModel | Modifying DisplayStubGenerator | Generator already calls getRenderTemplate(), cleaner to keep logic in model |
| Hardcoded template names | Config-based template names | Hardcoded is simpler and sufficient for this scope |
| Runtime template lookup | Generation-time resolution | Generation-time is already the pattern, avoids expensive #ifexist |

**Installation:**
No additional dependencies needed - all components exist.

## Architecture Patterns

### Recommended Change Pattern

The modification follows the existing null-coalescing pattern in `getRenderTemplate()`:

```php
// CURRENT (src/Schema/PropertyModel.php:263-265)
public function getRenderTemplate(): string {
    return $this->hasTemplate ?? 'Template:Property/Default';
}

// RECOMMENDED
public function getRenderTemplate(): string {
    if ( $this->hasTemplate !== null ) {
        return $this->hasTemplate;
    }
    if ( $this->isPageType() ) {
        return 'Template:Property/Page';
    }
    return 'Template:Property/Default';
}
```

### Pattern 1: Fallback Chain Logic
**What:** Sequential checks with early return for each condition.
**When to use:** When multiple conditions determine a single return value.
**Example:**
```php
// Source: Common PHP pattern, follows existing codebase style
public function getRenderTemplate(): string {
    // Priority 1: Explicit custom template
    if ( $this->hasTemplate !== null ) {
        return $this->hasTemplate;
    }
    // Priority 2: Datatype-specific template
    if ( $this->isPageType() ) {
        return 'Template:Property/Page';
    }
    // Fallback: Default template
    return 'Template:Property/Default';
}
```

### Pattern 2: Generation-Time Resolution
**What:** Template selection happens when display templates are generated, not at wiki render time.
**When to use:** Always - avoids expensive runtime lookups.
**Already implemented in:** DisplayStubGenerator.php:210
```php
// Source: src/Generator/DisplayStubGenerator.php:208-214
$renderTemplate = $property->getRenderTemplate();
$valueCall = "{{" . $renderTemplate . " | value={{{" . $paramName . "|}}} }}";
```

### Anti-Patterns to Avoid
- **Runtime template selection in wiki templates:** Avoid `#ifexist` or `#switch` based on property lookup. Expensive and cache-unfriendly.
- **Checking hasTemplate in DisplayStubGenerator:** Keep template selection logic in PropertyModel where it belongs.
- **Changing DisplayStubGenerator calling pattern:** The generator already uses `getRenderTemplate()` correctly.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Page-type detection | String comparison on datatype | `isPageType()` | Already exists and handles normalization |
| Template name formatting | Manual string concatenation | Return full template name | Existing pattern includes "Template:" prefix |
| Null checking | Complex ternary expressions | Explicit if/return pattern | More readable, matches codebase style |

**Key insight:** This phase is purely additive logic to an existing method. No new methods or files needed.

## Common Pitfalls

### Pitfall 1: Breaking Custom Template Override
**What goes wrong:** Custom `Has_template` values stop working because new logic doesn't check them first.
**Why it happens:** Ordering conditions incorrectly in the fallback chain.
**How to avoid:** Always check `$this->hasTemplate !== null` FIRST before any datatype checks.
**Warning signs:** Properties with explicit `Has template` annotation render with wrong template.

### Pitfall 2: Case Sensitivity in Template Names
**What goes wrong:** `Template:Property/page` doesn't match `Template:Property/Page` in wiki.
**Why it happens:** PHP returns lowercase or wrong case in template name.
**How to avoid:** Use exact string `'Template:Property/Page'` with proper capitalization.
**Warning signs:** Red links or template not found errors.

### Pitfall 3: Forgetting Template Prefix
**What goes wrong:** Returning `'Property/Page'` instead of `'Template:Property/Page'`.
**Why it happens:** Inconsistency in how template names are stored/returned.
**How to avoid:** Match existing return format from `$this->hasTemplate` which includes full prefix.
**Warning signs:** Check existing templates like `Property/Email` to verify format.

### Pitfall 4: Display Templates Not Regenerated
**What goes wrong:** Phase completes but existing pages still show plain text for Page-type properties.
**Why it happens:** Display templates are generated once and cached. They don't update automatically.
**How to avoid:** Plan includes regeneration step for affected categories.
**Warning signs:** New categories work correctly, existing ones don't.

## Code Examples

Verified patterns from codebase analysis:

### Current getRenderTemplate() Implementation
```php
// Source: src/Schema/PropertyModel.php:263-265
public function getRenderTemplate(): string {
    return $this->hasTemplate ?? 'Template:Property/Default';
}
```

### isPageType() Implementation
```php
// Source: src/Schema/PropertyModel.php:233-235
public function isPageType(): bool {
    return $this->datatype === 'Page';
}
```

### How DisplayStubGenerator Uses Template
```php
// Source: src/Generator/DisplayStubGenerator.php:208-214
$renderTemplate = $property->getRenderTemplate();

// Construct the template call:
// {{ Template:Property/Email | value={{{email|}}} }}
$valueCall = "{{" . $renderTemplate . " | value={{{" . $paramName . "|}}} }}";

$out .= "|-\n";
$out .= "! " . $label . "\n";
$out .= "| " . $valueCall . "\n";
```

### Complete Updated getRenderTemplate()
```php
// Source: Planned implementation based on existing patterns
public function getRenderTemplate(): string {
    // Custom template takes priority
    if ( $this->hasTemplate !== null ) {
        return $this->hasTemplate;
    }
    // Page-type properties get Page template
    if ( $this->isPageType() ) {
        return 'Template:Property/Page';
    }
    // All other types get Default template
    return 'Template:Property/Default';
}
```

## Namespace Prefix Restoration Analysis

CONTEXT.md mentions namespace prefix restoration for properties with `Allows_value_from_namespace`. After analysis:

### Current Behavior

1. **Storage:** When a property has `Allows_value_from_namespace=Property`, PageForms autocomplete may store values without namespace prefix (just `Has_type` instead of `Property:Has_type`).

2. **Semantic Template:** TemplateGenerator adds namespace prefix during SMW storage:
   ```php
   // src/Generator/TemplateGenerator.php:73-74
   return ' | ' . $propertyName . ' = {{#if:{{{' . $param . '|}}}|' .
       $allowedNamespace . ':{{{' . $param . '|}}}|}}';
   ```

3. **Display Template:** Template:Property/Page renders `[[value]]`. If value lacks namespace, link points to main namespace.

### Options for Namespace Restoration

| Approach | Complexity | Recommendation |
|----------|------------|----------------|
| Pass namespace as template parameter | MEDIUM | Would require DisplayStubGenerator changes |
| Read from SMW annotation in template | HIGH | Expensive runtime query |
| Assume values include namespace | LOW | Works for values stored WITH namespace prefix |
| Handle in getRenderTemplate() only | N/A | Method returns template name, not logic |

### Recommendation

For Phase 2, focus on the core success criteria: template selection logic. The namespace prefix issue is about **value format at storage time**, not template selection. If values are stored without namespace prefix, that's a semantic template issue (already handled by TemplateGenerator for storage).

The display template renders whatever value it receives. If the stored SMW value includes namespace (via TemplateGenerator's prefix logic), display works correctly. If not, that's a separate concern.

**Action:** Defer namespace restoration to a future enhancement if user testing reveals issues.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No Page-type template | Page-type uses Property/Default | Phase 1 added Template:Property/Page | Page values show as plain text |
| All properties use Default | Datatype-aware selection | Phase 2 (this phase) | Automatic Page template usage |

**Deprecated/outdated:**
- None relevant to this phase.

## Open Questions

Things that couldn't be fully resolved:

1. **Namespace prefix in stored values**
   - What we know: TemplateGenerator adds prefix during storage for single-namespace properties
   - What's unclear: Whether all Page-type properties with namespace restrictions have correct prefix in stored values
   - Recommendation: Verify during testing. If issues arise, address in separate plan.

2. **Future datatype-specific templates**
   - What we know: Phase 2 adds Page-type awareness
   - What's unclear: Should other datatypes (URL, Email) follow similar auto-selection?
   - Recommendation: Current Email/Link templates require explicit Has_template setting. Could enhance later.

## Sources

### Primary (HIGH confidence)
- Codebase: `src/Schema/PropertyModel.php` - getRenderTemplate(), isPageType(), getHasTemplate()
- Codebase: `src/Generator/DisplayStubGenerator.php:208-214` - Template usage pattern
- Codebase: `src/Generator/TemplateGenerator.php:60-75` - Namespace prefix handling in storage

### Secondary (MEDIUM confidence)
- `.planning/research/ARCHITECTURE.md` - System architecture patterns
- `.planning/phases/01-template-foundation/01-RESEARCH.md` - Phase 1 patterns
- `.planning/phases/02-system-integration/02-CONTEXT.md` - User decisions

### Tertiary (LOW confidence)
- PageForms documentation (WebSearch, unable to fetch) - Namespace stripping behavior unverified

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components exist in codebase and verified
- Architecture: HIGH - Follows exact existing patterns
- Pitfalls: HIGH - Derived from codebase analysis and Phase 1 learnings

**Research date:** 2026-01-19
**Valid until:** No expiration - uses stable internal patterns

## Implementation Checklist

Based on this research, the planner should ensure:

- [ ] `getRenderTemplate()` checks `hasTemplate !== null` FIRST
- [ ] `getRenderTemplate()` checks `isPageType()` SECOND
- [ ] Returns `'Template:Property/Page'` (exact case)
- [ ] Falls back to `'Template:Property/Default'` (unchanged)
- [ ] No changes to DisplayStubGenerator
- [ ] Regeneration plan for existing display templates

## Files to Modify

| File | Change | Lines |
|------|--------|-------|
| `src/Schema/PropertyModel.php` | Update getRenderTemplate() with datatype fallback | ~5 |

**Total estimated changes:** ~5 lines of PHP

## Testing Considerations

| Scenario | Expected Result |
|----------|-----------------|
| Property with Has_template set | Uses custom template (unchanged behavior) |
| Page-type property without Has_template | Uses Template:Property/Page (new behavior) |
| Non-Page property without Has_template | Uses Template:Property/Default (unchanged) |
| After regeneration: Page-type values | Render as clickable links |
| Multi-value Page-type | Comma-separated clickable links |
