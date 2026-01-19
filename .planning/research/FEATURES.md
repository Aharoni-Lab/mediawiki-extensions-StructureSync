# Feature Landscape: Page-Type Property Display

**Domain:** SMW property value display for Page-type properties
**Researched:** 2026-01-19
**Confidence:** HIGH (verified against SMW official documentation and codebase analysis)

## Table Stakes

Features users expect. Missing = property display feels incomplete or broken.

| Feature | Why Expected | Complexity | Dependencies | Notes |
|---------|--------------|------------|--------------|-------|
| **Clickable wiki links** | Page-type values MUST render as links. SMW default behavior. | Low | MediaWiki link syntax | `[[Namespace:PageName\|Display]]` |
| **Namespace prefix support** | Properties like "Has parent category" store `Category:Foo`, must render correctly | Low | None | Already implemented in semantic templates |
| **Red/blue link differentiation** | Standard MediaWiki behavior - users expect broken links to show red | None | MediaWiki core | Automatic with standard wikilinks |
| **Multi-value comma separation** | Multiple values must be visually separated | Low | None | Standard separator: `, ` |
| **Empty value handling** | No value = no broken markup | Low | None | `{{#if:}}` guards |
| **Display title (pipe trick)** | Hide namespace prefix in display: `Category:Foo` shows as `Foo` | Low | None | `[[Category:Foo\|Foo]]` |

### Why These Are Table Stakes

1. **Clickable wiki links**: SMW's Page datatype "displays them as a link" by default. Any property display that shows Page values as plain text breaks user expectations.

2. **Namespace prefix support**: SemanticSchemas already has ~12 Page-type properties with `allowedNamespace` (Category, Property, Subobject). These MUST render with correct namespace.

3. **Multi-value support**: Properties like "Has required property" have `allowsMultipleValues: true`. Comma-separated values without visual separation would be unreadable.

## Differentiators

Features that set product apart. Not expected, but valued.

| Feature | Value Proposition | Complexity | Dependencies | Notes |
|---------|-------------------|------------|--------------|-------|
| **Custom link labels** | Show "Parent Categories" instead of raw page names | Medium | Property label metadata | Uses `Display label` property |
| **Tooltip on hover** | Show property description or page summary | Medium | CSS/JS, SMW `#info` | SMW supports via `#info` parser function |
| **Link to non-existent pages (smart red links)** | Encourage page creation by showing what's missing | Low | None | Default MediaWiki behavior, but explicit support |
| **Configurable display templates per property** | Email shows as mailto:, URL as clickable link, Page as wiki link | Medium | Template system | Already partially implemented (`Has template`) |
| **Sorting multi-values alphabetically** | Cleaner display for long lists | Low | `#arraymap` | Sort before display |
| **Truncation with "show more"** | Handle properties with many values gracefully | Medium | CSS/JS | e.g., show first 5, expand for rest |
| **Category badge styling** | Visual distinction for category links vs regular page links | Low | CSS | Different background/icon for `Category:*` |

### Why These Are Differentiators

1. **Custom link labels**: Wikipedia-style wikis use `[[Page|Custom Label]]`. SemanticSchemas can auto-generate these from property metadata.

2. **Configurable templates**: Already in the system (`Property/Default`, `Property/Email`, `Property/Link`). A `Property/Page` template would complete the set.

3. **Smart multi-value display**: Most SMW implementations just dump comma-separated values. Sorting and truncation show polish.

## Anti-Features

Features to explicitly NOT build. Common mistakes in this domain.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **`#ifexist` for every link** | Performance killer - each call is a database query. On pages with many Page properties, this destroys performance. | Trust MediaWiki's native red/blue link handling |
| **Complex JavaScript link enhancement** | Breaks parser cache, adds load time, accessibility issues | Use pure wikitext solutions |
| **Automatic page creation prompts** | Intrusive UX, permission issues, edit war potential | Let red links be red links |
| **Live preview of linked page content** | Scope creep, performance, complexity | Keep display focused on property values |
| **Custom link colors per property type** | CSS complexity, accessibility (color blindness), maintenance burden | Use semantic HTML classes instead, let themes handle |
| **Inline editing of linked pages** | Massive scope creep, permission complexity, save conflict handling | Out of scope for property display |
| **Recursive property display** | If Page A links to Page B, showing B's properties inline creates infinite loops and performance issues | Show link only, let user navigate |

### Why These Are Anti-Features

1. **`#ifexist` overuse**: "Even if the page does not exist, the ParserFunction still creates an invisible link, which shows up in Whatlinkshere" (MediaWiki docs). Each call adds database overhead.

2. **JavaScript link enhancement**: MediaWiki's parser cache is key to performance. JS that modifies links post-render breaks caching and creates FOUC (flash of unstyled content).

3. **Recursive property display**: Consider "Has parent category" pointing to another Category page. Showing that page's properties would show ITS "Has parent category"... infinite loop.

## Feature Dependencies

```
Clickable wiki links (base)
    |
    +-- Namespace prefix support
    |       |
    |       +-- Display title (pipe trick)
    |
    +-- Multi-value comma separation
    |       |
    |       +-- Sorting multi-values
    |       |
    |       +-- Truncation with "show more"
    |
    +-- Custom display templates
            |
            +-- Property/Page template
```

**Critical path:** Clickable links -> Namespace support -> Multi-value handling

## MVP Recommendation

For MVP, prioritize:

1. **Clickable wiki links** - Core functionality
2. **Namespace prefix handling** - Already needed for existing Page-type properties
3. **Multi-value comma separation** - Essential for readability
4. **Display title (hide namespace in display)** - Polish, but low complexity
5. **Empty value handling** - Defensive, prevents broken markup

### Implementation Approach

Create `Template:Property/Page` that:

```wikitext
<includeonly>{{#if:{{{value|}}}|{{#arraymap:{{{value|}}}|,|@@v@@|[[{{{namespace|}}}:@@v@@{{!}}@@v@@]]|, }}|}}</includeonly>
```

Key elements:
- `#if` guard for empty values
- `#arraymap` for multi-value support
- Namespace prefix in link target
- Pipe trick for clean display
- `, ` separator between values

Defer to post-MVP:
- **Tooltips**: Requires CSS/JS integration, adds complexity
- **Truncation**: Nice-to-have, not essential
- **Category badge styling**: Cosmetic, theme-dependent

## Complexity Assessment

| Feature | Lines of Code | Testing Effort | Risk |
|---------|---------------|----------------|------|
| Property/Page template | ~10 wikitext | Low | Low |
| Namespace parameter passing | ~20 PHP | Medium | Low |
| Multi-value handling | Already exists (`#arraymap`) | Low | Low |
| Display title support | ~5 wikitext | Low | Low |
| **Total MVP** | **~35 lines** | **Low-Medium** | **Low** |

## Sources

### Official SMW Documentation (HIGH confidence)
- [Help:Type Page](https://www.semantic-mediawiki.org/wiki/Help:Type_Page) - Page datatype behavior
- [Help:Displaying information](https://www.semantic-mediawiki.org/wiki/Help:Displaying_information) - Property value display
- [Help:Using templates](https://www.semantic-mediawiki.org/wiki/Help:Using_templates) - Template-based display
- [Help:Semantic templates](https://www.semantic-mediawiki.org/wiki/Help:Semantic_templates) - Infobox patterns

### MediaWiki Documentation (HIGH confidence)
- [Pipe trick](https://www.mediawiki.org/wiki/Pipe_trick) - Namespace hiding in links
- [Help:Links](https://www.mediawiki.org/wiki/Help:Links) - Red/blue link behavior
- [Extension:HidePrefix](https://www.mediawiki.org/wiki/Extension:HidePrefix) - Namespace prefix hiding

### Codebase Analysis (HIGH confidence)
- `/home/daharoni/dev/SemanticSchemas/resources/extension-config.json` - Existing Page-type properties
- `/home/daharoni/dev/SemanticSchemas/src/Generator/TemplateGenerator.php` - Current namespace handling
- `/home/daharoni/dev/SemanticSchemas/src/Generator/DisplayStubGenerator.php` - Display template system
