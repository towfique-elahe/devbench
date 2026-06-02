<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-notes';
include __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('note',22); ?> Quick Notes</h1>
	<p>A persistent scratchpad for snippets, credentials reminders, and to-dos — stored in your database.</p>
</div>

<div class="db-grid" style="grid-template-columns:300px 1fr;gap:16px;align-items:start">
	<div class="db-card db-mb-0">
		<div class="db-card-head"><h3 class="db-card-title">Notes</h3><button class="db-btn db-btn-primary db-btn-sm" id="db-note-new">+ New</button></div>
		<div class="db-card-body flush" id="db-note-list" style="max-height:70vh;overflow:auto"><div style="padding:16px;text-align:center"><span class="db-spinner"></span></div></div>
	</div>

	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<input type="text" class="db-input" id="db-note-title" placeholder="Note title" style="max-width:360px;border:none;font-weight:600;box-shadow:none;padding-left:0">
			<div class="db-flex db-gap-8">
				<label class="db-flex db-gap-8" style="font-size:13px;cursor:pointer"><input type="checkbox" id="db-note-pin"> Pin</label>
				<button class="db-btn db-btn-success db-btn-sm" id="db-note-save">Save</button>
			</div>
		</div>
		<div class="db-card-body">
			<textarea class="db-textarea mono" id="db-note-body" rows="18" placeholder="Start typing… (Ctrl+S to save)"></textarea>
		</div>
	</div>
</div>

<script>
window.DBPages['devbench-notes'] = function () {
	var $ = jQuery, notes = [], current = null;

	function render() {
		var h = '';
		if (!notes.length) h = '<div class="db-empty"><p>No notes yet.</p></div>';
		notes.forEach(function (n) {
			h += '<div class="db-note-item" data-id="' + DBEsc(n.id) + '" style="padding:12px 16px;border-bottom:1px solid var(--db-border);cursor:pointer' + (current === n.id ? ';background:var(--db-accent-soft)' : '') + '">'
				+ '<div class="db-flex db-gap-4"><strong style="font-size:13px">' + (n.pinned ? DBIcon('pin', 13) + ' ' : '') + DBEsc(n.title) + '</strong></div>'
				+ '<div class="db-muted" style="font-size:11px;margin-top:2px">' + new Date(n.updated * 1000).toLocaleString() + '</div></div>';
		});
		$('#db-note-list').html(h);
	}

	function select(id) {
		var n = notes.find(function (x) { return x.id === id; });
		if (!n) return;
		current = id;
		$('#db-note-title').val(n.title);
		$('#db-note-body').val(n.body);
		$('#db-note-pin').prop('checked', !!n.pinned);
		render();
	}

	$('#db-note-list').on('click', '.db-note-item', function () { select($(this).data('id')); });

	$('#db-note-new').on('click', function () {
		current = null;
		$('#db-note-title').val(''); $('#db-note-body').val(''); $('#db-note-pin').prop('checked', false);
		render(); $('#db-note-title').focus();
	});

	function save() {
		var data = { id: current || '', title: $('#db-note-title').val() || 'Untitled', body: $('#db-note-body').val(), pinned: $('#db-note-pin').is(':checked') ? '1' : '0' };
		DBAjax('extra', 'note_save', data).done(function (r) {
			if (r.success) { DBToast.show('Saved', 'success'); boot(); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	}
	$('#db-note-save').on('click', save);
	$('#db-note-body, #db-note-title').on('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(); } });

	function boot() {
		DBAjax('extra', 'note_list').done(function (r) {
			if (r && r.success) { notes = r.data || []; render(); }
		});
	}
	boot();
};
</script>

<?php include __DIR__ . '/_footer.php'; ?>
