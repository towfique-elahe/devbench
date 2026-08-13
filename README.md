# DevBench

**A modern all-in-one developer workbench for WordPress.**

DevBench brings the tools you actually reach for during development — debugging, file editing, database browsing, search, mail testing, and environment auditing — into a single clean, card-based admin interface. It ships with a calm, neutral design system that defaults to **dark mode** (with a one-click light/dark toggle), so it feels right at home for development work.

![Version](https://img.shields.io/badge/version-1.2.0-6366f1) ![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)

---

## Features

### Core
- **Dashboard** — A live, information-dense overview: WordPress/PHP/DB versions, disk and memory usage bars, plugin/post/user counts, caught-mail count, recent errors, the environment table, and a wp-config constants panel — all at a glance.
- **Search & Locator** — Full-text search across your installation's files (with extension filters) or scan database tables column-by-column, with a **live progress bar** that reports parsing percentage as it works through files/tables in batches. Every result links straight into the editor at the matching line.
- **Debug Manager** — Toggle `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG`, and `SAVEQUERIES` with switches that rewrite `wp-config.php`, plus a live `debug.log` viewer with one-click **copy**.
- **Log Analyzer** — Parses `debug.log`, treats each multi-line error (fatal + full stack trace) as a single grouped entry, counts occurrences, and sorts by frequency so you fix the loudest problems first. Filter by type or keyword, and copy any entry (with its trace) to the clipboard.
- **File Manager** — Browse, edit, create, rename, chmod, upload (drag & drop), and bulk-delete files. The chmod dialog includes a **permission reference** (click a common mode to apply it). Includes a full-screen code editor with line numbers, find, go-to-line, auto-indent, bracket matching, and **session version control** (snapshot / restore-and-save / line-by-line diff).
- **Database Manager** — Browse tables with pagination, inspect structure, run SQL (destructive statements blocked), and export any table to `.sql`.
- **Snippet Runner** — Execute PHP in the full WordPress context with captured output, error reporting, and a library of handy presets.

### WordPress
- **Options Manager** — Search, edit, and delete `wp_options` entries, sorted by size to expose bloat.
- **Transients** — Inspect transients, see active/expired/persistent status, and clear expired ones.
- **Cron Manager** — View scheduled events, run them on demand, or unschedule them.
- **WP Config Editor** — Add, edit, and delete constants in `wp-config.php` through a safe UI.
- **Plugins & Themes** — Activate/deactivate plugins and switch themes from one screen.
- **Mail Catcher** — Intercept all outgoing `wp_mail()` so nothing is actually sent; inspect subject, recipients, headers, and body.

### Utilities & Environment
- **Quick Notes** — A persistent scratchpad stored in your database, with pinning.
- **Environment Checker** — 20+ best-practice checks across PHP, WordPress, security, the database, and extensions, with pass/warn/fail scoring and suggested fixes.
- **PHP Info** — The full `phpinfo()` report in an isolated frame.
- **System Info** — A complete technical overview of WordPress, PHP, the database, the theme, and the server.

---

## Installation

1. Download the plugin ZIP.
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP and click **Install Now**, then **Activate**.
4. Open **DevBench** from the admin menu.

Or install manually by extracting the `devbench` folder into `wp-content/plugins/`.

---

## Requirements

- WordPress 6.6 or higher
- PHP 7.4 or higher
- An administrator (`manage_options`; `manage_network_options` on multisite)

---

## Security

DevBench is a powerful tool intended for **development and staging environments**. Several features (snippet runner, file editor, config editor) can modify your site directly — use them with care, and avoid leaving the plugin active on production.

- Every request requires the plugin capability (`manage_network_options` on multisite) **and** a valid nonce, checked both at the AJAX router and again inside each module handler.
- All write operations — file edits, `wp-config.php` changes and the snippet runner — additionally respect `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS`. When either is set, DevBench becomes read-only and says so.
- Path traversal is blocked: paths are rejected outright if they contain `..`, and the resolved real path must sit inside `ABSPATH` (so symlinks cannot escape either).
- All file I/O goes through the WordPress Filesystem API rather than direct PHP calls.
- Table and column identifiers reach the database through `$wpdb->prepare()`'s `%i` placeholder, and table names are validated against `SHOW TABLES` first.
- `DROP DATABASE`, `DROP TABLE` and `TRUNCATE` are blocked in the SQL runner.
- Uploads are limited to an extension allowlist and rejected when the file contents do not match the extension.
- Keys, salts and passwords in `wp-config.php` are masked in the Config Editor and cannot be edited or deleted through it.

---

## Development

The repository ships a PHPCS ruleset matching the checks the WordPress.org Plugin Check runs:

```bash
composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs phpcompatibility/phpcompatibility-wp
./vendor/bin/phpcs --standard=phpcs.xml .
```

`phpcs.xml`, `README.md` and the dotfiles are excluded from the distributed ZIP via `.distignore`.

---

## Changelog

### 1.2.0

**Security & hardening**
- All file, directory and permission operations now go through the WordPress Filesystem API.
- File and `wp-config.php` writes respect `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS`, with a clear in-app notice when they are set.
- Multisite now requires `manage_network_options` rather than `manage_options`.
- Table and column identifiers use `$wpdb->prepare()` `%i` placeholders.
- Uploads are validated with `wp_check_filetype_and_ext()`.
- Every AJAX handler re-verifies the nonce and capability; all request input is unslashed before sanitizing.
- Every dynamic value printed by the admin templates is escaped at output.
- Keys, salts and passwords are masked in the Config Editor and are no longer editable through it.

**Fixes**
- **Mail Catcher recorded nothing.** It hooked `pre_wp_mail` to block delivery and `wp_mail` to capture, but the former short-circuits before the latter runs — so mail was suppressed and never logged. Both now happen in one callback.
- **Options Manager and autoload reporting were wrong on WordPress 6.6+**, where the `autoload` column gained `on`/`off`/`auto` values alongside `yes`/`no`.
- Table exports are now read in chunks, so large tables no longer have to fit in memory.

**Housekeeping**
- Added `readme.txt`, `uninstall.php`, `.gitignore`, `.distignore` and a `phpcs.xml` ruleset.
- Report-screen data gathering moved out of the templates into `DevBench_Reports`.
- Removed dead duplicate search code paths and de-duplicated the clipboard helper.
- Raised the minimum WordPress version to 6.6.

### 1.1.0

**Design system**
- **Dark mode by default** with a one-click light/dark toggle in the sidebar; the choice is persisted per browser and applied before paint (no flash).
- Reworked into a **calmer, neutral palette** with a single soft-blue accent.
- **Removed all box shadows** — depth now comes from borders only, for a flatter, cleaner look.
- Themed every form control (inputs, selects, custom checkboxes/radios, focus rings) so they no longer inherit WordPress admin's default styling, in both light and dark mode.
- Replaced emoji icons with a consistent **inline-SVG icon set** throughout, and gave each page a distinct, correct icon.

**Features & UX**
- **Search & Locator:** real progress bar with percentage, driven by batched file/table scanning (also avoids request timeouts on large installs).
- **Log Analyzer:** multi-line errors and their stack traces are now grouped as one entry; added a per-row **Copy** button for the full entry.
- **Debug Manager:** added a **Copy** button for the `debug.log` viewer.
- **File editor:** restoring a version from history now **saves it to disk** automatically.
- **File Manager:** added a clickable **permission reference** to the chmod dialog and a proper upload icon.

**Fixes**
- Fixed the page-initializer registry so AJAX-driven pages (File Manager, Database, Search, etc.) load their data reliably.
- Fixed a variable-scope collision that broke the Log Analyzer list (and produced stray `Undefined array key` warnings).
- Hardened log parsing against very long lines (PCRE backtrack-limit safety).
- Normalized file paths to forward slashes (correct display and navigation on Windows).
- Fixed wide-table horizontal overflow in the Database Manager (now scrolls within the card).

### 1.0.0
- Initial release — complete rebuild with a modern, light, card-based UI.
- 17 tools across Core, WordPress, Utilities, and Environment groups.
- Full-screen code editor with session version control.
- Unified AJAX architecture and a single clean design system.

---

## Author

**Towfique Elahe**
Website: [towfiqueelahe.com](https://towfiqueelahe.com)
GitHub: [github.com/towfique-elahe](https://github.com/towfique-elahe)

---

## License

GPL-2.0+ — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
