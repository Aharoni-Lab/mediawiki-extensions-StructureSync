# Domain Pitfalls: SMW Display Templates

**Domain:** Semantic MediaWiki display templates for Page-type properties
**Researched:** 2026-01-19
**Confidence:** HIGH (verified against codebase + official SMW documentation)

---

## Critical Pitfalls

Mistakes that cause data corruption, broken links, or require significant rework.

### Pitfall 1: Namespace Prefix Stripping in Multi-Value Page Properties

**What goes wrong:** When storing multi-value Page-type properties with namespace restrictions (e.g., `allowedNamespace: "Property"`), using comma-separated values in `#set` treats the entire string as a single page reference instead of multiple values.

**Why it happens:** SMW's `#set` parser function interprets comma-separated values in a single parameter as a single page name containing commas, not as multiple values. This is compounded when namespace prefixes are involved because `Property:Name1, Property:Name2` is parsed as one malformed page reference.

**Consequences:**
- Page-type property values don't resolve to actual pages
- Semantic queries return no results or wrong results
- Form autocomplete fails to find existing values
- Data appears corrupted in Special:Browse

**Prevention:**
1. Use inline annotation syntax `[[Property::Namespace:Value]]` for multi-value Page properties instead of `#set`
2. Use `#arraymap` to iterate over comma-separated values and create individual annotations:
   ```
   {{#arraymap:{{{param|}}}|,|@@item@@|[[Property::Namespace:@@item@@]]|}}
   ```
3. For single-value properties, use conditional namespace prefixing:
   ```
   | Property = {{#if:{{{param|}}}|Namespace:{{{param|}}}|}}
   ```

**Detection:**
- Property values show as red links when they should be blue
- `[[Has property::+]]` queries return fewer results than expected
- Special:Browse shows raw comma-separated text instead of linked pages

**Phase to address:** Phase 1 (Core semantic template generation) - This is already implemented in `TemplateGenerator.php` via `generatePropertyLine()` and `generateInlineAnnotation()` methods.

**Source:** [Verified in codebase commit a10b649](https://github.com/labki-org/SemanticSchemas/commit/a10b649), [SMW Setting Values Documentation](https://www.semantic-mediawiki.org/wiki/Help:Setting_values)

---

### Pitfall 2: #arraymap Variable Name Collision

**What goes wrong:** When using `#arraymap` to process multi-value fields, the variable placeholder (e.g., `x`) appears within the property name or value, causing unintended substitutions.

**Why it happens:** `#arraymap` performs simple string substitution. If your property is named `Data export` and you use `x` as the variable, then `{{#arraymap:{{{values|}}}|,|x|[[Data export::x]]}}` will produce `[[Data eort::value]]` because the `x` in "export" gets replaced.

**Consequences:**
- Properties with mangled names are created
- Semantic data stored under wrong property names
- Difficult to diagnose because the pattern is inconsistent

**Prevention:**
1. Use double-letter or unique variable names: `@@item@@`, `xx`, `zz`
2. Avoid single common letters: `x`, `e`, `a`, `i`, `o`, `n`, `t`, `s`
3. Use template parameter syntax as the variable: `{{{@@val@@}}}`

**Detection:**
- Strange property names appearing in Special:Properties
- Properties missing expected values
- Data appears to be stored but queries don't find it

**Phase to address:** Phase 2 (Display template generation) - When implementing custom display templates that iterate over multi-value properties.

**Source:** [Page Forms Common Problems](https://www.mediawiki.org/wiki/Extension:Page_Forms/Common_problems)

---

### Pitfall 3: Namespace Configuration Order

**What goes wrong:** Custom namespaces (like `Subobject:`) are not recognized by SMW, causing Page-type properties targeting them to fail silently.

**Why it happens:** SMW's `$smwgNamespacesWithSemanticLinks` must be configured AFTER calling `enableSemantics()`, not before. If set before, the namespace constants aren't defined yet, and the configuration silently fails.

**Consequences:**
- Pages in custom namespaces don't store semantic data
- Queries for pages in custom namespaces return empty
- No error messages - failure is silent

**Prevention:**
1. Always configure `$smwgNamespacesWithSemanticLinks[NS_CUSTOM] = true;` AFTER `enableSemantics()` in LocalSettings.php
2. Verify namespace setup with `Special:SMWAdmin` diagnostics
3. After namespace changes, run `rebuildData.php` maintenance script

**Detection:**
- Check `Special:Properties` - properties show "Page" type but values don't link
- Run `php maintenance/rebuildData.php -v` and watch for namespace-related warnings
- Check error logs for "cannot be used as a page name" messages

**Phase to address:** Phase 0 (Extension installation/configuration) - Document in installation instructions.

**Source:** [SMW Namespaces Documentation](https://www.semantic-mediawiki.org/wiki/Help:Namespaces), [Custom Namespaces](https://www.semantic-mediawiki.org/wiki/Help:Custom_namespaces)

---

## Moderate Pitfalls

Mistakes that cause UX issues, maintenance burden, or require template rework.

### Pitfall 4: Expensive Parser Function Limits (#ifexist)

**What goes wrong:** Template fallback chains using `#ifexist` to check for property-specific templates hit MediaWiki's expensive parser function limit (default: 500 per page).

**Why it happens:** Each `#ifexist` call requires a database query to check page existence. Unlike static wikilinks, these results cannot be fully cached because the template parameters vary.

**Consequences:**
- Pages with many properties show errors: "Pages with too many expensive parser function calls"
- Template fallback silently stops working partway through the page
- Inconsistent display: some properties render with custom templates, others don't
- Degraded wiki performance under load

**Prevention:**
1. **Resolve templates at generation time, not render time.** Pre-compute which template to use when generating the display template, storing the resolved template name directly in wikitext.
2. **Cache template existence checks** in PHP/Lua rather than wikitext
3. **Use CSS-based fallbacks** where possible: `.property-default { }` overridden by `.property-email { }`
4. **Batch property types** - use one template for all text-type properties, one for all page-type, etc.

**Detection:**
- Check `Special:Statistics` for expensive parser function warnings
- Monitor page render times
- Look for category "Pages with too many expensive parser function calls"

**Phase to address:** Phase 2 (Display template generation) - Already addressed in SemanticSchemas by resolving `getRenderTemplate()` at generation time in `DisplayStubGenerator.php`.

**Source:** [MediaWiki Conditional Expressions](https://en.wikipedia.org/wiki/Help:Conditional_expressions)

---

### Pitfall 5: Link Display Text vs. Link Target Confusion

**What goes wrong:** Page-type property values display with namespace prefixes when users expect clean labels, or vice versa.

**Why it happens:** By default, SMW's Page-type properties render as full wikilinks including namespace. When using `link=none` in queries to handle display manually, templates must reconstruct links correctly using `{{FULLPAGENAME}}` for target and `{{PAGENAME}}` for display.

**Consequences:**
- Display shows `Property:Has name` when user expects `Has name`
- Links target wrong pages (missing namespace prefix)
- Inconsistent display across different templates

**Prevention:**
1. Understand the difference:
   - `{{FULLPAGENAME:{{{1}}}}}` = `Property:Has name` (for link target)
   - `{{PAGENAME:{{{1}}}}}` = `Has name` (for display text)
2. When using `link=none`, always construct links manually:
   ```
   [[{{FULLPAGENAME:{{{1}}}}}|{{PAGENAME:{{{1}}}}}]]
   ```
3. For Property/Link templates, strip namespace for display but preserve for linking
4. Store display labels separately from page references

**Detection:**
- Links show namespace prefixes in visible text
- Clicking links goes to wrong page or shows "page does not exist"
- Users report confusing display

**Phase to address:** Phase 2 (Property display templates) - Implement in `Template:Property/Page` template.

**Source:** [SMW Using Templates](https://www.semantic-mediawiki.org/wiki/Help:Using_templates), [SMW Template Format](https://www.semantic-mediawiki.org/wiki/Archive:Template_format)

---

### Pitfall 6: Empty Value Display Handling

**What goes wrong:** Display templates show empty table rows, blank labels, or "Unknown" text for properties that weren't filled in.

**Why it happens:** Template parameters with empty values still "exist" (they're empty strings, not undefined). Simple `#if` checks pass for empty strings unless using the correct syntax.

**Consequences:**
- Cluttered display with many empty rows
- Users confused by "empty" data appearing to be set
- Forms pre-populate with wrong defaults

**Prevention:**
1. Always use `{{{param|}}}` with trailing pipe for default empty string
2. Use `{{#if:{{{param|}}}|...}}` - the trailing `|` ensures empty string evaluates to false
3. For table rows, wrap entire row in conditional:
   ```
   {{#if:{{{param|}}}|
   {{!}}-
   ! Label
   {{!}} {{{param}}}
   }}
   ```
4. Consider `display=nonempty` option in Page Forms for form-driven display

**Detection:**
- Empty rows visible in tables
- "Edit" links for rows that have no data
- Special:Browse shows property with no value

**Phase to address:** Phase 2 (Display templates) - Implement conditional hiding in property templates.

**Source:** [SMW Hide Empty Rows](https://www.semantic-mediawiki.org/wiki/Help:Hide_empty_rows_in_table)

---

### Pitfall 7: Pipe Character Escaping in Template Parameters

**What goes wrong:** Property values containing pipe characters (`|`) break template calls, truncating values or causing parse errors.

**Why it happens:** Pipe characters are MediaWiki's parameter delimiter. When a property value like a URL `http://example.com?a=1&b=2` or a template with pipes is passed as a parameter, the pipe is interpreted as the start of the next parameter.

**Consequences:**
- Values truncated at first pipe character
- Template parse errors
- Malformed wikitext output
- URLs broken

**Prevention:**
1. Use `{{!}}` magic word (MW 1.24+) to escape pipes in wikitext
2. Use named parameters with `1=` prefix: `{{template|1=value with | pipe}}`
3. For URLs, use `{{#urlencode:}}` before passing to templates
4. In queries with template format, use `|link=none` and handle escaping in template

**Detection:**
- Values appear truncated
- Parse errors in page history
- URLs missing query parameters

**Phase to address:** Phase 2 (Property display templates) - Handle in `Template:Property/Link` and `Template:Property/Default`.

**Source:** [MediaWiki Pipe Escape](https://www.mediawiki.org/wiki/Extension:Pipe_Escape), [MediaWiki Templates](https://www.mediawiki.org/wiki/Help:Templates)

---

## Minor Pitfalls

Mistakes that cause annoyance but are easily fixable.

### Pitfall 8: Parameter Name Inconsistency Between Templates

**What goes wrong:** Semantic template uses `{{{full_name}}}`, display template uses `{{{fullname}}}`, form uses `{{{Full Name}}}`. Values don't pass through correctly.

**Why it happens:** Different developers/generators use different naming conventions. MediaWiki template parameters are case-sensitive and whitespace-sensitive.

**Consequences:**
- Values entered in forms don't display
- Semantic data stored but display is empty
- Debugging is frustrating due to silent failures

**Prevention:**
1. **Centralize naming logic** - Use a single helper function for all name transformations
2. **Document the convention** - "All parameters use lowercase with underscores"
3. **Test the full chain** - Form -> Dispatcher -> Semantic -> Display

**Detection:**
- Values appear in Special:Browse but not in page display
- Form saves successfully but page looks empty

**Phase to address:** Already addressed in SemanticSchemas via `NamingHelper::propertyToParameter()`.

---

### Pitfall 9: Section Edit Links in Templates

**What goes wrong:** Templates with section headings (`== Section ==`) get edit links that edit the template, not the page content.

**Why it happens:** MediaWiki's section editing feature doesn't distinguish between headings in transcluded templates and headings in the page itself.

**Consequences:**
- Users accidentally edit templates instead of their content
- Confusing UX
- Template vandalism risk

**Prevention:**
1. Add `__NOEDITSECTION__` magic word inside templates with headings
2. Or use CSS to hide edit links: `.source-semanticschemas .mw-editsection { display: none; }`
3. Avoid section headings in templates when possible

**Detection:**
- "Edit" links next to template-generated headings
- Users report being taken to template page when trying to edit

**Phase to address:** Phase 2 (Display template generation) - Add `__NOEDITSECTION__` to generated templates.

**Source:** [SMW Semantic Templates](https://www.semantic-mediawiki.org/wiki/Help:Semantic_templates)

---

### Pitfall 10: Page Title Length Limit (255 bytes)

**What goes wrong:** Page-type property values exceeding 255 bytes cause silent truncation or errors.

**Why it happens:** MediaWiki page titles have a hard limit of 255 bytes (not characters - multibyte UTF-8 characters count more). This limit applies to Page-type property values since they reference wiki pages.

**Consequences:**
- Long page names silently truncated
- Links appear broken
- Semantic queries return unexpected results

**Prevention:**
1. Validate Page-type property values during form submission
2. Use Text type instead of Page type for long-form content references
3. Document maximum lengths in form help text

**Detection:**
- Very long property values appear truncated
- "Invalid title" errors in logs

**Phase to address:** Phase 3 (Form validation) - Add length validation to Page-type fields.

**Source:** [SMW Type Page](https://www.semantic-mediawiki.org/wiki/Help:Type_Page)

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Semantic template generation | Namespace prefix stripping (#1) | Use inline annotations for multi-value Page properties |
| Display template generation | #ifexist limits (#4), Empty values (#6) | Resolve templates at generation time, use conditionals |
| Property display templates | Link text confusion (#5), Pipe escaping (#7) | Use FULLPAGENAME/PAGENAME correctly, escape special chars |
| Form generation | Parameter naming (#8), Title limits (#10) | Use NamingHelper consistently, validate lengths |
| Custom namespace setup | Configuration order (#3) | Document enableSemantics() dependency |
| Multi-value fields | #arraymap collision (#2) | Use unique variable placeholders |

---

## Checklist: Before Deploying Display Templates

- [ ] Multi-value Page properties use inline annotations, not #set
- [ ] #arraymap uses unique variable names (not `x`, `e`, etc.)
- [ ] Custom namespaces configured AFTER enableSemantics()
- [ ] No #ifexist in templates (resolve at generation time)
- [ ] Display text vs. link target handled correctly for Page properties
- [ ] Empty values handled with proper `#if` syntax
- [ ] Pipe characters escaped in URL-type values
- [ ] Parameter names match across semantic/dispatcher/display templates
- [ ] `__NOEDITSECTION__` added to templates with headings
- [ ] Page-type values validated for 255-byte limit

---

## Sources

### Official Documentation (HIGH confidence)
- [SMW Setting Values](https://www.semantic-mediawiki.org/wiki/Help:Setting_values)
- [SMW Type Page](https://www.semantic-mediawiki.org/wiki/Help:Type_Page)
- [SMW Namespaces](https://www.semantic-mediawiki.org/wiki/Help:Namespaces)
- [SMW Hide Empty Rows](https://www.semantic-mediawiki.org/wiki/Help:Hide_empty_rows_in_table)
- [SMW Using Templates](https://www.semantic-mediawiki.org/wiki/Help:Using_templates)
- [SMW Semantic Templates](https://www.semantic-mediawiki.org/wiki/Help:Semantic_templates)
- [Page Forms Common Problems](https://www.mediawiki.org/wiki/Extension:Page_Forms/Common_problems)
- [MediaWiki Templates](https://www.mediawiki.org/wiki/Help:Templates)

### Codebase Evidence (HIGH confidence)
- `TemplateGenerator.php` - `generatePropertyLine()`, `generateInlineAnnotation()` methods
- `NamingHelper.php` - Centralized parameter naming
- `DisplayStubGenerator.php` - Generation-time template resolution
- Commit a10b649 - Fix for namespace prefix stripping issue
