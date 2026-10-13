=== SEO Analyzer ===
Contributors: thrivemattic
Tags: seo, audit, ai, performance
Requires at least: 6.2
Tested up to: 7.1
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

The plugin needs no theme changes, no other plugin and no add-on. The form, the report, the email step and the PDF all belong to the plugin.

== Requirements ==

* WordPress 6.2 or later.
* PHP 8.0 or later with the `dom`, `mbstring`, `openssl` and `gd` extensions. The plugin shows a notice and stays off when one is missing.
* The site must be able to send email (see Email below).
* The site must be able to make outgoing HTTPS requests.

== Installation ==

1. In WordPress go to Plugins > Add New > Upload Plugin. Choose `halo-audit-2.0.0.zip` and press Install Now.
2. Press Activate.
3. Add `[seo_analyzer]` to a page.

The ZIP already holds everything the plugin needs. You do not run Composer on the server.

If the upload is refused because the file is too large (the ZIP is about 7 MB), unzip it and copy the `seo-analyzer` folder to `/wp-content/plugins/` with SFTP, then activate the plugin.

Updating a site that already runs this plugin: replace the files in the folder the plugin is already in. Do not upload the ZIP as a second plugin. If two copies are active, only the first one loads.

Coming from version 1.x: never delete the 1.x copy from the Plugins screen. Deleting it drops the table that holds your reports and leads. Replace its files instead, and export the leads first to be safe.

== Settings ==

SEO Analyzer > Settings. Every setting is optional.

* Google PageSpeed API key. Turns on the Speed group. Without a key the speed test is skipped: Speed shows "Not measured" and the score is worked out from the other three groups. The key is free from Google and stays on the server.
* Brevo API key and Sender email. Send the email code through Brevo. Without a key the code is sent with WordPress mail.
* Scan server URL and secret. Not used in this version.

== Email ==

To unlock the findings a visitor enters a name and an email address and gets a 6-digit code. The code is valid for 15 minutes and works once.

The code is sent through Brevo when a Brevo key is saved. Otherwise it is sent with `wp_mail()`, so whatever sends mail for the site (the host, or an SMTP plugin) sends the code.

If a code cannot be sent, the visitor sees "We could not send the code" and the plugin's admin screens show a notice until a later code goes out. Test this once after you install: run an audit and unlock it with your own address.

== Shortcode ==

`[seo_analyzer]`

* `title="Free SEO audit"` sets the heading. The default is "Halo Audit".
* `title=""` leaves the heading out. Use this when the page already has a heading for the tool.
* `heading_level="3"` sets the heading level, 2 to 5. The default is 2.
* `align="wide"` or `align="full"` adds the standard WordPress alignment class, so a block theme gives the tool its wide or full width.

The old attributes `popup`, `comparison`, `nlp` and `competitor` are accepted and ignored.

The tool fills the width of the place you put it in. In a narrow column it stacks; in a wide one the form sits on one line and the findings use two columns. To give it more room, put it in a wider part of the page.

More than one tool can sit on a page. Each keeps its own report.

== What the visitor sees ==

1. A form: page address and keyword.
2. The score, the grade and the four group scores. No account is needed.
3. "See all findings" or "Download PDF" asks for a name, an email and the 6-digit code.
4. After the code: every finding, with what to fix and why. The PDF holds the same report.
5. The unlock lasts 30 days in that browser.

== Security limits ==

* The tool does not read local or private addresses: `localhost`, `.local`, `.test`, private and reserved IP ranges, ports other than 80 and 443.
* Each visitor can run 10 audits an hour. One email address gets 3 codes an hour.
* A site that answers with an error (for example 403) gets no score. The tool says it could not read the page.
* The findings and the PDF are checked on the server. They are never sent to a browser that has not confirmed an email.
* Reports that were never unlocked are deleted after 7 days.

== Themes ==

The plugin styles only its own markup, under `.halo-audit`. It was tested on GeneratePress (child theme) and on Twenty Twenty-Five. Other themes and page builders have not been tested.

The colours suit a light background. To change them, set the `--halo-*` variables on `.halo-audit` in your theme's CSS, for example:

`.halo-audit { --halo-accent: #1a56db; --halo-radius: 4px; }`

Variables: `--halo-ink`, `--halo-muted`, `--halo-line`, `--halo-soft`, `--halo-field`, `--halo-surface`, `--halo-pad`, `--halo-radius`, `--halo-accent`, `--halo-accent-ink`, `--halo-pass`, `--halo-warn`, `--halo-fail`.

A theme can follow the tool's state. The root element carries `data-state` (`idle`, `invalid`, `loading`, `error`, `preview`, `gate`, `code`, `unlocked`), and a `halo-audit:state` event bubbles from it on each change.

== Troubleshooting ==

* "Run composer install" notice: the plugin was copied from the source repository, not from the ZIP. Install the ZIP.
* Speed always says "Not measured": add a PageSpeed key, or check that the key is valid.
* "We could not send the code": the site cannot send email. Check the Brevo key and sender, or the site's mail setup.
* "Your session has expired": a firewall or cache is blocking `/wp-json/halo-audit/v1/`. Do not cache that path.
* The tool looks unstyled after an update: clear the cache of your optimisation plugin, including its minified CSS and JavaScript.
* With "Remove Unused CSS" in an optimisation plugin, add `halo-audit` to its safelist. Most of the report is drawn in the browser after the page loads.

== Uninstall ==

Deactivating keeps all data. Deleting the plugin from the Plugins screen removes its tables (reports and leads), its settings and its scheduled clean-up. Export the leads first: SEO Analyzer > Reports > Export leads as CSV.

== Upgrade Notice ==

= 2.0.0 =
Needs PHP 8.0 and WordPress 6.2. The OpenAI and NLP add-ons are no longer used: deactivate and delete them. The competitor comparison is removed. The old OpenAI key setting is not read any more.

== Developer notes ==

* Filter `halo_audit_client_ip`: return the true visitor IP when the site is behind a proxy.
* Filter `halo_audit_speed_enabled`: return true or false to force the speed test on or off.
* Filter `halo_audit_user_agent`: the user agent used to read pages. The default names this site.
* Filter `halo_audit_pdf_footer`: the last line of the PDF. Return an empty string to leave it out.
* Filter `halo_audit_send_code`: return true or false to send the code yourself. Arguments: null, the address, the code.
* Filter `halo_audit_scan_client`: return a `HaloAudit\ScanClient` to enable the live AI visibility scan.
* Tests: `composer test` (unit). Integration tests need Docker: `npx wp-env start`, then
  `npx wp-env run tests-cli --env-cwd=wp-content/plugins/SEO-plugin php vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml`.
* Release ZIP: from the repository root,
  `docker run --rm -v "$PWD:/repo" -w /repo/SEO-plugin composer:2 sh bin/build-zip.sh /repo/dist`.
  It builds from the last commit, installs the production Composer packages and writes `dist/halo-audit-<version>.zip`.
