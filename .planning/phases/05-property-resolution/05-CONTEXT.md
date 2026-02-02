# Phase 5: Property Resolution - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Multi-category property resolver that accepts one or more category names and returns a merged, deduplicated property list with source attribution. Handles shared properties, required/optional promotion, and C3 ordering. Consumed by Form Generation (Phase 6), API (Phase 7), and UI (Phase 8). Subobjects are treated identically to properties throughout.

</domain>

<decisions>
## Implementation Decisions

### Shared property deduplication
- A property is identified by its wiki page title — globally unique, no ambiguity possible
- Two categories referencing the same property name always reference the same `Property:X` with identical definition
- No datatype conflict is possible by design (properties are wiki-global entities)
- When a property is required in any selected category, silently promote to required (no warning — consistent with Phase 3 silent promotion pattern)

### Conflict handling
- No datatype conflict checking needed — impossible by design, skip the check entirely
- No per-category property overrides exist (description, allowed values, display templates are property-level, not category-level)
- Disjoint categories (no shared properties) are a normal case — no notice needed

### Resolution output structure
- Subobjects handled identically to properties — same deduplication, same merging rules, same output structure
- Single-category input is valid — resolver works with 1+ categories, always the entry point

### Edge cases in merging
- Single category: returns that category's properties as-is (resolver is always the entry point)
- Diamond inheritance: treated the same as any shared property — just deduplicate, no special handling
- Empty categories: valid input, contribute no properties but still tag the page with their category

### Claude's Discretion
- Whether to return a structured result object with accessors or raw data arrays
- Source attribution approach (list all contributing categories vs shared flag)
- Shared property presentation (deduplicated group vs per-category with flag)
- Property ordering strategy (schema order vs alphabetical)
- Input interface (accept category names vs pre-loaded CategoryModel objects)
- Whether to flatten inherited properties first via InheritanceResolver before cross-category merge

</decisions>

<specifics>
## Specific Ideas

- "A property is referred to using that property's wiki page title. It will not be possible for 2 categories to refer to the same named property where that property differs."
- "We treat subobjects identically to how we treat properties. You can think of subobjects as just a defined group of properties."
- Resolver should work as the universal entry point for property resolution — even single-category pages go through it

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 05-property-resolution*
*Context gathered: 2026-02-02*
