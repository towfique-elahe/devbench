<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-transients';
include __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('clock',22); ?> Transients</h1>
	<p>Inspect cached transients, spot expired entries, and clear them to free space.</p>
</div>

<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title"><?php echo DevBench_Helpers::icon('clock',16); ?> Stored Transients <span class="db-badge db-badge-gray" id="db-tr-count">—</span></h3>
		<div class="db-flex db-gap-8">
			<button class="db-btn db-btn-ghost db-btn-sm" id="db-tr-refresh"><?php echo DevBench_Helpers::icon('refresh',14); ?> Refresh</button>
			<button class="db-btn db-btn-danger db-btn-sm" id="db-tr-clear">Clear Expired</button>
		</div>
	</div>
	<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table">
		<thead><tr><th>Key</th><th style="width:90px">Size</th><th style="width:110px">Status</th><th style="width:170px">Expires</th><th style="width:90px">Action</th></tr></thead>
		<tbody id="db-tr-rows"><tr><td colspan="5" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr></tbody>
	</table></div></div>
</div>

<script>
window.DBPages['devbench-transients'] = function () {
	var $ = jQuery;
	function load() {
		$('#db-tr-rows').html('<tr><td colspan="5" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr>');
		DBAjax('tools', 'transients_list').done(function (r) {
			if (!r.success) return;
			$('#db-tr-count').text(r.data.length);
			if (!r.data.length) { $('#db-tr-rows').html('<tr><td colspan="5"><div class="db-empty"><p>No transients stored.</p></div></td></tr>'); return; }
			var h = '';
			r.data.forEach(function (t) {
				var badge = t.status === 'active' ? 'db-badge-green' : (t.status === 'expired' ? 'db-badge-red' : 'db-badge-blue');
				var exp = t.expires === 0 ? 'Never' : new Date(t.expires * 1000).toLocaleString();
				h += '<tr><td class="db-mono" style="font-weight:600">' + DBEsc(t.key) + '</td>'
					+ '<td class="db-muted">' + DBHumanSize(t.size) + '</td>'
					+ '<td><span class="db-badge ' + badge + '">' + t.status + '</span></td>'
					+ '<td class="db-muted" style="font-size:12px">' + exp + '</td>'
					+ '<td><button class="db-btn db-btn-xs db-btn-danger db-tr-del" data-key="' + DBEsc(t.key) + '">Delete</button></td></tr>';
			});
			$('#db-tr-rows').html(h);
		});
	}
	$('#db-tr-refresh').on('click', load);
	$('#db-tr-rows').on('click', '.db-tr-del', function () {
		DBAjax('tools', 'transient_delete', { key: $(this).data('key') }).done(function (r) { if (r.success) { DBToast.show('Deleted', 'success'); load(); } });
	});
	$('#db-tr-clear').on('click', function () {
		if (!confirm('Clear all expired transients?')) return;
		DBAjax('tools', 'transients_clear_expired').done(function (r) { if (r.success) { DBToast.show('Cleared ' + r.data.cleared + ' expired', 'success'); load(); } });
	});
	load();
};
</script>

<?php include __DIR__ . '/_footer.php'; ?>
