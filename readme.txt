=== DevBench ===
Contributors: towfiqueelahe
Tags: developer, debug, database, file manager, log
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
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
* **File Manager** — Browse, edit, create, rename, chmod, upload and bulk-delete files, with a full-screen code editor (line numbers, find, go-to-line, auto-indent, session version control).
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

* **Quick Notes** — A persistent scratchpad stored in your database, with pinning.
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

No. It makes no external requests and collects no data.

== Screenshots ==

1. Dashboard — a full snapshot of the development environment.
2. Search & Locator — file and database search with live progress.
3. File Manager with the full-screen code editor.
4. Log Analyzer — errors grouped by frequency.
5. Environment Checker — best-practice checks with pass/warn/fail scoring.

== Changelog ==

= 1.2.0 =
* Security: all file, directory and permission operations now go through the WordPress Filesystem API instead of direct PHP calls.
* Security: file and `wp-config.php` writes now respect `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS`.
* Security: on multisite, DevBench now requires `manage_network_options` rather than `manage_options`.
* Security: table and column identifiers in database queries now use `$wpdb->prepare()` `%i` placeholders.
* Security: uploads are validated with `wp_check_filetype_and_ext()` and rejected if the real type does not match the extension.
* Hardening: every AJAX handler re-verifies the nonce and capability; all request input is unslashed before sanitizing.
* Escaping: every dynamic value printed by the admin templates is now escaped at output.
* Fixed the Mail Catcher recording nothing: it short-circuited `wp_mail()` before the capture filter could run, so mail was blocked but never logged.
* Fixed the Options Manager and autoload size reporting on WordPress 6.6+, where the `autoload` column gained `on`/`off`/`auto` values alongside `yes`/`no`.
* Keys, salts and passwords in `wp-config.php` are now masked in the Config Editor and cannot be edited or deleted through it.
* Added `uninstall.php` so DevBench removes its own options when deleted.
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

= 1.2.0 =
Security and hardening release. Requires WordPress 6.6 or later. File and config editing now respects DISALLOW_FILE_EDIT.
