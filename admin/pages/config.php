<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id  = 'devbench-config';
$devbench_writable = DevBench_Helpers::config_writable();
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('sliders',22); ?> WP Config Editor</h1>
	<p>Manage constants defined in <code>wp-config.php</code> safely, without a text editor.</p>
</div>

<?php if ( ! $devbench_writable ) : ?>
<div class="db-alert db-alert-warn"><?php DevBench_Helpers::the_icon('info',17); ?><div><strong>wp-config.php is not writable.</strong> You can view constants but not change them.</div></div>
<?php endif; ?>

<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title"><?php DevBench_Helpers::the_icon('code',16); ?> Defined Constants</h3>
		<button class="db-btn db-btn-primary db-btn-sm" id="db-cfg-add" <?php disabled( ! $devbench_writable ); ?>>+ Add Constant</button>
	</div>
	<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table">
		<thead><tr><th>Name</th><th>Value</th><th style="width:90px">Type</th><th style="width:160px">Actions</th></tr></thead>
		<tbody id="db-cfg-rows"><tr><td colspan="4" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr></tbody>
	</table></div></div>
</div>

<!-- Modal -->
<div class="db-modal-overlay" id="db-cfg-modal">
	<div class="db-modal">
		<h3 id="db-cfg-modal-title">Add Constant</h3>
		<div class="db-field"><label class="db-label">Name</label><input type="text" class="db-input mono" id="db-cfg-name" placeholder="WP_MEMORY_LIMIT"></div>
		<div class="db-field"><label class="db-label">Type</label>
			<select class="db-select" id="db-cfg-type"><option value="string">String</option><option value="bool">Boolean</option><option value="int">Integer</option></select>
		</div>
		<div class="db-field"><label class="db-label">Value</label><input type="text" class="db-input mono" id="db-cfg-value" placeholder="256M"></div>
		<div class="db-modal-foot">
			<button class="db-btn" id="db-cfg-cancel">Cancel</button>
			<button class="db-btn db-btn-primary" id="db-cfg-save">Save</button>
		</div>
	</div>
</div>

<script>
window.DBPages['devbench-config'] = function () {
	var $ = jQuery, writable = <?php echo $devbench_writable ? 'true' : 'false'; ?>;

	function load() {
		DBAjax('extra', 'config_list').done(function (r) {
			if (!r.success) return;
			if (!r.data.length) { $('#db-cfg-rows').html('<tr><td colspan="4"><div class="db-empty"><p>No constants found.</p></div></td></tr>'); return; }
			var h = '';
			r.data.forEach(function (c) {
				var badge = c.type === 'bool' ? 'db-badge-accent' : (c.type === 'int' ? 'db-badge-blue' : 'db-badge-gray');
				var vColor = c.value === 'true' ? 'color:var(--db-green)' : (c.value === 'false' ? 'color:var(--db-red)' : '');
				h += '<tr><td class="db-mono" style="font-weight:600">' + DBEsc(c.name) + '</td>'
					+ '<td class="db-mono" style="' + vColor + ';max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + DBEsc(c.value) + '</td>'
					+ '<td><span class="db-badge ' + badge + '">' + c.type + '</span></td>'
					+ '<td><div class="db-flex db-gap-8">'
					+ (c.protected
						? '<span class="db-muted" style="font-size:12px">protected</span>'
						: (writable
							? '<button class="db-btn db-btn-xs db-cfg-edit" data-name="' + DBEsc(c.name) + '" data-value="' + DBEsc(c.value) + '" data-type="' + c.type + '">Edit</button><button class="db-btn db-btn-xs db-btn-danger db-cfg-del" data-name="' + DBEsc(c.name) + '">Delete</button>'
							: '<span class="db-muted" style="font-size:12px">read-only</span>'))
					+ '</div></td></tr>';
			});
			$('#db-cfg-rows').html(h);
		});
	}

	function openModal(title, name, value, type, lockName) {
		$('#db-cfg-modal-title').text(title);
		$('#db-cfg-name').val(name || '').prop('disabled', !!lockName);
		$('#db-cfg-value').val(value || '');
		$('#db-cfg-type').val(type || 'string');
		$('#db-cfg-modal').addClass('open');
	}
	$('#db-cfg-add').on('click', function () { openModal('Add Constant', '', '', 'string', false); });
	$('#db-cfg-rows').on('click', '.db-cfg-edit', function () {
		openModal('Edit Constant', $(this).data('name'), String($(this).data('value')), $(this).data('type'), true);
	});
	$('#db-cfg-rows').on('click', '.db-cfg-del', function () {
		var name = $(this).data('name');
		if (!confirm('Delete constant ' + name + '?')) return;
		DBAjax('extra', 'config_delete', { name: name }).done(function (r) { if (r.success) { DBToast.show('Deleted', 'success'); load(); } else DBToast.show(r.data || 'Failed', 'error'); });
	});
	$('#db-cfg-cancel').on('click', function () { $('#db-cfg-modal').removeClass('open'); });
	$('#db-cfg-modal').on('click', function (e) { if ($(e.target).is('#db-cfg-modal')) $(this).removeClass('open'); });
	$('#db-cfg-save').on('click', function () {
		DBAjax('extra', 'config_set', { name: $('#db-cfg-name').val(), value: $('#db-cfg-value').val(), type: $('#db-cfg-type').val() }).done(function (r) {
			if (r.success) { DBToast.show('Saved', 'success'); $('#db-cfg-modal').removeClass('open'); load(); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	});

	load();
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
