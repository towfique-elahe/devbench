<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-notes';
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('note',22); ?> Quick Notes</h1>
	<p>A persistent scratchpad for snippets, credentials reminders, and to-dos — stored in your database.</p>
</div>

<div class="db-grid" style="grid-template-columns:300px minmax(0, 1fr);gap:16px;align-items:start">
	<div class="db-card db-mb-0">
		<div class="db-card-head"><h3 class="db-card-title">Notes</h3><button class="db-btn db-btn-primary db-btn-sm" id="db-note-new">+ New</button></div>
		<div class="db-card-body flush" id="db-note-list" style="max-height:70vh;overflow:auto"><div style="padding:16px;text-align:center"><span class="db-spinner"></span></div></div>
	</div>

	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<input type="text" class="db-input db-note-title" id="db-note-title"
				placeholder="<?php esc_attr_e( 'Note title', 'devbench' ); ?>"
				aria-label="<?php esc_attr_e( 'Note title', 'devbench' ); ?>">
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
			var title = n.title || 'Untitled';
			h += '<div class="db-note-item db-flex-between" data-id="' + DBEsc(n.id) + '" style="padding:12px 16px;border-bottom:1px solid var(--db-border);cursor:pointer;gap:8px' + (current === n.id ? ';background:var(--db-accent-soft)' : '') + '">'
				+ '<div style="min-width:0">'
				+ '<div class="db-flex db-gap-4"><strong style="font-size:13px">' + (n.pinned ? DBIcon('pin', 13) + ' ' : '') + DBEsc(title) + '</strong></div>'
				+ '<div class="db-muted" style="font-size:11px;margin-top:2px">' + new Date(n.updated * 1000).toLocaleString() + '</div>'
				+ '</div>'
				+ DBAction('button', 'db-note-del db-btn-danger', 'trash', 'Delete note: ' + title,
					'data-id="' + DBEsc(n.id) + '" data-confirm="Delete the note &quot;' + DBEsc(title) + '&quot;? This cannot be undone."')
				+ '</div>';
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

	/* Delete sits inside the clickable row, so keep the click from also
	   selecting the note being removed. */
	$('#db-note-list').on('click', '.db-note-del', function (e) {
		e.stopPropagation();
		var id = $(this).data('id');
		DBAjax('extra', 'note_delete', { id: id }).done(function (r) {
			if (!r.success) { DBToast.show(r.data || 'Failed', 'error'); return; }
			DBToast.show('Note deleted', 'success');
			/* If the open note was the one deleted, clear the editor. Leaving
			   its id in `current` would make the next Save recreate it. */
			if (current === id) blank();
			boot();
		});
	});

	function blank() {
		current = null;
		$('#db-note-title').val(''); $('#db-note-body').val(''); $('#db-note-pin').prop('checked', false);
	}

	$('#db-note-new').on('click', function () {
		blank();
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

<?php require __DIR__ . '/_footer.php'; ?>
