<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-search';
global $wpdb;
$tables = $wpdb->get_col( 'SHOW TABLES' );
include __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('search',22); ?> Search &amp; Locator</h1>
	<p>Search file contents across your install or scan database tables — then jump straight into the editor.</p>
</div>

<div class="db-card">
	<div class="db-card-body">
		<div class="db-tabs" id="db-search-tabs">
			<button class="db-tab active" data-mode="files">Files</button>
			<button class="db-tab" data-mode="database">Database</button>
		</div>

		<div class="db-flex db-gap-8 db-wrap">
			<input type="text" class="db-input" id="db-search-kw" placeholder="Enter a keyword (min 2 chars)…" style="flex:1;min-width:240px">
			<button class="db-btn db-btn-primary" id="db-search-go"><?php echo DevBench_Helpers::icon('search',15); ?> Search</button>
		</div>

		<!-- Files options -->
		<div id="db-search-files-opts" class="db-mt-12">
			<label class="db-label">Filter by extension (leave empty for common code files)</label>
			<div class="db-flex db-wrap db-gap-8">
				<?php foreach ( [ 'php','js','css','html','json','txt','md','sql','env' ] as $e ) : ?>
				<label class="db-flex db-gap-8" style="font-size:13px;cursor:pointer"><input type="checkbox" class="db-ext" value="<?php echo $e; ?>"> .<?php echo $e; ?></label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Database options -->
		<div id="db-search-db-opts" class="db-mt-12 db-hidden">
			<label class="db-label">Tables to scan (leave empty for all)</label>
			<div class="db-flex db-wrap db-gap-8" style="max-height:120px;overflow:auto">
				<?php foreach ( $tables as $t ) : ?>
				<label class="db-flex db-gap-8" style="font-size:12px;cursor:pointer"><input type="checkbox" class="db-tbl" value="<?php echo esc_attr($t); ?>"> <span class="db-mono"><?php echo esc_html($t); ?></span></label>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<div id="db-search-results"></div>

<?php include __DIR__ . '/_editor_modal.php'; ?>

<script>
window.DBPages['devbench-search'] = function () {
	var $ = jQuery, mode = 'files';

	$('#db-search-tabs .db-tab').on('click', function () {
		$('#db-search-tabs .db-tab').removeClass('active');
		$(this).addClass('active');
		mode = $(this).data('mode');
		$('#db-search-files-opts').toggleClass('db-hidden', mode !== 'files');
		$('#db-search-db-opts').toggleClass('db-hidden', mode !== 'database');
		$('#db-search-results').empty();
	});

	function run() {
		var kw = $('#db-search-kw').val().trim();
		if (kw.length < 2) { DBToast.show('Enter at least 2 characters', 'error'); return; }
		var $r = $('#db-search-results').html('<div class="db-card"><div class="db-card-body"><span class="db-spinner"></span> <span class="db-muted">Searching…</span></div></div>');

		if (mode === 'files') {
			var exts = $('.db-ext:checked').map(function () { return this.value; }).get();
			DBAjax('search', 'files', { keyword: kw, extensions: exts }).done(function (res) {
				if (!res.success) { $r.html(''); DBToast.show(res.data || 'Search failed', 'error'); return; }
				renderFiles(res.data.results, kw);
			});
		} else {
			var tbls = $('.db-tbl:checked').map(function () { return this.value; }).get();
			DBAjax('search', 'database', { keyword: kw, tables: tbls }).done(function (res) {
				if (!res.success) { $r.html(''); DBToast.show(res.data || 'Search failed', 'error'); return; }
				renderDb(res.data.results, kw);
			});
		}
	}

	function hl(text, kw) {
		var re = new RegExp('(' + kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
		return DBEsc(text).replace(re, '<mark>$1</mark>');
	}

	function renderFiles(results, kw) {
		if (!results.length) { $('#db-search-results').html(emptyState('No files matched “' + DBEsc(kw) + '”.')); return; }
		var h = '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">' + results.length + ' file' + (results.length !== 1 ? 's' : '') + ' matched</h3></div></div>';
		results.forEach(function (f) {
			h += '<div class="db-card"><div class="db-card-head">'
				+ '<h3 class="db-card-title">' + DBFileIcon(f.ext) + ' <span class="db-mono" style="font-size:13px">' + DBEsc(f.path) + '</span> <span class="db-badge db-badge-accent">' + f.count + '</span></h3>'
				+ '<button class="db-btn db-btn-sm db-search-edit" data-path="' + DBEsc(f.path) + '" data-name="' + DBEsc(f.name) + '" data-line="' + (f.matches[0] ? f.matches[0].line : 1) + '">✏️ Edit</button>'
				+ '</div><div class="db-card-body flush">';
			f.matches.forEach(function (m) {
				h += '<div class="db-flex db-gap-12 db-search-jump" data-path="' + DBEsc(f.path) + '" data-name="' + DBEsc(f.name) + '" data-line="' + m.line + '" style="padding:7px 16px;border-bottom:1px solid var(--db-border);cursor:pointer;font-family:var(--db-mono);font-size:12px">'
					+ '<span class="db-muted" style="min-width:48px;text-align:right">' + m.line + '</span>'
					+ '<span style="white-space:pre-wrap;word-break:break-word">' + hl(m.text, kw) + '</span></div>';
			});
			h += '</div></div>';
		});
		$('#db-search-results').html(h);
	}

	function renderDb(results, kw) {
		if (!results.length) { $('#db-search-results').html(emptyState('No database rows matched “' + DBEsc(kw) + '”.')); return; }
		var h = '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">Matches in ' + results.length + ' table' + (results.length !== 1 ? 's' : '') + '</h3></div></div>';
		results.forEach(function (t) {
			h += '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">' + DBFileIcon('sql') + ' <span class="db-mono">' + DBEsc(t.table) + '</span> <span class="db-badge db-badge-accent">' + t.total + ' total</span></h3>'
				+ '<a class="db-btn db-btn-ghost db-btn-sm" href="<?php echo admin_url('admin.php?page=devbench-database'); ?>">Open table →</a></div><div class="db-card-body flush">';
			t.hits.forEach(function (row) {
				h += '<div style="padding:9px 16px;border-bottom:1px solid var(--db-border)"><div class="db-muted db-mono" style="font-size:11px;margin-bottom:4px">id: ' + DBEsc(row.id) + '</div>';
				row.cells.forEach(function (c) {
					h += '<div style="font-size:12px;margin-bottom:2px"><strong class="db-mono">' + DBEsc(c.col) + ':</strong> ' + hl(c.snippet, kw) + '</div>';
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
	$('#db-search-kw').on('keydown', function (e) { if (e.key === 'Enter') run(); });

	// Edit handlers (delegated, no stopPropagation)
	$('#db-search-results').on('click', '.db-search-edit', function (e) {
		e.stopPropagation();
		DBOpenEditor($(this).data('path'), $(this).data('name'), parseInt($(this).data('line'), 10));
	});
	$('#db-search-results').on('click', '.db-search-jump', function () {
		DBOpenEditor($(this).data('path'), $(this).data('name'), parseInt($(this).data('line'), 10));
	});
};
</script>

<?php include __DIR__ . '/_footer.php'; ?>
