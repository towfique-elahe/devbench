=== DevBench ===
Contributors: towfiqueelaheofficial
Donate link: https://towfiqueelahe.com/support/
Tags: developer, debug, database, file manager, log
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An all-in-one developer workbench: debug tools, file manager, database browser, search, mail catcher and environment checks.

== Description ==

DevBench brings the tools you actually reach for during development — debugging, file editing, database browsing, search, mail testing and environment auditing — into a single clean, card-based admin interface. It ships with a calm, neutral design system that defaults to dark mode, with a one-click light/dark toggle.

DevBench is built for **local, development and staging environments**. It is deliberately powerful: it can edit files, rewrite `wp-config.php`, run SQL and execute PHP. Every one of those actions requires the `manage_options` capability (`manage_network_options` on multisite) and a valid nonce, and file/config editing additionally respects the `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS` constants. Even so, you should not leave it active on a production site.

= Core tools =

* **Dashboard** — WordPress/PHP/DB versions, disk and memory bars, plugin/post/user counts, caught-mail count, recent errors and a wp-config constants panel.
* **Search & Locator** — Full-text search across install files (with extension filters) or column-by-column database scans, with a live progress bar. Results link straight into the editor at the matching line.
* **Debug Manager** — Toggle `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG` and `SAVEQUERIES`, plus a live `debug.log` viewer with one-click copy.
* **Log Analyzer** — Groups each multi-line error (fatal + full stack trace) into a single entry, counts occurrences and sorts by frequency.
* **File Manager** — Browse, edit, create, rename, chmod, upload, download, zip and bulk-delete files, with a full-screen code editor (line numbers, find, go-to-line, auto-indent, session version control).
* **Database Manager** — Browse tables with pagination, inspect structure, run SQL (destructive statements blocked) and export tables to `.sql`.
* **Snippet Runner** — Execute PHP in the full WordPress context with captured output and error reporting.

= WordPress tools =

* **Options Manager** — Search, edit and delete `wp_options` entries, sorted by size to expose bloat.
* **Transients** — Inspect transients, see active/expired/persistent status and clear expired ones.
* **Cron Manager** — View scheduled events, run them on demand or unschedule them.
* **WP Config Editor** — Add, edit and delete constants in `wp-config.php` through a safe UI.
* **Plugins & Themes** — Activate/deactivate plugins and switch themes from one screen.
* **Mail Catcher** — Intercept outgoing `wp_mail()` so nothing is sent; inspect subject, recipients, headers and body.

= Utilities & environment =

* **Quick Notes** — A persistent scratchpad stored in your database, with pinning and per-note delete.
* **Report a Bug** — Send a problem report to the plugin author, with an optional environment summary you can read before it goes.
* **Environment Checker** — 20+ best-practice checks across PHP, WordPress, security, database and extensions.
* **PHP Info** — The full `phpinfo()` report in an isolated frame.
* **System Info** — A complete technical overview of WordPress, PHP, database, theme and server.

== Installation ==

1. Upload the `devbench` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Open **DevBench** from the admin menu.

== Frequently Asked Questions ==

= Is DevBench safe to run on a production site? =

No. It is a development tool. It can edit files, rewrite `wp-config.php`, run arbitrary SQL and execute PHP. Use it locally or on staging, and deactivate it on production.

= Why are the File Manager and Config Editor disabled? =

DevBench respects the `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS` constants. If either is defined as `true` in `wp-config.php`, all file and config write operations are blocked. Read-only browsing still works.

= Why can't I see DevBench on my multisite subsite? =

On multisite, DevBench requires the `manage_network_options` capability (super admin), because its tools operate on files and the database shared by the whole network.

= Does DevBench send any data anywhere? =

Not on its own. DevBench makes no external requests, phones nothing home and collects no analytics.

The one exception is the **Report a Bug** screen, which you have to fill in and submit yourself. It sends a single email to the plugin author through your own site's mail setup. The optional environment block is printed in full on that screen before you send, so you can read exactly what would be attached — it contains no keys, salts, passwords or database credentials.

== Screenshots ==

1. Dashboard — a full snapshot of the development environment.
2. Search & Locator — file and database search with live progress.
3. File Manager with the full-screen code editor.
4. Log Analyzer — errors grouped by frequency.
5. Environment Checker — best-practice checks with pass/warn/fail scoring.

== Changelog ==

= 1.3.0 =

**New features**

* File Manager: download any file, streamed rather than buffered so large files work.
* File Manager: zip the current selection into the folder you are browsing — folders included recursively, using ZipArchive where available and the PclZip library WordPress already bundles otherwise.
* Quick Notes: delete a note from the list.
* New Report a Bug screen: send a problem report to the plugin author, with an optional environment summary shown in full before sending. It deliberately bypasses the Mail Catcher, which would otherwise swallow it.
* Every destructive action now asks for confirmation before it runs, including transient and caught-mail deletion, which previously fired on a single click.

**Security**

* All file, directory and permission operations go through the WordPress Filesystem API instead of direct PHP calls.
* File and `wp-config.php` writes respect `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS`, with an in-app notice explaining when they are set.
* On multisite, DevBench requires `manage_network_options` rather than `manage_options`, since its tools reach files and tables shared by the whole network.
* Table and column identifiers reach the database through `$wpdb->prepare()` `%i` placeholders, and table names are validated against `SHOW TABLES` first.
* Path traversal hardened: paths containing `..` are rejected outright and the resolved real path must sit inside `ABSPATH`, so symlinks cannot escape either.
* Uploads are validated with `wp_check_filetype_and_ext()` and `is_uploaded_file()`, and rejected when the contents do not match the extension.
* Downloads are always served as an attachment with `X-Content-Type-Options: nosniff`, so an `.html` or `.svg` from inside the install cannot execute on the site's own origin.
* Fixed an escaping gap where a file name containing a quote could break out of an HTML attribute in the admin screens.
* Removed the Google Fonts request. DevBench now makes no external requests at all and uses system font stacks.
* Keys, salts and passwords in `wp-config.php` are masked in the Config Editor and cannot be edited or deleted through it.
* Every AJAX handler re-verifies the nonce and capability; all request input is unslashed before sanitizing; every dynamic value printed by the admin templates is escaped at output.

**Fixes**

* Mail Catcher recorded nothing. It hooked `pre_wp_mail` to block delivery and `wp_mail` to capture, but the former short-circuits before the latter runs, so mail was suppressed and never logged. Both now happen in one callback.
* Database Manager reported 0 rows for every table. `SHOW TABLE STATUS` returns an estimate for InnoDB, and on a small or recently created database that estimate is 0. Counts now come from `COUNT(*)` and match the number shown when browsing a table.
* Options Manager mislabelled autoloaded options on WordPress 6.6+, where the `autoload` column gained `on`/`off`/`auto` values alongside `yes`/`no`. Both the filter and the badge now read the vocabulary from core.
* Table exports are read in chunks, so a large table no longer has to fit in memory.
* Fixed viewport-height maths in wp-admin: the layout accounts for the admin bar and the space reserved for the footer, instead of adding roughly 97px of dead scroll to every screen.
* Fixed a background seam under short pages, where WordPress's own wrapper showed through beneath the app.
* File Manager breadcrumb segments are real links again — they had no `href`, so they had no pointer cursor, no tab stop and no keyboard activation.
* Quick Notes: the note title lines up with the note body, and deleting the note currently open no longer lets a subsequent save recreate it.

**Design**

* Redesigned around a monochrome, shadcn-style neutral palette: no brand hue, status expressed as contrast weight rather than colour, and destructive actions as the single retained accent. Every foreground/background pair was measured and meets WCAG AA in both themes.
* The sidebar is a rounded floating panel pinned in place while you scroll, with the light/dark switch moved up to the brand row.
* Row actions across every screen are icon buttons, each carrying an explicit accessible label naming its target.
* WordPress's admin footer is hidden on DevBench screens and the space it reserved is reclaimed.

**Housekeeping**

* Added `readme.txt`, `uninstall.php`, `.gitignore`, `.distignore` and a `phpcs.xml` ruleset matching the checks the WordPress.org Plugin Check runs.
* Report-screen data gathering moved out of the page templates into a dedicated class.
* Removed dead duplicate search code and de-duplicated the clipboard helper.
* Raised the minimum WordPress version to 6.6.

= 1.1.0 =
* Dark mode by default with a one-click light/dark toggle, applied before paint.
* Reworked into a calmer, neutral palette with a single soft-blue accent; removed all box shadows.
* Themed every form control so it no longer inherits WordPress admin styling.
* Replaced emoji icons with a consistent inline-SVG icon set.
* Search & Locator: real progress bar driven by batched file/table scanning.
* Log Analyzer: multi-line errors and stack traces grouped as one entry, with a per-row copy button.
* Debug Manager: added a copy button for the `debug.log` viewer.
* File editor: restoring a version from history now saves it to disk.
* File Manager: clickable permission reference in the chmod dialog.
* Fixed the page-initializer registry so AJAX-driven pages load reliably.
* Fixed a variable-scope collision that broke the Log Analyzer list.
* Hardened log parsing against very long lines.
* Normalized file paths to forward slashes for Windows.

= 1.0.0 =
* Initial release — 17 tools across Core, WordPress, Utilities and Environment groups.
* Full-screen code editor with session version control.
* Unified AJAX architecture and a single design system.

== Upgrade Notice ==

= 1.3.0 =
Security, accessibility and design release. Requires WordPress 6.6 or later. File and config editing now respects DISALLOW_FILE_EDIT, and every destructive action asks before it runs.
