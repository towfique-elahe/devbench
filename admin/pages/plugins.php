<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-plugins';
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('plug',22); ?> Plugins &amp; Themes</h1>
	<p>Activate or deactivate plugins and switch the active theme from one place.</p>
</div>

<div class="db-tabs" id="db-pt-tabs">
	<button class="db-tab active" data-pt="plugins">Plugins</button>
	<button class="db-tab" data-pt="themes">Themes</button>
</div>

<div id="db-pt-plugins">
	<div class="db-card db-mb-0">
		<div class="db-card-head"><h3 class="db-card-title"><?php DevBench_Helpers::the_icon('plug',16); ?> Installed Plugins <span class="db-badge db-badge-gray" id="db-pl-count">—</span></h3></div>
		<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table">
			<thead><tr><th>Plugin</th><th style="width:100px">Version</th><th style="width:110px">Status</th><th style="width:140px">Action</th></tr></thead>
			<tbody id="db-pl-rows"><tr><td colspan="4" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr></tbody>
		</table></div></div>
	</div>
</div>

<div id="db-pt-themes" class="db-hidden">
	<div class="db-grid db-grid-3" id="db-th-grid"><div style="padding:24px"><span class="db-spinner"></span></div></div>
</div>

<script>
window.DBPages['devbench-plugins'] = function () {
	var $ = jQuery;

	$('#db-pt-tabs .db-tab').on('click', function () {
		$('#db-pt-tabs .db-tab').removeClass('active'); $(this).addClass('active');
		var t = $(this).data('pt');
		$('#db-pt-plugins').toggleClass('db-hidden', t !== 'plugins');
		$('#db-pt-themes').toggleClass('db-hidden', t !== 'themes');
		if (t === 'themes' && !$('#db-th-grid').data('loaded')) loadThemes();
	});

	function loadPlugins() {
		DBAjax('extra', 'plugins_list').done(function (r) {
			if (!r.success) return;
			$('#db-pl-count').text(r.data.length);
			var h = '';
			r.data.forEach(function (p) {
				h += '<tr><td><div style="font-weight:600">' + DBEsc(p.name) + '</div><div class="db-muted" style="font-size:11px;max-width:520px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + DBEsc(p.description) + '</div></td>'
					+ '<td class="db-muted">' + DBEsc(p.version) + '</td>'
					+ '<td><span class="db-badge ' + (p.active ? 'db-badge-green' : 'db-badge-gray') + '">' + (p.active ? 'Active' : 'Inactive') + '</span></td>'
					+ '<td><button class="db-btn db-btn-xs db-pl-toggle" data-file="' + DBEsc(p.file) + '" data-active="' + (p.active ? '1' : '0') + '">' + (p.active ? 'Deactivate' : 'Activate') + '</button></td></tr>';
			});
			$('#db-pl-rows').html(h);
		});
	}
	$('#db-pl-rows').on('click', '.db-pl-toggle', function () {
		var file = $(this).data('file'), active = String($(this).data('active')) === '1';
		DBAjax('extra', 'plugin_toggle', { file: file, activate: active ? '0' : '1' }).done(function (r) {
			if (r.success) { DBToast.show(active ? 'Deactivated' : 'Activated', 'success'); loadPlugins(); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	});

	function loadThemes() {
		$('#db-th-grid').data('loaded', true);
		DBAjax('extra', 'themes_list').done(function (r) {
			if (!r.success) return;
			var h = '';
			r.data.forEach(function (t) {
				h += '<div class="db-card db-mb-0"><div class="db-card-body">'
					+ (t.screenshot ? '<img src="' + DBEsc(t.screenshot) + '" style="width:100%;border-radius:8px;margin-bottom:12px;border:1px solid var(--db-border)">' : '')
					+ '<div class="db-flex-between"><strong>' + DBEsc(t.name) + '</strong>' + (t.active ? '<span class="db-badge db-badge-green">Active</span>' : '') + '</div>'
					+ '<div class="db-muted" style="font-size:12px;margin:4px 0 12px">v' + DBEsc(t.version) + ' · ' + DBEsc(t.author) + '</div>'
					+ (t.active ? '' : '<button class="db-btn db-btn-sm db-th-activate" data-slug="' + DBEsc(t.slug) + '">Activate</button>')
					+ '</div></div>';
			});
			$('#db-th-grid').html(h);
		});
	}
	$('#db-th-grid').on('click', '.db-th-activate', function () {
		if (!confirm('Switch the active theme?')) return;
		DBAjax('extra', 'theme_activate', { slug: $(this).data('slug') }).done(function (r) {
			if (r.success) { DBToast.show('Theme activated', 'success'); $('#db-th-grid').data('loaded', false); loadThemes(); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	});

	loadPlugins();
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
