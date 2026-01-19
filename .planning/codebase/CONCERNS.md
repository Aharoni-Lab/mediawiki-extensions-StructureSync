# Codebase Concerns

**Analysis Date:** 2026-01-19

## Tech Debt

**Large God Class: SpecialSemanticSchemas.php**
- Issue: Single file at 1,963 lines handles all admin UI functionality
- Files: `src/Special/SpecialSemanticSchemas.php`
- Impact: Difficult to maintain, test, and extend. High cognitive load for modifications.
- Fix approach: Extract into smaller classes by responsibility:
  - OverviewController
  - GenerateController
  - ValidateController
  - HierarchyController
  - InstallController
  - Separate view renderers for HTML generation

**ExtensionConfigInstaller Complex SMW Workarounds**
- Issue: 880 lines of complex multi-pass installation logic to work around SMW's asynchronous property type registration
- Files: `src/Schema/ExtensionConfigInstaller.php`
- Impact: Fragile installation process, tight coupling to SMW internals, difficult to debug
- Fix approach: Document SMW behavior thoroughly; consider upstream contribution to SMW for synchronous mode

**Dead Code: Legacy Installation Methods**
- Issue: `showInstallConfigConfirmationLegacy()` method retained but unused
- Files: `src/Special/SpecialSemanticSchemas.php:743-864`
- Impact: Code bloat, maintenance burden
- Fix approach: Remove legacy method after confirming automated installer is stable

**Inline JavaScript in PHP**
- Issue: ~170 lines of JavaScript embedded via HEREDOC in PHP file
- Files: `src/Special/SpecialSemanticSchemas.php:561-728`
- Impact: No linting, no minification, poor separation of concerns, difficult to maintain
- Fix approach: Move to separate `.js` file in `resources/`, register via ResourceLoader

**Magic Namespace Constants**
- Issue: Hardcoded namespace numbers (102, 14, 3300) scattered throughout code
- Files:
  - `src/Store/WikiCategoryStore.php:218` (102 for Property)
  - `src/Store/WikiCategoryStore.php:223` (14 for Category)
  - `src/Store/PageCreator.php:182` (SMW_NS_PROPERTY)
- Impact: Fragile if namespace IDs change; unclear meaning
- Fix approach: Create constants class or use MediaWiki namespace service consistently

## Known Bugs

**No TODO/FIXME Comments Detected**
- The codebase has no explicit TODO or FIXME markers, which is unusual and suggests either excellent completion or potential hidden issues not documented.

## Security Considerations

**Rate Limiting Implementation**
- Risk: Rate limiting uses in-memory cache which doesn't persist across server restarts
- Files: `src/Special/SpecialSemanticSchemas.php:78-99`
- Current mitigation: Uses ObjectCache::getLocalClusterInstance()
- Recommendations: Consider persistent rate limiting for production environments; document that cluster cache is required for distributed deployments

**System User for Page Operations**
- Risk: All page operations use a system user "SemanticSchemas" with steal=true
- Files: `src/Store/PageCreator.php:29`
- Current mitigation: Operations require 'editinterface' permission before reaching PageCreator
- Recommendations: Audit all entry points to ensure permission checks occur; consider passing user context through for better audit trails

**API Token Validation**
- Risk: API endpoint does manual token validation rather than using MediaWiki's built-in mechanisms
- Files: `src/Api/ApiSemanticSchemasInstall.php:114-118`, `needsToken()` returns false
- Current mitigation: Manual matchEditToken check
- Recommendations: Consider using MediaWiki's standard token mechanism (PARAM_TYPE => 'csrf')

## Performance Bottlenecks

**getAllCategories() Full Scan**
- Problem: Reads ALL category pages from database then filters for SemanticSchemas-managed ones
- Files: `src/Store/WikiCategoryStore.php:93-116`
- Cause: No index on SemanticSchemas-managed marker; reads every category then checks each
- Improvement path: Use SMW semantic query to find only managed categories, or maintain category list in state

**Repeated Property Lookups**
- Problem: Same properties loaded multiple times during form/template generation
- Files:
  - `src/Generator/FormGenerator.php:214` - reads property in loop
  - `src/Generator/TemplateGenerator.php:53,134` - reads property multiple times
- Cause: No caching layer between generators and WikiPropertyStore
- Improvement path: Add property cache to generators or implement in-request memoization

**Hash Computation for All Entities**
- Problem: `computeAllSchemaHashes()` iterates all categories, properties, and subobjects on every generate
- Files: `src/Special/SpecialSemanticSchemas.php:1685-1709`
- Cause: No incremental hash tracking
- Improvement path: Compute hashes only for modified entities; use hook to track changes

## Fragile Areas

**SMW Dependency Timing**
- Files: `src/Schema/ExtensionConfigInstaller.php`
- Why fragile: Relies on SMW job queue completing before next layer starts; timing-sensitive
- Safe modification: Always test with job queue enabled; consider explicit queue flush
- Test coverage: Integration tests exist but may not cover all timing scenarios

**InheritanceResolver C3 Linearization**
- Files: `src/Schema/InheritanceResolver.php`
- Why fragile: Complex algorithm; incorrect implementation causes silent property inheritance bugs
- Safe modification: Extensive unit tests required for any changes; compare against Python MRO behavior
- Test coverage: Good unit tests in `tests/phpunit/unit/Schema/InheritanceResolverTest.php`

**Display Template Detection**
- Files: `src/Generator/DisplayStubGenerator.php`
- Why fragile: Uses marker-based detection to determine if template is user-customized
- Safe modification: Changing markers breaks detection; must update existing pages
- Test coverage: Limited

**PageForms Form Namespace**
- Files: `src/Generator/FormGenerator.php:346`
- Why fragile: Uses `\PF_NS_FORM` constant directly; fails if PageForms not loaded
- Safe modification: Add defensive check before using constant
- Test coverage: Only tested in integration environment

## Scaling Limits

**State Storage in MediaWiki Page**
- Current capacity: State JSON stored in `MediaWiki:SemanticSchemasState.json`
- Limit: Page content limits (typically ~2MB); slows with many tracked pages
- Scaling path: Consider separate database table for state tracking if managing >100 categories

**In-Memory Category Map**
- Current capacity: `InheritanceResolver` loads all categories into memory
- Limit: Memory exhaustion with thousands of categories
- Scaling path: Lazy loading or streaming approach for very large ontologies

## Dependencies at Risk

**Tight Coupling to SMW Internals**
- Risk: Uses SMW internal classes (`\SMW\StoreFactory`, `\SMW\ServicesFactory`, `\SMW\DIWikiPage`)
- Impact: SMW version updates may break functionality
- Migration plan: Abstract SMW interactions behind interface; test against multiple SMW versions

**PageForms Constants**
- Risk: Direct use of `PF_NS_FORM`, `PF_VERSION`
- Impact: PageForms updates could break form generation
- Migration plan: Check constant existence before use; add graceful degradation

## Missing Critical Features

**Rollback/Undo Support**
- Problem: No way to undo schema import or template regeneration
- Blocks: Safe experimentation with schema changes
- Files: No implementation exists
- Recommendation: Implement snapshot/restore functionality using MediaWiki's revision system

**Schema Export**
- Problem: No way to export current wiki state back to schema format
- Blocks: Round-trip editing, backup/restore workflows
- Files: Export mentioned in architecture but not implemented
- Recommendation: Implement SchemaExporter that reverses import flow

**Dry-Run Mode**
- Problem: No preview of what generate/import will change
- Blocks: Safe validation before applying changes
- Files: `previewInstallation()` exists only for base config
- Recommendation: Extend preview to all generation operations

## Test Coverage Gaps

**SpecialSemanticSchemas Not Unit Tested**
- What's not tested: All 1,963 lines of Special page logic
- Files: `src/Special/SpecialSemanticSchemas.php`
- Risk: UI regressions, permission bypasses, rate limit failures
- Priority: High

**FormGenerator Partially Tested**
- What's not tested: Subobject section generation, namespace targeting, parent property detection
- Files: `src/Generator/FormGenerator.php`
- Risk: Form generation bugs affect user data entry
- Priority: Medium

**DisplayStubGenerator Not Tested**
- What's not tested: All display template generation logic
- Files: `src/Generator/DisplayStubGenerator.php` (301 lines)
- Risk: Display regression affects page rendering
- Priority: Medium

**API Endpoints Not Tested**
- What's not tested: `ApiSemanticSchemasInstall`, `ApiSemanticSchemasHierarchy`
- Files: `src/Api/`
- Risk: API contract violations, security issues
- Priority: High

**Error Paths Largely Untested**
- What's not tested: Exception handling in catch blocks (28 catch statements across codebase)
- Files: All files with try/catch
- Risk: Silent failures, unexpected error messages
- Priority: Medium

---

*Concerns audit: 2026-01-19*
