# Halo Audit 2.0: acceptance notes (Task 22)

Date: 2026-10-13
Branch: `halo-audit-engine`, made from `main` at `dc8ec62` (the merge of the Task 2 hotfix).
Scope of this run: local only. Nothing was pushed, merged or deployed. No request was made to production or staging.

## Environment

| Item | Value |
|---|---|
| Unit tests | PHP 8.0.30 (`php:8.0-cli` container), PHPUnit 9.6.38 |
| Integration tests | `@wordpress/env` 10.39.0, WordPress image `wordpress:php8.1` (PHP 8.1.34), MariaDB 12.3 |
| Local site | DevKinsta, WordPress 7.1.3, PHP 8.3.3 for web requests, MariaDB 10.5.17 |
| Composer | 2.10.3 in the `composer:2` container, platform pinned to PHP 8.0.30 |
| mPDF | 8.3.1 |

What ran on which PHP version:

- **PHP 8.0.30:** the unit suite (engine only), the lint run, a load of all 72 engine and WordPress-layer class files outside WordPress, and one direct mPDF render. The WordPress layer was not exercised inside WordPress on 8.0.
- **PHP 8.1.34:** the integration suite and the wp-env end-to-end run.
- **PHP 8.3.3:** the end-to-end and browser runs on the DevKinsta site.

Outside calls made by this run: page fetches of `example.com` and `www.ashoka.edu.in` (both named in the plan), and five PageSpeed Insights requests for `example.com` with the local site's own key. No other outside service was called.

## Step 1: test suites

| Suite | Command | Result |
|---|---|---|
| Unit | `php vendor/phpunit/phpunit/phpunit` | OK, 279 tests, 702 assertions |
| Integration | `npx wp-env run tests-cli --env-cwd=wp-content/plugins/SEO-plugin php vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml` | OK, 52 tests, 187 assertions |
| Lint | `php -l` under PHP 8.0.30 on every plugin PHP file outside `vendor/` | 106 files, 0 errors |
| JavaScript | `node --check public/js/halo-audit.js` | no syntax errors |
| Composer | `composer validate` | valid (one warning: no licence field) |

## Step 2: audits

Run on the wp-env site (`http://localhost:8888`) and on the local DevKinsta site.

| Page | Keyword | Result | Time to first result | Not measured |
|---|---|---|---|---|
| `https://example.com/` | `example` | Overall 47, grade D before speed; 57, grade C after speed. On-page 38, Technical 70, AI readiness 40, Speed 100 | 5.6 s (DevKinsta), 10.0 s (wp-env), 10.9 s in a browser | Speed, until the speed request finished. None after |
| `https://www.ashoka.edu.in/` | `liberal arts university` | No score. The site answered our fetch with HTTP 403. The tool showed "We could not read this page. The site answered with HTTP 403." and stored nothing | 1.5 s | not applicable |
| `https://www.ashoka.edu.in/admissions/` | `Ashoka admissions` | Same as above: HTTP 403, no score, nothing stored | 1.5 s | not applicable |
| `https://thrivemattic.com/` | `AI marketing agency` | **Not run.** This run was not allowed to send requests to production | | |
| `https://thrivemattic.com/halo/` | `search visibility platform` | **Not run.** Same reason | | |

Notes:

- **The plan's criterion "both `ashoka.edu.in` pages show the Education extras" was not met.** The Education extras could not be shown end to end, because the only education target in the plan refused the fetch. Detection and the four extras are covered by `tests/unit/EducationTest.php` (20 tests). This needs one audit of an education site that allows the `HaloAudit/2.0` user agent.
- With no PageSpeed key (wp-env), the Speed group read "Not measured" with three unmeasured checks and the overall score stayed at 47. An unmeasured group did not lower the score.
- No page showed a PHP error. The PHP error log had no fatal error and no line from this plugin during the run.

## Step 3: comparison with Halo

Not done. The Halo CLI output for the two Thrivemattic pages was not available, and those two pages were not audited (see Step 2).

## Step 4: security checks

Run logged out against both local sites. Unknown-report probes used random 32-character IDs.

| Check | Expected | wp-env | DevKinsta |
|---|---|---|---|
| Old report URL `admin-ajax.php?page=seo-analyzer-reports&action=view&id=...` | 400 | 400 | 400 |
| `POST admin-ajax.php` with `action=test_wp_mail` | 400 | 400 | 400 |
| Other old actions (`perform_seo_analysis`, `send_otp`, `verify_otp_and_save_data`, `download_pdf_report`, `fetch_page_title`, `save_competitor_score`) | 400 | 400 | 400 |
| `admin-post.php?action=halo_audit_export` | 302 or 403 | **400**, empty body | **400**, empty body |
| `GET /report/{unknown id}` without a nonce | 403 | 403 | 403 |
| `POST /analyze` for `http://169.254.169.254/latest/meta-data/` | "This address cannot be checked." | as expected (422) | as expected (422) |
| `POST /analyze` for `localhost` and for a `.local` host | rejected | 422 | 422 |
| Response bodies contain a name or an email | no | no | no |

The export probe answers 400, not 302 or 403. WordPress returns 400 from `admin-post.php` when a logged-out visitor names an action that has no public handler. No data is returned. The capability and nonce checks for a logged-in user are covered by `tests/integration/AdminTest.php`.

Further checks, all passing on both sites:

- `POST /analyze` without a nonce: 403.
- The free view has no `checks` array and no finding text. The browser DOM holds no findings before the gate.
- `GET /report/{id}` and `GET /report/{id}/pdf` without an unlock: 403. With a changed cookie: 403. With a cookie but no nonce: 403.
- Gate: invalid email 400; wrong code 400; expired code 400; empty name 400; a used code cannot be replayed; five wrong tries void the code; the fourth code for one email in an hour gets 429.
- The stored code is a hash.
- A correct code returns 33 findings (12 on-page, 8 technical, 10 AI readiness, 3 speed) and sets `halo_audit_unlock` as HttpOnly, SameSite=Lax, and Secure on HTTPS.
- The PDF route streams `application/pdf` built on the server from the stored report. The old endpoint that rendered posted HTML is gone.
- Analysis rate limit: 429 once 10 requests in an hour are reached.
- Admin: the reports list pages 20 rows at a time and shows the old rows; the CSV export holds the leads; cells that start with `=`, `+`, `-` or `@` are quoted.

No real email was sent. A temporary must-use plugin on each local site used the `halo_audit_send_code` filter to record the code, and it was removed after the run. Test leads used `@example.test` addresses and were deleted after the run.

## Local migration check

Run on the DevKinsta database after a rehearsal on a copy of the same tables in the wp-env database.

| Item | Before | After |
|---|---|---|
| Rows in `wp_seo_analyzer_results` | 24 | 24 |
| Checksum over the original columns of those rows | 45823201794 | 45823201794 |
| Rows with an email | 24 | 24 |
| PageSpeed key | one row in `wp_seo_analyzer_api_keys`, 39 characters | option `halo_audit_psi_key`, 39 characters |
| `wp_seo_analyzer_otp` | old layout, 68 rows | new layout, empty |
| `wp_seo_analyzer_api_keys` | present | dropped |
| Option `halo_audit_db_version` | absent | `2` |
| Daily event `halo_audit_purge` | absent | scheduled |

The 7-day purge matches only rows that have a `report_key`, so it does not touch the old rows.

## Step 5: pull request

Not opened. This run was not allowed to push.

## Step 6: staging

Not done. That includes the client-IP check (Review Focus item 5).

## Where the build departs from the plan

| Point | What changed | Why |
|---|---|---|
| `.wp-env.json` | `phpVersion` is `8.1`, not `8.0` | The `wordpress:php8.0` image is built on Debian bullseye, whose package archive has moved. `wp-env` could not build the image. PHP 8.0 is still covered by the unit suite and the lint run |
| `tests/integration/ShortcodeTest.php` | The assertion on the localised script data accepts `halo-audit/v1/` with or without escaped slashes | Current WordPress no longer escapes the slashes |
| Task 2 | Steps 3 to 5 were not repeated | The hotfix is already on `main`. Only `tests/integration/HotfixTest.php` was added, then trimmed in Task 14 and removed in Task 20 as planned |
| Task 1, Step 1 | The last commit touching `SEO-plugin/` was `0f5a185`, not `b965a0e` | Same reason |
| Commits | Files were staged by path, not with `git add -A` | The repo holds untracked local notes |
| mPDF | 8.3.1, up from 8.2.4 | `composer update` in Task 1 |

## Stand-alone plugin check (2026-10-13)

The widget was changed so that it needs no theme code. Built as `halo-audit-2.0.0.zip` with `bin/build-zip.sh` (684 files, about 7 MB, one `seo-analyzer/` folder, production Composer packages inside).

| Check | Where | Result |
|---|---|---|
| ZIP installs through the WordPress installer and activates | wp-env, `wp plugin install <zip>` into a new `seo-analyzer` folder | Pass |
| Form, audit, free view, email step, findings layout | wp-env, Twenty Twenty-Five 1.5, WordPress 7.1.3, PHP 8.1.34, plain permalinks | Pass at 1440, 1024, 768 and 390 px |
| Same, on the local site with the theme patch | DevKinsta, GeneratePress child, PHP 8.3.3, WP Rocket minify and Delay JS on | Pass |
| Same, with the three theme files served as they were before the patch | DevKinsta, page 917, files swapped in the test browser only | Pass. The report stays in the page's half-width column (583 px) |
| Same, with both theme scripts blocked | DevKinsta, page 937 | Pass |
| Two widgets on one page | wp-env | Pass: no shared ids, one request per submit, separate reports |
| `title=""` and `align="wide"` | wp-env | Pass |
| No speed request without a PageSpeed key | wp-env | Pass: 0 requests, Speed "Not measured", score unchanged |
| "Send code" on a site with no mail transport | wp-env | Pass: visitor sees "We could not send the code", report stays locked, admin notice recorded |
| Text contrast | both | Lowest measured 5.83:1 |
| First interaction is the submit, with delayed scripts | DevKinsta | Pass: one request, no page reload |
| PDF with the reduced font set | wp-env, direct render | Pass |
| Delete from the Plugins screen (`wp plugin uninstall`) | wp-env | Pass: tables, options and the scheduled event removed |
| Unit tests | PHP 8.0.30 | 279 pass |
| Integration tests | wp-env | 68 pass (was 52) |

Not tested in this round:

- A real emailed code, the unlocked report over HTTP and the PDF download in a browser. The findings layout was drawn from stubbed answers in the test browser. A manual run on the local site had passed before this change.
- The wp-admin screens in a browser. The admin tests in the integration suite pass.
- Any theme other than the two above, any page builder, a WordPress install in a subfolder, and a warmed "Remove Unused CSS" cache.
- The WordPress layer on PHP 8.0.

## Open items

1. The two Thrivemattic audits, the Halo comparison, the pull request and the staging run.
2. One end-to-end audit of an education site.
3. Tool pages `/ai-tools/seo-analysis/` and `/ai-tools/competitor-analysis/`: the layout and the repeated title were fixed on the local site on 2026-10-13 in the child theme and in `tm-blocks`. Those files are outside this repo and must ship with the plugin; see `THEME-INTEGRATION.md`. The "No signup" lines are still on both pages and are a copy decision.
4. The competitor page still carries the shortcode with `comparison` and `competitor`. They are ignored, so the page shows the single-page audit.
5. Deploy steps in `README.md`, including the add-on removal on each site.
6. WP Rocket "Remove Unused CSS" is active on this site. Most `.halo-audit__*` elements are created by the script after load, so their rules may be stripped on a cached page. Check on staging, and safelist `halo-audit` or exclude `halo-audit.css` if needed. The local browser run showed the widget styled, but it was not checked against a warmed cache.
7. In the browser run on `/ai-tools/seo-analysis/` the only failed request was the deliberate wrong-code `POST /gate/verify` (400). There were no script errors on either tool page.
