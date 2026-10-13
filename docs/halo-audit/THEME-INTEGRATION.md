# Halo Audit 2.0: theme integration

Date: 2026-10-13. Scope: the two tool pages that frame the `[seo_analyzer]` widget in a two-column panel, `/ai-tools/seo-analysis/` (page 917) and `/ai-tools/competitor-analysis/` (page 937).

The files below are **not in this repository**. They live in the site's child theme and in the `tm-blocks` plugin. They were changed on the local site only and must ship with the 2.0 plugin.

## What the widget promises the theme

The theme reads one thing from the widget: the `hidden` attribute of `.halo-audit__results`.

| Widget state | `.halo-audit__results` |
|---|---|
| First load | `hidden` |
| Empty form submitted | `hidden` |
| Audit running | `hidden` |
| Audit failed or the address was refused | `hidden` |
| Report on the page (free view, email form, unlocked findings) | not `hidden` |
| Another audit started | `hidden` again |

`public/js/halo-audit.js` sets this in `Widget.prototype.onSubmit` and `Widget.prototype.render`. If that changes, the two theme scripts below need the same change.

The theme also styles these widget classes: `.halo-audit`, `.halo-audit__title`, `.halo-audit__form`, `.halo-audit__button`, `.halo-audit__groups`, `.halo-audit__group`, `.halo-audit__findings`, `.halo-audit__section`.

## Files changed outside this repository

All three changes only add lines. The rules and the script branch for the 1.x widget are still there, so the theme works with either plugin version and can be deployed before or after the plugin.

| File | Change |
|---|---|
| `wp-content/themes/generatepress_child/assets/js/tm-seo-analysis.js` | 15 lines added in `auditToggle` |
| `wp-content/themes/generatepress_child/assets/js/tm-competitor-analysis.js` | The same 15 lines, with `tmca-running` |
| `wp-content/plugins/tm-blocks/assets/css/tm-blocks.css` | 31 lines added in the page 917 block and 31 in the page 937 block |

### Script (page 917; page 937 is the same with `.tmca-audit-panel` and `tmca-running`)

Added at the top of `auditToggle`, after the `panel` lookup:

```js
var results = panel.querySelector('.halo-audit__results');
if (results) {
  var sync = function () { panel.classList.toggle('tmsa-running', !results.hidden); };
  new MutationObserver(sync).observe(results, { attributes: true, attributeFilter: ['hidden'] });
  sync();
  return;
}
```

The script adds no submit listener. The plugin owns the form.

### Styles (page 917; page 937 is the same with `page-id-937`, `.tmca-audit-col`, `.tmca-audit-panel`, `tmca-running`)

Added after the existing `.tmsa-running` rules. Every rule is scoped to `body.tm-blocks-page.page-id-917`.

```css
.tmsa-audit-col .halo-audit { max-width: none !important; margin: 0 !important; }
.tmsa-audit-col .halo-audit__title { display: none !important; }
.tmsa-audit-panel:not(.tmsa-running) .tmsa-audit-col .halo-audit__form { grid-template-columns: 1fr !important; }
.tmsa-audit-col .halo-audit input {
  padding: 13px 16px !important; font-family: var(--font-body) !important; font-size: 16px !important; color: var(--text-primary) !important;
  background: var(--cream) !important; border: 1px solid var(--border-medium) !important; border-radius: var(--radius) !important;
}
.tmsa-audit-col .halo-audit input:focus { border-color: var(--olive) !important; }
.tmsa-audit-col .halo-audit__button { border-radius: var(--radius) !important; font-family: var(--font-body) !important; }
main#main .tmsa-audit-col ul.halo-audit__groups { padding-left: 0 !important; margin-left: 0 !important; list-style: none !important; }
@media (min-width: 901px) {
  .tmsa-audit-panel.tmsa-running .halo-audit__groups { display: grid !important; grid-template-columns: repeat(4, 1fr) !important; gap: 16px !important; border-top: none !important; }
  .tmsa-audit-panel.tmsa-running .halo-audit__group { padding: 16px 18px !important; background: var(--cream) !important; border: 1px solid var(--border-light) !important; border-radius: var(--radius) !important; }
  .tmsa-audit-panel.tmsa-running .halo-audit__findings { column-count: 2; column-gap: 48px; }
  .tmsa-audit-panel.tmsa-running .halo-audit__section { break-inside: avoid-column; }
  .tmsa-audit-panel.tmsa-running .halo-audit__section:first-child { margin-top: 0 !important; }
}
```

Why each rule is there:

- **Width.** The panel is a two-column grid. It becomes one column only when the panel has the `tmsa-running` class. The plugin's own stylesheet also caps `.halo-audit` at 880px, so the cap is lifted inside the card.
- **Title.** The page already has `h3.tmsa-audit-title` above the shortcode. The widget's own `h2` repeated it and came after an `h3`.
- **Form.** The widget lays the form out three across from 720px of viewport width. In the half-width card that is too tight, so the form is stacked until the card is full width.
- **Focus.** The widget's 2px focus outline is left as it is.

## Deploying the theme files

1. Copy the three files to the site.
2. WP Rocket keeps minified copies under `wp-content/cache/min/`. They are not rebuilt when the source changes. Clear the WP Rocket cache (or at least the minified CSS and JS) after copying the files. On the local site the old script kept being served until the three minified copies were removed.
3. If "Remove Unused CSS" is on (it is off on the local site), add `halo-audit`, `tmsa-running` and `tmca-running` to its safelist. These classes are added by scripts after load, so their rules would be stripped.
4. "Delay JavaScript execution" is on locally. The widget and the theme script both start on the visitor's first interaction, in order. No exclusion was needed.

## Not changed

- Page content, page IDs, slugs, metadata and the shortcode attributes on pages 917 and 937.
- The plugin. No file under `SEO-plugin/` changed for this work.
- `functions.php` line 1300 and `style.css` lines 2237 to 2258 in the child theme still name `.seo-analyzer-widget form.seo-analyzer-form button`. They match nothing with the 2.0 widget and do no harm. They gave the old submit button the Divi button class.
- The `REPORT + MODAL restyle` blocks for pages 917 and 937 in `tm-blocks.css` only match the 1.x report and popups. They can be removed once 1.x is retired everywhere.

## Open content questions (not decided here)

1. **"No signup" copy.** The full findings and the PDF now need a name and an emailed code. These lines say otherwise:
   - Page 917 hero: "Free, in your browser, no signup."
   - Page 917 card note: "Free. No signup. Your report is generated on this page."
   - Page 917 comparison list: "Runs on demand, right here, no signup"
   - Page 917 FAQ: "Yes. No signup, no email gate, no card."
   - Page 937 has the same four lines in its own wording, including "Shareable PDF report, no signup".

   Suggested wording, for approval: "Free. Your score appears on this page. Confirm your email to see every finding and download the PDF." For the FAQ: "Yes. The score is free and needs no account. To see every finding and download the PDF we ask for your name and email and send a 6-digit code. No card."
2. **Page 937.** Competitor comparison was removed in 2.0. The page still promises it throughout: the H1 "see exactly where their page beats yours", "Three fields. One side-by-side verdict.", "Two pages, one keyword, compared across 8 factor categories.", the two-ring mock report, and the FAQ. The widget on it now audits one page. The page needs a decision: rewrite it as a single-page audit, redirect it to `/ai-tools/seo-analysis/`, or hold it until a comparison exists.
3. **Other placements of `[seo_analyzer]`:** posts 908 and 1569, page 1023, draft page 3378, and Divi layouts 1012 and 3377. Posts 908 and 1569 serve the widget with no theme panel around it, so the plugin's own layout applies there. Page 1023 holds the shortcode in its stored content, but the served page does not show the widget. Layout 1012 and page 1023 still pass `comparison` and `competitor` attributes, which are ignored. The other placements were not opened in a browser for this work.
