<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-files';
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('folder',22); ?> File Manager</h1>
	<p>Browse, edit, upload, and manage files within your WordPress install. The editor includes session version control.</p>
</div>

<div class="db-card">
	<div class="db-card-head" style="gap:10px">
		<div class="db-breadcrumb" id="db-fm-breadcrumb"></div>
		<div class="db-flex db-gap-8 db-wrap">
			<input type="text" class="db-input db-btn-sm" id="db-fm-search" placeholder="Filter in folder…" style="width:170px;height:30px">
			<button class="db-btn db-btn-sm" id="db-fm-newfile"><?php DevBench_Helpers::the_icon('code',14); ?> New File</button>
			<button class="db-btn db-btn-sm" id="db-fm-newfolder"><?php DevBench_Helpers::the_icon('folder',14); ?> New Folder</button>
			<button class="db-btn db-btn-sm" id="db-fm-upload"><?php DevBench_Helpers::the_icon('upload',14); ?> Upload</button>
			<input type="file" id="db-fm-file-input" class="db-hidden">
		</div>
	</div>

	<div id="db-fm-bulkbar" class="db-flex-between db-hidden" style="padding:8px 20px;background:var(--db-accent-soft);border-bottom:1px solid var(--db-border)">
		<span class="db-mono" style="font-size:12px;color:var(--db-accent)" id="db-fm-selcount">0 selected</span>
		<div class="db-flex db-gap-8">
			<button class="db-btn db-btn-sm" id="db-fm-bulkzip"><?php DevBench_Helpers::the_icon( 'archive', 14 ); ?> <?php esc_html_e( 'Zip selected', 'devbench' ); ?></button>
			<button class="db-btn db-btn-danger db-btn-sm" id="db-fm-bulkdelete"><?php esc_html_e( 'Delete selected', 'devbench' ); ?></button>
		</div>
	</div>

	<div class="db-card-body flush">
		<div id="db-fm-dropzone" class="db-dropzone db-hidden" style="margin:16px">Drop files here to upload</div>
		<div class="db-table-wrap"><table class="db-table">
			<thead><tr>
				<th style="width:34px"><input type="checkbox" id="db-fm-selectall"></th>
				<th>Name</th><th style="width:90px">Size</th><th style="width:90px">Perms</th><th style="width:150px">Modified</th><th style="width:170px">Actions</th>
			</tr></thead>
			<tbody id="db-fm-rows"><tr><td colspan="6" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr></tbody>
		</table></div>
	</div>
</div>

<?php require __DIR__ . '/_editor_modal.php'; ?>

<!-- Generic modal -->
<div class="db-modal-overlay" id="db-fm-modal">
	<div class="db-modal">
		<h3 id="db-fm-modal-title">Modal</h3>
		<div id="db-fm-modal-body"></div>
		<div class="db-modal-foot">
			<button class="db-btn" id="db-fm-modal-cancel">Cancel</button>
			<button class="db-btn db-btn-primary" id="db-fm-modal-ok">Confirm</button>
		</div>
	</div>
</div>

<script>
window.DBPages['devbench-files'] = function () {
	var $ = jQuery, cwd = '/', items = [], modalCb = null;

	function load(path) {
		cwd = path || '/';
		$('#db-fm-rows').html('<tr><td colspan="6" style="padding:24px;text-align:center"><span class="db-spinner"></span></td></tr>');
		DBAjax('files', 'list', { path: cwd }).done(function (r) {
			if (!r.success) { DBToast.show(r.data || 'Failed', 'error'); return; }
			cwd = r.data.path; items = r.data.items;
			breadcrumb(); render(items);
			$('#db-fm-search').val('');
			$('#db-fm-selectall').prop('checked', false);
			updateBulk();
		});
	}

	function breadcrumb() {
		/* href="#" matters: without it these are not links — no pointer cursor,
		   no tab stop, no Enter. The click handler preventDefaults it. */
		var parts = cwd.split('/').filter(Boolean), h = '<a href="#" data-path="/">root</a>', acc = '';
		parts.forEach(function (p, i) {
			acc += '/' + p;
			h += ' <span>/</span> ' + (i === parts.length - 1
				? '<strong aria-current="page">' + DBEsc(p) + '</strong>'
				: '<a href="#" data-path="' + DBEsc(acc) + '">' + DBEsc(p) + '</a>');
		});
		$('#db-fm-breadcrumb').html(h);
	}

	var action = DBAction;

	function render(list) {
		if (!list.length) { $('#db-fm-rows').html('<tr><td colspan="6"><div class="db-empty"><p>This folder is empty.</p></div></td></tr>'); return; }
		var h = '';
		if (cwd !== '/' && cwd !== '') {
			var up = cwd.split('/').slice(0, -1).join('/') || '/';
			h += '<tr><td></td><td><a class="db-file-name db-fm-cd" data-path="' + DBEsc(up) + '" href="#">' + DBIcon('folder', 15) + ' ..</a></td><td colspan="4" class="db-muted">parent directory</td></tr>';
		}
		list.forEach(function (it) {
			var isDir = it.type === 'dir';
			var name = isDir
				? '<a class="db-file-name db-fm-cd" data-path="' + DBEsc(it.path) + '" href="#">' + DBIcon('folder', 15) + ' ' + DBEsc(it.name) + '</a>'
				: '<a class="db-file-name db-fm-edit" data-path="' + DBEsc(it.path) + '" data-name="' + DBEsc(it.name) + '" href="#"><span class="db-file-icon">' + DBFileIcon(it.ext) + '</span> ' + DBEsc(it.name) + '</a>';
			var data = 'data-path="' + DBEsc(it.path) + '" data-name="' + DBEsc(it.name) + '"';

			h += '<tr>'
				+ '<td><input type="checkbox" class="db-fm-check" value="' + DBEsc(it.path) + '"></td>'
				+ '<td>' + name + '</td>'
				+ '<td class="db-muted">' + (isDir ? '—' : DBHumanSize(it.size)) + '</td>'
				+ '<td class="mono"><span class="db-flex db-gap-4">' + DBEsc(it.perms) + (it.writable ? '' : '<span class="db-muted" title="Read-only">' + DBIcon('lock', 12) + '</span>') + '</span></td>'
				+ '<td class="db-muted" style="font-size:12px">' + new Date(it.modified * 1000).toLocaleString() + '</td>'
				+ '<td><div class="db-flex db-gap-8">'
				+ (isDir ? '' : action('button', 'db-fm-edit', 'code', 'Edit ' + it.name, data))
				+ (isDir ? '' : action('a', '', 'download', 'Download ' + it.name, 'href="' + DBEsc(DBDownloadUrl(it.path)) + '"'))
				+ action('button', 'db-fm-rename', 'edit', 'Rename ' + it.name, data)
				+ action('button', 'db-fm-chmod', 'key', 'Permissions for ' + it.name, 'data-path="' + DBEsc(it.path) + '" data-perms="' + DBEsc(it.perms) + '"')
				+ action('button', 'db-fm-delete db-btn-danger', 'trash', 'Delete ' + it.name,
					data + ' data-confirm="Delete &quot;' + DBEsc(it.name) + '&quot;? This cannot be undone."')
				+ '</div></td></tr>';
		});
		$('#db-fm-rows').html(h);
	}

	/* Modal helper */
	function modal(title, bodyHtml, okLabel, cb) {
		$('#db-fm-modal-title').text(title);
		$('#db-fm-modal-body').html(bodyHtml);
		$('#db-fm-modal-ok').text(okLabel || 'Confirm');
		modalCb = cb;
		$('#db-fm-modal').addClass('open');
		setTimeout(function(){ $('#db-fm-modal-body input').first().focus(); }, 50);
	}
	function closeModal() { $('#db-fm-modal').removeClass('open'); modalCb = null; }
	$('#db-fm-modal-cancel').on('click', closeModal);
	$('#db-fm-modal').on('click', function (e) { if ($(e.target).is('#db-fm-modal')) closeModal(); });
	$('#db-fm-modal-ok').on('click', function () { if (modalCb) modalCb(); });

	/* Navigation + edit (delegated) */
	$('#db-fm-breadcrumb').on('click', 'a', function (e) { e.preventDefault(); load($(this).data('path')); });
	$('#db-fm-rows').on('click', '.db-fm-cd', function (e) { e.preventDefault(); load($(this).data('path')); });
	$('#db-fm-rows').on('click', '.db-fm-edit', function (e) { e.preventDefault(); DBOpenEditor($(this).data('path'), $(this).data('name')); });

	/* Rename */
	$('#db-fm-rows').on('click', '.db-fm-rename', function () {
		var path = $(this).data('path'), name = $(this).data('name');
		modal('Rename', '<div class="db-field"><label class="db-label">New name</label><input type="text" class="db-input" id="db-m-rename" value="' + DBEsc(name) + '"></div>', 'Rename', function () {
			DBAjax('files', 'rename', { path: path, new_name: $('#db-m-rename').val() }).done(function (r) {
				if (r.success) { DBToast.show('Renamed', 'success'); closeModal(); load(cwd); }
				else DBToast.show(r.data || 'Failed', 'error');
			});
		});
	});

	/* Chmod */
	var PERM_MODES = [
		['644', 'Standard file — owner read/write, everyone else read-only'],
		['755', 'Folder / executable — owner full, others read & enter/run'],
		['600', 'Private file — owner read/write only (e.g. wp-config.php)'],
		['640', 'Owner read/write, group read-only, others none'],
		['750', 'Owner full, group read & enter, others none'],
		['664', 'Owner & group read/write, others read-only'],
		['666', 'Everyone read/write — avoid (insecure)'],
		['777', 'Everyone read/write/execute — dangerous, avoid']
	];
	$('#db-fm-rows').on('click', '.db-fm-chmod', function () {
		var path = $(this).data('path'), perms = $(this).data('perms');
		var ref = '';
		PERM_MODES.forEach(function (m) {
			ref += '<button type="button" class="db-perm-pick" data-mode="' + m[0] + '">'
				+ '<span class="db-badge db-badge-gray db-mono">' + m[0] + '</span>'
				+ '<span>' + DBEsc(m[1]) + '</span></button>';
		});
		var body = '<div class="db-field"><label class="db-label">Octal mode (e.g. 644, 755)</label>'
			+ '<input type="text" class="db-input mono" id="db-m-chmod" value="' + perms + '"></div>'
			+ '<div class="db-label">Common modes (click to use)</div>'
			+ '<div class="db-perm-ref">' + ref + '</div>'
			+ '<div class="db-muted db-text-xs db-mt-8">Three digits = <strong>owner · group · others</strong>. '
			+ 'Each digit = read (4) + write (2) + execute (1). E.g. 7 = rwx, 6 = rw-, 5 = r-x, 4 = r--.</div>';
		modal('Change Permissions', body, 'Apply', function () {
			DBAjax('files', 'chmod', { path: path, mode: $('#db-m-chmod').val() }).done(function (r) {
				if (r.success) { DBToast.show('Permissions updated', 'success'); closeModal(); load(cwd); }
				else DBToast.show(r.data || 'Failed', 'error');
			});
		});
	});
	/* Fill the input when a reference mode is clicked */
	$('#db-fm-modal-body').on('click', '.db-perm-pick', function () {
		$('#db-m-chmod').val($(this).data('mode')).focus();
	});

	/* Delete single */
	$('#db-fm-rows').on('click', '.db-fm-delete', function () {
		var path = $(this).data('path');
		DBAjax('files', 'delete', { path: path }).done(function (r) {
			if (r.success) { DBToast.show('Deleted', 'success'); load(cwd); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	});

	/* New file / folder */
	$('#db-fm-newfile').on('click', function () {
		modal('New File', '<div class="db-field"><label class="db-label">File name</label><input type="text" class="db-input" id="db-m-nf" placeholder="example.php"></div>', 'Create', function () {
			DBAjax('files', 'create_file', { path: cwd, name: $('#db-m-nf').val() }).done(function (r) {
				if (r.success) { DBToast.show('File created', 'success'); closeModal(); load(cwd); }
				else DBToast.show(r.data || 'Failed', 'error');
			});
		});
	});
	$('#db-fm-newfolder').on('click', function () {
		modal('New Folder', '<div class="db-field"><label class="db-label">Folder name</label><input type="text" class="db-input" id="db-m-nd" placeholder="my-folder"></div>', 'Create', function () {
			DBAjax('files', 'mkdir', { path: cwd, name: $('#db-m-nd').val() }).done(function (r) {
				if (r.success) { DBToast.show('Folder created', 'success'); closeModal(); load(cwd); }
				else DBToast.show(r.data || 'Failed', 'error');
			});
		});
	});

	/* Upload */
	$('#db-fm-upload').on('click', function () { $('#db-fm-file-input').click(); });
	$('#db-fm-file-input').on('change', function () { if (this.files[0]) doUpload(this.files[0]); this.value = ''; });
	function doUpload(file) {
		var fd = new FormData();
		fd.append('action', 'devbench'); fd.append('nonce', DevBench.nonce);
		fd.append('module', 'files'); fd.append('sub_action', 'upload');
		fd.append('path', cwd); fd.append('file', file);
		DBToast.show('Uploading ' + file.name + '…');
		$.ajax({ url: DevBench.ajax_url, type: 'POST', data: fd, processData: false, contentType: false }).done(function (r) {
			if (r.success) { DBToast.show('Uploaded', 'success'); load(cwd); }
			else DBToast.show(r.data || 'Upload failed', 'error');
		});
	}
	/* Drag & drop */
	var $dz = $('#db-fm-dropzone');
	$(document).on('dragover', '.db-main', function (e) { e.preventDefault(); $dz.removeClass('db-hidden').addClass('drag'); });
	$dz.on('dragleave', function () { $dz.addClass('db-hidden').removeClass('drag'); });
	$dz.on('drop', function (e) {
		e.preventDefault(); $dz.addClass('db-hidden').removeClass('drag');
		var f = e.originalEvent.dataTransfer.files[0]; if (f) doUpload(f);
	});

	/* Filter */
	$('#db-fm-search').on('input', function () {
		var q = $(this).val().toLowerCase();
		if (!q) { render(items); return; }
		render(items.filter(function (i) { return i.name.toLowerCase().indexOf(q) !== -1; }));
	});

	/* Bulk select */
	$('#db-fm-selectall').on('change', function () {
		$('.db-fm-check').prop('checked', this.checked); updateBulk();
	});
	$('#db-fm-rows').on('change', '.db-fm-check', updateBulk);
	function updateBulk() {
		var n = $('.db-fm-check:checked').length;
		$('#db-fm-bulkbar').toggleClass('db-hidden', n === 0);
		$('#db-fm-selcount').text(n + ' selected');
		$('#db-fm-bulkdelete').attr('data-confirm', 'Delete ' + n + ' item(s)? This cannot be undone.');
	}
	/* Zip the current selection into the folder being browsed. */
	$('#db-fm-bulkzip').on('click', function () {
		var paths = $('.db-fm-check:checked').map(function () { return this.value; }).get();
		if (!paths.length) return;

		var suggested = 'devbench-archive';
		modal('Create Archive',
			'<div class="db-field"><label class="db-label">Archive name</label>'
			+ '<input type="text" class="db-input" id="db-m-zip" value="' + DBEsc(suggested) + '"></div>'
			+ '<div class="db-muted db-text-xs">' + paths.length + ' item(s) will be zipped into this folder. '
			+ 'Folders are included with their contents.</div>',
			'Create', function () {
				var $ok = $('#db-fm-modal-ok').prop('disabled', true).text('Zipping…');
				DBAjax('files', 'zip', { paths: paths, path: cwd, name: $('#db-m-zip').val() }).done(function (r) {
					if (r.success) { DBToast.show('Created ' + r.data.name + ' (' + r.data.size + ')', 'success'); closeModal(); load(cwd); }
					else DBToast.show(r.data || 'Failed', 'error');
				}).fail(function () {
					DBToast.show('Request failed', 'error');
				}).always(function () {
					$ok.prop('disabled', false).text('Create');
				});
			});
	});

	$('#db-fm-bulkdelete').on('click', function () {
		var paths = $('.db-fm-check:checked').map(function () { return this.value; }).get();
		if (!paths.length) return;
		DBAjax('files', 'bulk_delete', { paths: paths }).done(function (r) {
			if (r.success) { DBToast.show('Deleted ' + r.data.deleted + ' item(s)' + (r.data.errors.length ? ', ' + r.data.errors.length + ' failed' : ''), 'success'); load(cwd); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	});

	load('/wp-content');
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
