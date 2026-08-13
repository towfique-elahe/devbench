<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-cron';
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('repeat',22); ?> Cron Manager</h1>
	<p>View scheduled WordPress cron events, run them on demand, or unschedule them.</p>
</div>

<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title"><?php DevBench_Helpers::the_icon('clock',16); ?> Scheduled Events <span class="db-badge db-badge-gray" id="db-cron-count">—</span></h3>
		<button class="db-btn db-btn-ghost db-btn-sm" id="db-cron-refresh"><?php DevBench_Helpers::the_icon('refresh',14); ?> Refresh</button>
	</div>
	<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table">
		<thead><tr><th>Hook</th><th style="width:150px">Schedule</th><th style="width:200px">Next Run</th><th style="width:160px">Actions</th></tr></thead>
		<tbody id="db-cron-rows"><tr><td colspan="4" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr></tbody>
	</table></div></div>
</div>

<script>
window.DBPages['devbench-cron'] = function () {
	var $ = jQuery;
	function load() {
		$('#db-cron-rows').html('<tr><td colspan="4" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr>');
		DBAjax('tools', 'cron_list').done(function (r) {
			if (!r.success) return;
			$('#db-cron-count').text(r.data.length);
			if (!r.data.length) { $('#db-cron-rows').html('<tr><td colspan="4"><div class="db-empty"><p>No cron events scheduled.</p></div></td></tr>'); return; }
			var h = '';
			r.data.forEach(function (e) {
				var when = new Date(e.next * 1000).toLocaleString();
				h += '<tr><td class="db-mono" style="font-weight:600">' + DBEsc(e.hook) + '</td>'
					+ '<td><span class="db-badge db-badge-blue">' + DBEsc(e.schedule) + '</span><div class="db-muted" style="font-size:11px;margin-top:2px">' + DBEsc(e.interval) + '</div></td>'
					+ '<td class="db-muted" style="font-size:12px">' + when + (e.overdue ? ' <span class="db-badge db-badge-amber">overdue</span>' : '') + '</td>'
					+ '<td><div class="db-flex db-gap-8"><button class="db-btn db-btn-xs db-cron-run" data-hook="' + DBEsc(e.hook) + '">Run now</button><button class="db-btn db-btn-xs db-btn-danger db-cron-del" data-hook="' + DBEsc(e.hook) + '">Unschedule</button></div></td></tr>';
			});
			$('#db-cron-rows').html(h);
		});
	}
	$('#db-cron-refresh').on('click', load);
	$('#db-cron-rows').on('click', '.db-cron-run', function () {
		DBAjax('tools', 'cron_run', { hook: $(this).data('hook') }).done(function (r) { if (r.success) DBToast.show('Hook fired', 'success'); });
	});
	$('#db-cron-rows').on('click', '.db-cron-del', function () {
		if (!confirm('Unschedule this event?')) return;
		DBAjax('tools', 'cron_unschedule', { hook: $(this).data('hook') }).done(function (r) { if (r.success) { DBToast.show('Unscheduled', 'success'); load(); } });
	});
	load();
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
