# DevBench

**A modern all-in-one developer workbench for WordPress.**

DevBench brings the tools you actually reach for during development — debugging, file editing, database browsing, search, mail testing, and environment auditing — into a single clean, light, card-based admin interface. No clutter, no dark-mode-only IDE vibes, just fast access to information.

![Version](https://img.shields.io/badge/version-1.0.0-4f46e5) ![WordPress](https://img.shields.io/badge/WordPress-5.5%2B-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)

---

## Features

### Core
- **Dashboard** — A live, information-dense overview: WordPress/PHP/DB versions, disk and memory usage bars, plugin/post/user counts, caught-mail count, recent errors, the environment table, and a wp-config constants panel — all at a glance.
- **Search & Locator** — Full-text search across your installation's files (with extension filters) or scan database tables column-by-column. Every result links straight into the editor at the matching line.
- **Debug Manager** — Toggle `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG`, and `SAVEQUERIES` with switches that rewrite `wp-config.php`, plus a live `debug.log` viewer.
- **Log Analyzer** — Parses `debug.log`, groups identical errors, counts occurrences, and sorts by frequency so you fix the loudest problems first. Filter by type or keyword.
- **File Manager** — Browse, edit, create, rename, chmod, upload (drag & drop), and bulk-delete files. Includes a full-screen code editor with line numbers, find, go-to-line, auto-indent, bracket matching, and **session version control** (snapshot / restore / line-by-line diff).
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

- WordPress 5.5 or higher
- PHP 7.4 or higher
- A user with the `manage_options` capability (administrator)

---

## Security

DevBench is a powerful tool intended for **development and staging environments**. All actions require the `manage_options` capability and are protected by nonces. Several features (snippet runner, file editor, config editor) can modify your site directly — use them with care, and avoid leaving the plugin active on production.

- Path traversal is blocked: file operations are confined within `ABSPATH`.
- `DROP DATABASE`, `DROP TABLE`, and `TRUNCATE` are blocked in the SQL runner.
- File uploads are limited to a whitelist of safe extensions.

---

## Changelog

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
