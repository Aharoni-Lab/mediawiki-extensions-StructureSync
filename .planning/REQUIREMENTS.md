# Requirements: SemanticSchemas v0.2.0

**Defined:** 2026-02-02
**Core Value:** Schema definitions are the single source of truth; all wiki artifacts are generated from schemas

## v0.2.0 Requirements

Requirements for multi-category page creation milestone. Each maps to roadmap phases.

### Workflow

- [ ] **WF-01**: All v0.2.0 work done in a feature branch, delivered as a pull request to main

### Required/Optional Conflict Resolution (Bug Fix)

- [ ] **FIX-01**: CategoryModel resolves required+optional conflict by promoting to required instead of throwing
- [ ] **FIX-02**: SubobjectModel resolves required+optional conflict by promoting to required instead of throwing
- [ ] **FIX-03**: SchemaValidator warns (not errors) when a property appears in both required and optional
- [ ] **FIX-04**: When combining categories, if a property is required in any category and optional in another, required wins
- [ ] **FIX-05**: When combining categories, if a subobject is required in any category and optional in another, required wins
- [ ] **FIX-06**: Existing schemas that don't have conflicts continue working identically

### Conditional Templates

- [ ] **TMPL-01**: All semantic templates wrap `#set` calls in `#if` conditions to prevent empty value overwrites
- [ ] **TMPL-02**: Existing single-category templates continue working after conditional `#set` change
- [ ] **TMPL-03**: Multi-value properties use `+sep` parameter instead of manual separators inside `#if` blocks

### Property Resolution

- [ ] **RESO-01**: MultiCategoryResolver resolves properties across multiple selected categories
- [ ] **RESO-02**: Shared properties (same name across categories) are identified and deduplicated
- [ ] **RESO-03**: Property ordering follows C3 linearization precedence within each category
- [ ] **RESO-04**: Each resolved property includes source attribution (which category defines it)
- [ ] **RESO-05**: Resolver detects conflicting property datatypes across categories and reports errors
- [ ] **RESO-06**: When a property is required in any selected category, it is required in the composite form

### Composite Form Generation

- [ ] **FORM-01**: CompositeFormGenerator produces a single PageForms form with multiple `{{{for template}}}` blocks
- [ ] **FORM-02**: Shared properties appear once in the first template section; conditional `#set` handles storage in other templates
- [ ] **FORM-03**: Each template section has a label identifying the category
- [ ] **FORM-04**: All selected categories are assigned on page save via `[[Category:X]]` wikilinks
- [ ] **FORM-05**: Generated composite form is saved as a wiki Form: page

### API Endpoint

- [ ] **API-01**: API endpoint accepts multiple category names and returns resolved/deduplicated property data
- [ ] **API-02**: API response includes shared properties, category-specific properties, and any conflicts
- [ ] **API-03**: API is registered in extension.json and follows existing ApiBase pattern

### Create Page UI

- [ ] **UI-01**: New Special page (e.g., `Special:CreateSemanticPage`) accessible to users with edit permissions
- [ ] **UI-02**: Collapsible hierarchy tree with checkboxes for category selection
- [ ] **UI-03**: Live AJAX preview shows merged/deduplicated properties as categories are selected
- [ ] **UI-04**: Page name input field for the new page
- [ ] **UI-05**: Submit triggers composite form generation and redirects to Special:FormEdit
- [ ] **UI-06**: JavaScript module registered via ResourceLoader in extension.json

### State Management

- [ ] **STATE-01**: StateManager uses template-level hashing instead of page-level hashing
- [ ] **STATE-02**: Dirty detection correctly handles multi-category pages (no false positives)
- [ ] **STATE-03**: Existing single-category state tracking continues working after refactor

## Future Requirements

Deferred to later milestones. Tracked but not in current roadmap.

### Enhanced UX

- **UX-01**: Template section collapse/expand (accordion) in composite forms
- **UX-02**: Quick-create presets for common category combinations
- **UX-03**: Category removal data cleanup for existing multi-category pages

### Advanced Validation

- **VAL-01**: Dry-run validation checking all constraints before page creation
- **VAL-02**: Conditional property visibility based on category selection

### Bulk Operations

- **BULK-01**: Create multiple pages with same category combination

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Auto-select parent categories | Explicit selection only — user decides category membership |
| Merge schemas into single template | Maintain category identity with separate `{{{for template}}}` blocks |
| Inline page creation | Use existing FormEdit workflow — no need to reinvent |
| Custom display template per composite form | Reuse existing display stubs per category |
| Drag-and-drop category ordering | Unnecessary complexity for v0.2.0 |
| Subobject deduplication across categories | Complex edge case — defer to post-MVP |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| WF-01 | — | Pending |
| FIX-01 | — | Pending |
| FIX-02 | — | Pending |
| FIX-03 | — | Pending |
| FIX-04 | — | Pending |
| FIX-05 | — | Pending |
| FIX-06 | — | Pending |
| TMPL-01 | — | Pending |
| TMPL-02 | — | Pending |
| TMPL-03 | — | Pending |
| RESO-01 | — | Pending |
| RESO-02 | — | Pending |
| RESO-03 | — | Pending |
| RESO-04 | — | Pending |
| RESO-05 | — | Pending |
| RESO-06 | — | Pending |
| FORM-01 | — | Pending |
| FORM-02 | — | Pending |
| FORM-03 | — | Pending |
| FORM-04 | — | Pending |
| FORM-05 | — | Pending |
| API-01 | — | Pending |
| API-02 | — | Pending |
| API-03 | — | Pending |
| UI-01 | — | Pending |
| UI-02 | — | Pending |
| UI-03 | — | Pending |
| UI-04 | — | Pending |
| UI-05 | — | Pending |
| UI-06 | — | Pending |
| STATE-01 | — | Pending |
| STATE-02 | — | Pending |
| STATE-03 | — | Pending |

**Coverage:**
- v0.2.0 requirements: 33 total
- Mapped to phases: 0
- Unmapped: 33 (pending roadmap creation)

---
*Requirements defined: 2026-02-02*
*Last updated: 2026-02-02 after scope adjustments (branch/PR, separate special page, required/optional bug fix)*
