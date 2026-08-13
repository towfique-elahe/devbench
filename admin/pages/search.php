<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id    = 'devbench-search';
$devbench_tables     = DevBench_Database::table_names();
$devbench_extensions = array( 'php', 'js', 'css', 'html', 'json', 'txt', 'md', 'sql', 'env' );

require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
    <h1><?php DevBench_Helpers::the_icon('search',22); ?> Search &amp; Locator</h1>
    <p>Search file contents across your install or scan database tables — then jump straight into the editor.</p>
</div>

<div class="db-card">
    <div class="db-card-body">
        <div class="db-tabs" id="db-search-tabs">
            <button class="db-tab active" data-mode="files">Files</button>
            <button class="db-tab" data-mode="database">Database</button>
        </div>

        <div class="db-flex db-gap-8 db-wrap">
            <div class="db-input-icon" style="flex:1;min-width:240px">
                <span class="db-input-icon-lead"><?php DevBench_Helpers::the_icon('search',16); ?></span>
                <input type="text" class="db-input" id="db-search-kw" placeholder="Enter a keyword (min 2 chars)…">
            </div>
            <button class="db-btn db-btn-primary" id="db-search-go"><?php DevBench_Helpers::the_icon('search',15); ?>
                Search</button>
        </div>

        <!-- Files options -->
        <div id="db-search-files-opts" class="db-mt-12">
            <label class="db-label">Filter by extension (leave empty for common code files)</label>
            <div class="db-flex db-wrap db-gap-8">
                <?php foreach ( $devbench_extensions as $devbench_extension ) : ?>
                <label class="db-flex db-gap-8" style="font-size:13px;cursor:pointer"><input type="checkbox"
                        class="db-ext" value="<?php echo esc_attr( $devbench_extension ); ?>">
                    .<?php echo esc_html( $devbench_extension ); ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Database options -->
        <div id="db-search-db-opts" class="db-mt-12 db-hidden">
            <label class="db-label">Tables to scan (leave empty for all)</label>
            <div class="db-flex db-wrap db-gap-8" style="max-height:120px;overflow-y:auto">
                <?php foreach ( $devbench_tables as $devbench_table ) : ?>
                <label class="db-flex db-gap-8" style="font-size:12px;cursor:pointer"><input type="checkbox"
                        class="db-tbl" value="<?php echo esc_attr( $devbench_table ); ?>"> <span
                        class="db-mono"><?php echo esc_html( $devbench_table ); ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div id="db-search-results"></div>

<?php require __DIR__ . '/_editor_modal.php'; ?>

<script>
window.DBPages['devbench-search'] = function() {
    var $ = jQuery,
        mode = 'files';

    $('#db-search-tabs .db-tab').on('click', function() {
        $('#db-search-tabs .db-tab').removeClass('active');
        $(this).addClass('active');
        mode = $(this).data('mode');
        $('#db-search-files-opts').toggleClass('db-hidden', mode !== 'files');
        $('#db-search-db-opts').toggleClass('db-hidden', mode !== 'database');
        $('#db-search-results').empty();
    });

    var FILE_BATCH = 50,   // files scanned per request
        MAX_FILE_HITS = 200; // stop after this many matched files
    var searching = false;

    function progressCard(label) {
        return '<div class="db-card"><div class="db-card-body">' +
            '<div class="db-flex-between" style="margin-bottom:10px">' +
            '<span class="db-fw-600" id="db-search-prog-label">' + label + '</span>' +
            '<span class="db-muted db-text-sm db-mono" id="db-search-prog-pct">0%</span></div>' +
            '<div class="db-progress"><div class="db-progress-fill" id="db-search-prog-bar" style="width:0%"></div></div>' +
            '<div class="db-muted db-text-sm db-mt-8" id="db-search-prog-sub">Preparing…</div>' +
            '</div></div>';
    }

    function setProgress(pct, sub) {
        $('#db-search-prog-bar').css('width', pct + '%');
        $('#db-search-prog-pct').text(pct + '%');
        if (sub != null) $('#db-search-prog-sub').text(sub);
    }

    function setSearching(on) {
        searching = on;
        $('#db-search-go').prop('disabled', on);
    }

    function run() {
        if (searching) return;
        var kw = $('#db-search-kw').val().trim();
        if (kw.length < 2) {
            DBToast.show('Enter at least 2 characters', 'error');
            return;
        }
        $('#db-search-results').html(progressCard(mode === 'files' ? 'Scanning files…' : 'Scanning tables…'));
        setSearching(true);
        if (mode === 'files') runFiles(kw);
        else runDb(kw);
    }

    /* ---- Files: enumerate, then scan in batches ---- */
    function runFiles(kw) {
        var exts = $('.db-ext:checked').map(function() { return this.value; }).get();
        DBAjax('search', 'enumerate', { extensions: exts }).done(function(res) {
            if (!res.success) { fail(res); return; }
            var files = res.data.files, total = res.data.total, idx = 0, all = [];
            if (!total) { setSearching(false); renderFiles([], kw); return; }
            setProgress(0, '0 of ' + total + ' files');

            function next() {
                if (idx >= total) return finishFiles(all, kw, false);
                var slice = files.slice(idx, idx + FILE_BATCH);
                DBAjax('search', 'scan_batch', { keyword: kw, paths: slice }).done(function(r) {
                    if (r.success && r.data.results.length) all = all.concat(r.data.results);
                    idx += slice.length;
                    var pct = Math.round(idx / total * 100);
                    setProgress(pct, idx + ' of ' + total + ' files scanned · ' +
                        all.length + ' match' + (all.length !== 1 ? 'es' : ''));
                    if (all.length >= MAX_FILE_HITS) return finishFiles(all.slice(0, MAX_FILE_HITS), kw, true);
                    next();
                }).fail(function() { idx += slice.length; next(); });
            }
            next();
        }).fail(function() { fail(); });
    }

    function finishFiles(results, kw, capped) {
        setSearching(false);
        results.sort(function(a, b) { return b.count - a.count; });
        renderFiles(results, kw);
        if (capped) DBToast.show('Showing first ' + MAX_FILE_HITS + ' matched files', 'success');
    }

    /* ---- Database: scan one table at a time ---- */
    function runDb(kw) {
        var tbls = $('.db-tbl:checked').map(function() { return this.value; }).get();
        DBAjax('search', 'db_tables', { tables: tbls }).done(function(res) {
            if (!res.success) { fail(res); return; }
            var tables = res.data.tables, total = res.data.total, idx = 0, all = [];
            if (!total) { setSearching(false); renderDb([], kw); return; }
            setProgress(0, '0 of ' + total + ' tables');

            function next() {
                if (idx >= total) { setSearching(false); return renderDb(all, kw); }
                var t = tables[idx];
                setProgress(Math.round(idx / total * 100),
                    'Scanning ' + t + ' (' + (idx + 1) + ' of ' + total + ') · ' +
                    all.length + ' with matches');
                DBAjax('search', 'scan_table', { keyword: kw, table: t }).done(function(r) {
                    if (r.success && r.data.result) all.push(r.data.result);
                    idx++;
                    next();
                }).fail(function() { idx++; next(); });
            }
            next();
        }).fail(function() { fail(); });
    }

    function fail(res) {
        setSearching(false);
        $('#db-search-results').html('');
        DBToast.show((res && res.data) || 'Search failed', 'error');
    }

    function hl(text, kw) {
        var re = new RegExp('(' + kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return DBEsc(text).replace(re, '<mark>$1</mark>');
    }

    function renderFiles(results, kw) {
        if (!results.length) {
            $('#db-search-results').html(emptyState('No files matched “' + DBEsc(kw) + '”.'));
            return;
        }
        var h = '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">' + results.length +
            ' file' + (results.length !== 1 ? 's' : '') + ' matched</h3></div></div>';
        results.forEach(function(f) {
            h += '<div class="db-card"><div class="db-card-head">' +
                '<h3 class="db-card-title">' + DBFileIcon(f.ext) +
                ' <span class="db-mono" style="font-size:13px">' + DBEsc(f.path) +
                '</span> <span class="db-badge db-badge-accent">' + f.count + '</span></h3>' +
                '<button class="db-btn db-btn-sm db-search-edit" data-path="' + DBEsc(f.path) +
                '" data-name="' + DBEsc(f.name) + '" data-line="' + (f.matches[0] ? f.matches[0].line : 1) +
                '">' + DBIcon('edit', 14) + ' Edit</button>' +
                '</div><div class="db-card-body flush">';
            f.matches.forEach(function(m) {
                h += '<div class="db-flex db-gap-12 db-search-jump" data-path="' + DBEsc(f.path) +
                    '" data-name="' + DBEsc(f.name) + '" data-line="' + m.line +
                    '" style="padding:7px 16px;border-bottom:1px solid var(--db-border);cursor:pointer;font-family:var(--db-mono);font-size:12px">' +
                    '<span class="db-muted" style="min-width:48px;text-align:right">' + m.line +
                    '</span>' +
                    '<span style="white-space:pre-wrap;word-break:break-word">' + hl(m.text, kw) +
                    '</span></div>';
            });
            h += '</div></div>';
        });
        $('#db-search-results').html(h);
    }

    function renderDb(results, kw) {
        if (!results.length) {
            $('#db-search-results').html(emptyState('No database rows matched “' + DBEsc(kw) + '”.'));
            return;
        }
        var h = '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">Matches in ' + results
            .length + ' table' + (results.length !== 1 ? 's' : '') + '</h3></div></div>';
        results.forEach(function(t) {
            h += '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">' + DBIcon('database', 16) +
                ' <span class="db-mono">' + DBEsc(t.table) +
                '</span> <span class="db-badge db-badge-accent">' + t.total + ' total</span></h3>' +
                '<a class="db-btn db-btn-ghost db-btn-sm" href="<?php echo esc_url( admin_url('admin.php?page=devbench-database') ); ?>">Open table →</a></div><div class="db-card-body flush">';
            t.hits.forEach(function(row) {
                h += '<div style="padding:9px 16px;border-bottom:1px solid var(--db-border)"><div class="db-muted db-mono" style="font-size:11px;margin-bottom:4px">id: ' +
                    DBEsc(row.id) + '</div>';
                row.cells.forEach(function(c) {
                    h += '<div style="font-size:12px;margin-bottom:2px"><strong class="db-mono">' +
                        DBEsc(c.col) + ':</strong> ' + hl(c.snippet, kw) + '</div>';
                });
                h += '</div>';
            });
            h += '</div></div>';
        });
        $('#db-search-results').html(h);
    }

    function emptyState(msg) {
        return '<div class="db-card"><div class="db-empty"><h3>No results</h3><p>' + msg + '</p></div></div>';
    }

    $('#db-search-go').on('click', run);
    $('#db-search-kw').on('keydown', function(e) {
        if (e.key === 'Enter') run();
    });

    // Edit handlers (delegated, no stopPropagation)
    $('#db-search-results').on('click', '.db-search-edit', function(e) {
        e.stopPropagation();
        DBOpenEditor($(this).data('path'), $(this).data('name'), parseInt($(this).data('line'), 10));
    });
    $('#db-search-results').on('click', '.db-search-jump', function() {
        DBOpenEditor($(this).data('path'), $(this).data('name'), parseInt($(this).data('line'), 10));
    });
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>