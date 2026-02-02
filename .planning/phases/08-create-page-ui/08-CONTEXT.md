# Phase 8: Create Page UI - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Special page (Special:CreateSemanticPage) where users select multiple categories from a hierarchy tree, see a live preview of merged/deduplicated properties, name the page, and submit to generate a composite form via Special:FormEdit. Requires edit permissions. The API endpoint (Phase 7) and composite form generation (Phase 6) are already built — this phase wires them into a user-facing UI.

</domain>

<decisions>
## Implementation Decisions

### Category selection
- No auto-select on parent check — each category is independent (checking "Vehicle" does NOT check "Car" or "Truck")
- Tree starts with first level expanded (root categories show direct children; deeper levels collapsed)
- Tree browsing only — no search/filter input
- Selected categories shown as removable chip/tag list outside the tree (in addition to checkboxes in tree)

### Property preview
- Live update on every check/uncheck (with debounce), no manual "Preview" button
- Each property shows: name, datatype, and required/optional status
- Datatype conflicts between categories are impossible (properties are wiki-global entities — Phase 5 decision), so no conflict UI needed

### Namespace resolution
- When selected categories have conflicting target namespaces, user must pick which namespace the page goes in
- Namespace picker appears in the preview area during conflicts

### Submission flow
- Submit redirects straight to Special:FormEdit for the new page — no intermediate confirmation
- Submit button uses progressive disclosure: disabled until at least one category is selected and a page name is entered
- Page name field generates the composite form on submit

### Empty state
- When no categories are selected, preview area shows instructional text (e.g., "Select categories from the tree to preview properties")

### Claude's Discretion
- Shared vs category-specific property visual distinction (grouped separately vs inline badges)
- Page name input positioning relative to tree and preview
- Page name clash behavior (block vs warn-but-allow when page already exists)
- Namespace picker widget type (dropdown vs radio) and whether to show namespace when no conflict
- API error feedback approach (inline replacement vs toast notification)
- Loading state design (spinner vs skeleton)
- Overall styling approach (standard MediaWiki OOUI vs custom-but-compatible)

</decisions>

<specifics>
## Specific Ideas

- Namespace conflict is a real scenario: different categories may target different namespaces, and the user needs to resolve this during page creation (not silently pick one)
- The flow is: select categories -> see properties -> name page -> submit -> land in FormEdit ready to fill in values

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-create-page-ui*
*Context gathered: 2026-02-02*
