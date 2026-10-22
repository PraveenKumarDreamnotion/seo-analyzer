# Results view design review (22 October 2026)

Sandeep's review of the on-page results, ported from `halo-audit-2` PR #3 (commit `395e244`).
The changes are listed in `SEO-plugin/CHANGELOG.md` under "Changed (results design review)".

| File | What it shows |
|---|---|
| `before-cards.png` | The area cards before the review |
| `after-desktop.png` | The reviewed results view, desktop width |
| `after-narrow.png` | The same, narrow width |
| `after-speed-pending.png` | The Speed card while its test is still running |

The screenshots were taken before the follow-up fixes: buttons are now a darker olive (`#6d6d00`) for
contrast, and the fonts load from the plugin itself.

## Not done yet

1. Number format in the server's evidence text: "2702 words" should read "2,702 words".
2. Freshness evidence "ACTIVE: last update 11 days ago" should read "Updated 11 days ago" (`SitemapFreshness`).
3. A "Try the speed test again" button. Today a PageSpeed answer with no result is saved as done,
   so a retry needs a server change first.
