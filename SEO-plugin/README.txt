=== SEO Analyzer ===
Contributors: thrivemattic
Tags: seo, audit, ai, performance
Requires at least: 6.2
Tested up to: 6.8
Stable tag: 2.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Halo Audit. Scores one page for on-page SEO, technical health, AI readiness and speed.

== Description ==

Enter a page address and a keyword. Halo Audit reads the page and scores it in four areas:

* On-page for the keyword (30%)
* Technical (20%)
* AI readiness (30%)
* Speed (20%)

Schools, colleges and universities also get four admission checks. These are shown but not scored.

The score and grade are free. The full findings and the PDF need a name and a confirmed email.

A check that cannot run shows "Not measured". It never lowers the score.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Run `composer install --no-dev` in the plugin folder.
3. Activate the plugin.
4. Go to SEO Analyzer > Settings. Add a Google PageSpeed API key and a Brevo API key.
5. Add `[seo_analyzer title="Halo Audit"]` to a page.

== Upgrade Notice ==

= 2.0.0 =
Needs PHP 8.0 and WordPress 6.2. Run `composer install --no-dev` after you update. The OpenAI and NLP add-ons are no longer used: deactivate and delete them. The competitor comparison is removed.

== Developer notes ==

* Filter `halo_audit_client_ip`: return the true visitor IP when the site is behind a proxy.
* Filter `halo_audit_scan_client`: return a `HaloAudit\ScanClient` to enable the live AI visibility scan.
* Tests: `composer test` (unit). Integration tests need Docker: `npx wp-env start`, then
  `npx wp-env run tests-cli --env-cwd=wp-content/plugins/SEO-plugin vendor/bin/phpunit -c phpunit.integration.xml`.
