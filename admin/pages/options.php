<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-options';
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('list',22); ?> Options Manager</h1>
	<p>Browse, edit, and delete entries in the <code>wp_options</code> table. Sorted by value size to surface bloat.</p>
</div>

<div class="db-card">
	<div class="db-card-head" style="gap:10px">
		<div class="db-flex db-gap-8" style="flex:1">
			<input type="text" class="db-input db-btn-sm" id="db-opt-search" placeholder="Search option name…" style="max-width:280px;height:30px">
			<select class="db-select db-btn-sm" id="db-opt-autoload" style="width:auto;height:30px">
				<option value="all">All</option>
				<option value="yes">Autoloaded only</option>
				<option value="no">Not autoloaded</option>
			</select>
			<button class="db-btn db-btn-primary db-btn-sm" id="db-opt-go">Search</button>
		</div>
	</div>
	<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table">
		<thead><tr><th>Option Name</th><th style="width:90px">Size</th><th style="width:90px">Autoload</th><th style="width:160px">Actions</th></tr></thead>
		<tbody id="db-opt-rows"><tr><td colspan="4" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr></tbody>
	</table></div></div>
</div>

<!-- Edit modal -->
<div class="db-modal-overlay" id="db-opt-modal">
	<div class="db-modal" style="max-width:640px">
		<h3 id="db-opt-modal-title">Edit Option</h3>
		<div class="db-field"><label class="db-label">Value</label><textarea class="db-textarea mono" id="db-opt-value" rows="12"></textarea></div>
		<div class="db-modal-foot">
			<button class="db-btn" id="db-opt-cancel">Cancel</button>
			<button class="db-btn db-btn-primary" id="db-opt-save">Save</button>
		</div>
	</div>
</div>

<script>
window.DBPages['devbench-options'] = function () {
	var $ = jQuery, editing = null;

	function load() {
		$('#db-opt-rows').html('<tr><td colspan="4" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr>');
		DBAjax('tools', 'options_list', { search: $('#db-opt-search').val(), autoload: $('#db-opt-autoload').val() }).done(function (r) {
			if (!r.success) return;
			if (!r.data.length) { $('#db-opt-rows').html('<tr><td colspan="4"><div class="db-empty"><p>No options found.</p></div></td></tr>'); return; }
			var h = '';
			r.data.forEach(function (o) {
				h += '<tr><td><div class="db-mono" style="font-weight:600">' + DBEsc(o.name) + '</div><div class="db-muted" style="font-size:11px;max-width:520px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + DBEsc(o.preview) + '</div></td>'
					+ '<td class="db-muted">' + DBHumanSize(o.size) + '</td>'
					+ '<td><span class="db-badge ' + (o.autoload === 'yes' ? 'db-badge-amber' : 'db-badge-gray') + '">' + o.autoload + '</span></td>'
					+ '<td><div class="db-flex db-gap-8"><button class="db-btn db-btn-xs db-opt-edit" data-name="' + DBEsc(o.name) + '">Edit</button><button class="db-btn db-btn-xs db-btn-danger db-opt-del" data-name="' + DBEsc(o.name) + '">Delete</button></div></td></tr>';
			});
			$('#db-opt-rows').html(h);
		});
	}

	$('#db-opt-go').on('click', load);
	$('#db-opt-search').on('keydown', function (e) { if (e.key === 'Enter') load(); });
	$('#db-opt-autoload').on('change', load);

	$('#db-opt-rows').on('click', '.db-opt-edit', function () {
		editing = $(this).data('name');
		$('#db-opt-modal-title').text('Edit: ' + editing);
		$('#db-opt-value').val('Loading…');
		$('#db-opt-modal').addClass('open');
		DBAjax('tools', 'option_get', { name: editing }).done(function (r) { if (r.success) $('#db-opt-value').val(r.data.value); });
	});
	$('#db-opt-rows').on('click', '.db-opt-del', function () {
		var name = $(this).data('name');
		if (!confirm('Delete option "' + name + '"?')) return;
		DBAjax('tools', 'option_delete', { name: name }).done(function (r) { if (r.success) { DBToast.show('Deleted', 'success'); load(); } });
	});
	$('#db-opt-cancel').on('click', function () { $('#db-opt-modal').removeClass('open'); });
	$('#db-opt-modal').on('click', function (e) { if ($(e.target).is('#db-opt-modal')) $(this).removeClass('open'); });
	$('#db-opt-save').on('click', function () {
		DBAjax('tools', 'option_update', { name: editing, value: $('#db-opt-value').val() }).done(function (r) {
			if (r.success) { DBToast.show('Saved', 'success'); $('#db-opt-modal').removeClass('open'); load(); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	});

	load();
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
