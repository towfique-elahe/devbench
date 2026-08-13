<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-database';
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('database',22); ?> Database Manager</h1>
	<p>Browse tables, inspect structure, run SQL, and export data. Destructive statements (DROP/TRUNCATE) are blocked.</p>
</div>

<div class="db-grid" style="grid-template-columns:280px 1fr;gap:16px;align-items:start">
	<!-- Table list -->
	<div class="db-card db-mb-0">
		<div class="db-card-head"><h3 class="db-card-title">Tables</h3></div>
		<div class="db-card-body flush" style="max-height:70vh;overflow:auto">
			<div id="db-db-tables" style="padding:6px"><div style="padding:16px;text-align:center"><span class="db-spinner"></span></div></div>
		</div>
	</div>

	<!-- Work area -->
	<div style="min-width:0">
		<div class="db-card">
			<div class="db-card-head">
				<h3 class="db-card-title"><?php DevBench_Helpers::the_icon('terminal',16); ?> SQL Query</h3>
			</div>
			<div class="db-card-body">
				<textarea class="db-textarea mono" id="db-db-sql" rows="3" placeholder="SELECT * FROM wp_options LIMIT 10"></textarea>
				<div class="db-flex db-gap-8 db-mt-12">
					<button class="db-btn db-btn-primary" id="db-db-run"><?php DevBench_Helpers::the_icon('zap',15); ?> Run Query</button>
					<span class="db-muted" style="font-size:12px;align-self:center"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> to run</span>
				</div>
			</div>
		</div>

		<div id="db-db-result"></div>
	</div>
</div>

<script>
window.DBPages['devbench-database'] = function () {
	var $ = jQuery, current = null, curPage = 1;

	DBAjax('database', 'tables').done(function (r) {
		if (!r.success) { DBToast.show('Failed to load tables', 'error'); return; }
		var h = '';
		r.data.forEach(function (t) {
			h += '<a href="#" class="db-nav-item db-db-table" data-table="' + DBEsc(t.name) + '" style="margin-bottom:1px">'
				+ '<span style="flex:1;overflow:hidden;text-overflow:ellipsis" class="db-mono">' + DBEsc(t.name) + '</span>'
				+ '<span class="db-badge db-badge-gray">' + t.rows + '</span></a>';
		});
		$('#db-db-tables').html(h);
	});

	$('#db-db-tables').on('click', '.db-db-table', function (e) {
		e.preventDefault();
		$('.db-db-table').removeClass('active'); $(this).addClass('active');
		current = $(this).data('table'); curPage = 1;
		browse();
	});

	function browse() {
		$('#db-db-result').html('<div class="db-card"><div class="db-card-body"><span class="db-spinner"></span></div></div>');
		DBAjax('database', 'browse', { table: current, page: curPage }).done(function (r) {
			if (!r.success) { DBToast.show(r.data || 'Failed', 'error'); return; }
			renderTable(r.data);
		});
	}

	function renderTable(d) {
		var h = '<div class="db-card"><div class="db-card-head">'
			+ '<h3 class="db-card-title"><span class="db-mono">' + DBEsc(current) + '</span> <span class="db-badge db-badge-gray">' + d.total + ' rows</span></h3>'
			+ '<div class="db-flex db-gap-8">'
			+ '<button class="db-btn db-btn-ghost db-btn-sm" id="db-db-structure">Structure</button>'
			+ '<button class="db-btn db-btn-ghost db-btn-sm" id="db-db-export">Export SQL</button>'
			+ '</div></div><div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table"><thead><tr>';
		d.columns.forEach(function (c) { h += '<th>' + DBEsc(c) + '</th>'; });
		h += '</tr></thead><tbody>';
		if (!d.rows.length) h += '<tr><td colspan="' + d.columns.length + '"><div class="db-empty"><p>No rows.</p></div></td></tr>';
		d.rows.forEach(function (row) {
			h += '<tr>';
			d.columns.forEach(function (c) {
				var v = row[c]; if (v === null) v = '<span class="db-muted">NULL</span>'; else v = DBEsc(String(v).substring(0, 160));
				h += '<td class="mono" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + v + '</td>';
			});
			h += '</tr>';
		});
		h += '</tbody></table></div>';
		if (d.pages > 1) {
			h += '<div class="db-flex-between" style="padding:12px 16px;border-top:1px solid var(--db-border)">'
				+ '<span class="db-muted" style="font-size:12px">Page ' + d.page + ' of ' + d.pages + '</span>'
				+ '<div class="db-flex db-gap-8">'
				+ '<button class="db-btn db-btn-sm" id="db-db-prev" ' + (d.page <= 1 ? 'disabled' : '') + '>← Prev</button>'
				+ '<button class="db-btn db-btn-sm" id="db-db-next" ' + (d.page >= d.pages ? 'disabled' : '') + '>Next →</button>'
				+ '</div></div>';
		}
		h += '</div></div>';
		$('#db-db-result').html(h);
	}

	$('#db-db-result').on('click', '#db-db-prev', function () { curPage--; browse(); });
	$('#db-db-result').on('click', '#db-db-next', function () { curPage++; browse(); });
	$('#db-db-result').on('click', '#db-db-structure', function () {
		DBAjax('database', 'structure', { table: current }).done(function (r) {
			if (!r.success) return;
			var h = '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">Structure: <span class="db-mono">' + DBEsc(current) + '</span></h3><button class="db-btn db-btn-ghost db-btn-sm" id="db-db-back">← Back to data</button></div>'
				+ '<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table"><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead><tbody>';
			r.data.forEach(function (c) {
				h += '<tr><td class="mono" style="font-weight:600">' + DBEsc(c.Field) + '</td><td class="mono">' + DBEsc(c.Type) + '</td><td>' + DBEsc(c.Null) + '</td><td>' + DBEsc(c.Key || '—') + '</td><td class="mono">' + DBEsc(c.Default === null ? 'NULL' : c.Default) + '</td><td class="db-muted">' + DBEsc(c.Extra || '') + '</td></tr>';
			});
			h += '</tbody></table></div></div></div>';
			$('#db-db-result').html(h);
		});
	});
	$('#db-db-result').on('click', '#db-db-back', browse);
	$('#db-db-result').on('click', '#db-db-export', function () {
		DBAjax('database', 'export', { table: current }).done(function (r) {
			if (!r.success) { DBToast.show(r.data || 'Failed', 'error'); return; }
			var blob = new Blob([r.data.sql], { type: 'text/plain' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob); a.download = current + '.sql'; a.click();
			DBToast.show('Export downloaded', 'success');
		});
	});

	function runQuery() {
		var sql = $('#db-db-sql').val().trim();
		if (!sql) return;
		$('#db-db-result').html('<div class="db-card"><div class="db-card-body"><span class="db-spinner"></span></div></div>');
		DBAjax('database', 'query', { sql: sql }).done(function (r) {
			if (!r.success) { $('#db-db-result').html('<div class="db-alert db-alert-error" style="margin:0"><strong>SQL Error:</strong> ' + DBEsc(r.data) + '</div>'); return; }
			if (r.data.type === 'write') {
				$('#db-db-result').html('<div class="db-alert db-alert-ok" style="margin:0">Query OK — ' + r.data.affected + ' row(s) affected.</div>');
				DBToast.show('Query executed', 'success'); return;
			}
			var d = r.data, h = '<div class="db-card"><div class="db-card-head"><h3 class="db-card-title">Result <span class="db-badge db-badge-gray">' + d.count + ' rows</span></h3></div><div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table"><thead><tr>';
			d.columns.forEach(function (c) { h += '<th>' + DBEsc(c) + '</th>'; });
			h += '</tr></thead><tbody>';
			if (!d.rows.length) h += '<tr><td colspan="' + Math.max(d.columns.length,1) + '"><div class="db-empty"><p>No rows returned.</p></div></td></tr>';
			d.rows.forEach(function (row) {
				h += '<tr>';
				d.columns.forEach(function (c) { var v = row[c]; h += '<td class="mono" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (v === null ? '<span class="db-muted">NULL</span>' : DBEsc(String(v).substring(0,160))) + '</td>'; });
				h += '</tr>';
			});
			h += '</tbody></table></div></div></div>';
			$('#db-db-result').html(h);
		});
	}
	$('#db-db-run').on('click', runQuery);
	$('#db-db-sql').on('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runQuery(); } });
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
