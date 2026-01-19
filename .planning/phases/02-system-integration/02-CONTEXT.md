# Phase 2: System Integration - Context

**Gathered:** 2026-01-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Page-type properties automatically use Template:Property/Page through smart fallback logic. When a Page-type property has a single `Allows_value_from_namespace`, the display template restores the namespace prefix that PageForms strips during storage.

</domain>

<decisions>
## Implementation Decisions

### Template fallback logic
- PropertyModel.getRenderTemplate() checks: custom template → Page-type → default
- Page-type properties without custom template use `Template:Property/Page`
- Other property types fall back to `Template:Property/Default`

### Namespace prefix restoration
- PageForms strips namespace prefix when a property allows values from exactly one namespace
- Template:Property/Page must restore this prefix when rendering wiki links
- Source: Read `Allows_value_from_namespace` from the property's SMW annotation
- Implementation: Template logic (not PHP generator)
- Condition: Only applies when exactly one namespace is allowed; multiple namespaces or no restriction means PageForms preserves the prefix

### Claude's Discretion
- Exact SMW query syntax in template for reading property annotations
- How DisplayStubGenerator determines which template to call
- Error handling for malformed property annotations

</decisions>

<specifics>
## Specific Ideas

- PageForms native behavior: strips namespace when form pulls from single namespace
- We need to "add back" what PageForms removes
- The property definition (SMW annotation) is the source of truth for namespace

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 02-system-integration*
*Context gathered: 2026-01-19*
