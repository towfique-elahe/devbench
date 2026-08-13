/* ============================================================
   DevBench — Admin JS
   ============================================================ */
(function ($) {
	'use strict';

	/* ---------------- Toast ---------------- */
	const Toast = {
		show(msg, type) {
			const $t = $('<div class="db-toast"></div>').addClass(type || '').text(msg);
			$('#db-toasts').append($t);
			setTimeout(() => $t.fadeOut(200, () => $t.remove()), 3200);
		}
	};
	window.DBToast = Toast;

	/* ---------------- AJAX helper ---------------- */
	function ajax(module, sub_action, data) {
		return $.post(DevBench.ajax_url, $.extend({
			action: 'devbench',
			nonce: DevBench.nonce,
			module: module,
			sub_action: sub_action
		}, data || {}));
	}
	window.DBAjax = ajax;

	/* ---------------- Escape ---------------- */
	/* Quotes are escaped too: this is interpolated into attributes as often as
	   into text, and a filename may legally contain " or '. */
	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}
	window.DBEsc = esc;

	/* ---------------- Clipboard ---------------- */
	function copy(text, emptyMessage) {
		if (!text || text === emptyMessage) { Toast.show('Nothing to copy', 'error'); return; }

		function ok() { Toast.show('Copied to clipboard', 'success'); }
		function fallback() {
			const ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.focus();
			ta.select();
			try { document.execCommand('copy'); ok(); } catch (e) { Toast.show('Copy failed', 'error'); }
			document.body.removeChild(ta);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(ok, fallback);
		} else {
			fallback();
		}
	}
	window.DBCopy = copy;

	function humanSize(b) {
		if (!b) return '0 B';
		const u = ['B', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(b) / Math.log(1024));
		return parseFloat((b / Math.pow(1024, i)).toFixed(1)) + ' ' + u[i];
	}
	window.DBHumanSize = humanSize;

	/* Inline SVG icon set (stroke-based, matches the PHP helper). */
	const ICON_PATHS = {
		code: '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
		'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
		image: '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
		archive: '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
		font: '<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" x2="15" y1="20" y2="20"/><line x1="12" x2="12" y1="4" y2="20"/>',
		database: '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
		folder: '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
		file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
		edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
		pin: '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>',
		lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
		trash: '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
		key: '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
		/* Matches the PHP icon set: 'zap' means "execute" throughout DevBench. */
		zap: '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
		check: '<path d="M20 6 9 17l-5-5"/>',
		power: '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>',
		list: '<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>'
	};

	/*
	 * One icon-only table action.
	 *
	 * These carry no visible text, so the accessible name has to come from
	 * aria-label — title alone is only a tooltip and is not reliably announced.
	 * Centralised so every table gets that treatment by default.
	 */
	function action(tag, cls, iconName, label, attrs, size) {
		size = size || 'xs';                       // 'xs' in table rows, 'sm' in card heads
		return '<' + tag + ' class="db-btn db-btn-' + size + ' db-btn-icon ' + cls + '" ' + (attrs || '')
			+ ' title="' + esc(label) + '" aria-label="' + esc(label) + '">'
			+ icon(iconName, 'sm' === size ? 14 : 13) + '</' + tag + '>';
	}
	window.DBAction = action;

	/* Nonce-signed URL for the File Manager download endpoint. */
	function downloadUrl(path) {
		return DevBench.download_url +
			'&devbench_download=1' +
			'&path=' + encodeURIComponent(path) +
			'&_wpnonce=' + encodeURIComponent(DevBench.download_nonce);
	}
	window.DBDownloadUrl = downloadUrl;

	function icon(name, size) {
		size = size || 15;
		const d = ICON_PATHS[name] || ICON_PATHS.file;
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size +
			'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
			'stroke-linecap="round" stroke-linejoin="round" class="db-icon" ' +
			'aria-hidden="true" focusable="false">' + d + '</svg>';
	}
	window.DBIcon = icon;

	const FILE_ICON_MAP = {
		code: ['php','js','mjs','ts','jsx','tsx','css','scss','sass','less','html','htm','twig','json','yml','yaml','xml','toml','sh','py','sql','sqlite'],
		'file-text': ['txt','md','csv','log','env','lock','ini','conf','pdf'],
		image: ['svg','png','jpg','jpeg','gif','webp','ico','bmp'],
		archive: ['zip','gz','tar','rar','7z'],
		font: ['woff','woff2','ttf','otf']
	};

	function fileIcon(ext, size) {
		ext = String(ext || '').toLowerCase();
		for (const name in FILE_ICON_MAP) {
			if (FILE_ICON_MAP[name].indexOf(ext) !== -1) return icon(name, size);
		}
		return icon('file', size);
	}
	window.DBFileIcon = fileIcon;

	/* ============================================================
	   CODE EDITOR
	   ============================================================ */
	const Editor = {
		$ta: null, $lines: null, ready: false,

		init() {
			if (Editor.ready) return;
			Editor.$ta = $('#db-editor-ta');
			Editor.$lines = $('#db-editor-lines');
			if (!Editor.$ta.length) return;
			Editor.ready = true;

			Editor.$ta.on('input', Editor.refresh);
			Editor.$ta.on('scroll', function () { Editor.$lines.scrollTop(Editor.$ta.scrollTop()); });
			Editor.$ta.on('keydown', Editor.onKey);
			Editor.$ta.on('keyup click', Editor.cursor);
		},

		load(content) {
			Editor.$ta.val(content).prop('disabled', false);
			Editor.refresh();
			Editor.$ta.scrollTop(0);
		},

		refresh() {
			const lines = (Editor.$ta.val().match(/\n/g) || []).length + 1;
			let s = '';
			for (let i = 1; i <= lines; i++) s += i + '\n';
			Editor.$lines.text(s);
			Editor.cursor();
		},

		cursor() {
			const v = Editor.$ta.val().substring(0, Editor.$ta[0].selectionStart);
			const line = (v.match(/\n/g) || []).length + 1;
			const col = v.length - v.lastIndexOf('\n');
			$('#db-editor-cursor').text('Ln ' + line + ', Col ' + col);
		},

		onKey(e) {
			const ta = Editor.$ta[0];
			const s = ta.selectionStart, en = ta.selectionEnd, val = ta.value;

			// Tab / Shift-Tab
			if (e.key === 'Tab') {
				e.preventDefault();
				if (e.shiftKey) {
					const ls = val.lastIndexOf('\n', s - 1) + 1;
					if (val.substring(ls, ls + 4) === '    ') {
						ta.value = val.substring(0, ls) + val.substring(ls + 4);
						ta.selectionStart = ta.selectionEnd = Math.max(ls, s - 4);
					}
				} else {
					ta.value = val.substring(0, s) + '    ' + val.substring(en);
					ta.selectionStart = ta.selectionEnd = s + 4;
				}
				Editor.refresh();
				return;
			}
			// Auto-indent
			if (e.key === 'Enter') {
				const ls = val.lastIndexOf('\n', s - 1) + 1;
				const indent = (val.substring(ls, s).match(/^[\t ]*/) || [''])[0];
				const prevChar = val[s - 1];
				const extra = /[{[(:]/.test(prevChar) ? '    ' : '';
				e.preventDefault();
				const ins = '\n' + indent + extra;
				ta.value = val.substring(0, s) + ins + val.substring(en);
				ta.selectionStart = ta.selectionEnd = s + ins.length;
				Editor.refresh();
				return;
			}
			// Bracket auto-close
			const pairs = { '(': ')', '[': ']', '{': '}' };
			if (pairs[e.key]) {
				e.preventDefault();
				ta.value = val.substring(0, s) + e.key + pairs[e.key] + val.substring(en);
				ta.selectionStart = ta.selectionEnd = s + 1;
				Editor.refresh();
			}
		},

		find(term, dir) {
			if (!term) return;
			const val = Editor.$ta.val();
			const from = dir < 0
				? val.lastIndexOf(term, Editor.$ta[0].selectionStart - term.length - 1)
				: val.indexOf(term, Editor.$ta[0].selectionEnd);
			let idx = from;
			if (idx === -1) idx = dir < 0 ? val.lastIndexOf(term) : val.indexOf(term);
			if (idx === -1) { $('#db-find-count').text('0 results'); return; }
			Editor.$ta[0].focus();
			Editor.$ta[0].setSelectionRange(idx, idx + term.length);
			// Scroll into view
			const before = val.substring(0, idx);
			const line = (before.match(/\n/g) || []).length;
			Editor.$ta.scrollTop(line * 22 - 100);
			Editor.$lines.scrollTop(Editor.$ta.scrollTop());
			const total = val.split(term).length - 1;
			$('#db-find-count').text(total + ' result' + (total !== 1 ? 's' : ''));
		},

		goToLine(n) {
			const val = Editor.$ta.val();
			const lines = val.split('\n');
			let pos = 0;
			for (let i = 0; i < n - 1 && i < lines.length; i++) pos += lines[i].length + 1;
			Editor.$ta[0].focus();
			Editor.$ta[0].setSelectionRange(pos, pos);
			Editor.$ta.scrollTop((n - 1) * 22 - 100);
			Editor.$lines.scrollTop(Editor.$ta.scrollTop());
		},

		reset() {
			if (Editor.$ta) Editor.$ta.val('').prop('disabled', true);
			if (Editor.$lines) Editor.$lines.text('');
		}
	};
	window.DBEditor = Editor;

	/* ============================================================
	   VERSION CONTROL (session-only)
	   ============================================================ */
	const VC = {
		history: [], MAX: 30,

		init(content) {
			VC.history = [];
			VC.snapshot(content, 'original');
			$('#db-editor-modified').hide();
			Editor.$ta.off('input.vc').on('input.vc', () => $('#db-editor-modified').show());
		},
		snapshot(content, label) {
			VC.history.unshift({
				id: Date.now() + Math.random(),
				label: label || 'snapshot',
				time: new Date().toLocaleTimeString(),
				content: content,
				lines: (content.split('\n')).length,
				size: content.length
			});
			if (VC.history.length > VC.MAX) VC.history = VC.history.slice(0, VC.MAX);
			const n = VC.history.length;
			$('#db-editor-vbadge').text(n + ' version' + (n !== 1 ? 's' : '')).show();
			VC.render();
		},
		auto(label) {
			const cur = Editor.$ta ? Editor.$ta.val() : '';
			if (!VC.history[0] || cur !== VC.history[0].content) VC.snapshot(cur, label || 'auto');
		},
		render() {
			const $l = $('#db-version-list');
			if (!$l.length) return;
			if (!VC.history.length) { $l.html('<div class="db-empty"><p>No snapshots yet.</p></div>'); return; }
			let h = '';
			VC.history.forEach((v, i) => {
				const cur = i === 0;
				h += '<div class="db-version-item">'
					+ '<div class="db-flex-between" style="margin-bottom:4px">'
					+ '<strong style="font-size:12.5px">' + esc(v.label) + '</strong>'
					+ (cur ? '<span class="db-badge db-badge-accent">current</span>' : '')
					+ '</div>'
					+ '<div class="db-muted" style="font-size:11px;margin-bottom:6px">' + v.time + ' \u00b7 ' + v.lines + ' lines \u00b7 ' + v.size + 'B</div>'
					+ (!cur ? '<div class="db-flex db-gap-8"><button class="db-btn db-btn-xs vc-restore" data-id="' + v.id + '">Restore</button><button class="db-btn db-btn-xs vc-diff" data-id="' + v.id + '">Diff</button></div>' : '')
					+ '</div>';
			});
			$l.html(h);
		},
		restore(id) {
			const e = VC.history.find(v => v.id == id);
			if (!e) return;
			VC.auto('before restore');
			Editor.load(e.content);
			$('#db-editor-modified').show();
			Toast.show('Restored ' + e.label);
		},
		diff(id) {
			const e = VC.history.find(v => v.id == id);
			if (!e) return;
			const oldL = e.content.split('\n');
			const newL = (Editor.$ta ? Editor.$ta.val() : '').split('\n');
			let h = '', changes = 0;
			const max = Math.max(oldL.length, newL.length);
			for (let i = 0; i < max; i++) {
				if (oldL[i] === newL[i]) continue;
				changes++;
				if (oldL[i] !== undefined) h += '<div style="background:var(--db-red-soft);color:var(--db-red);padding:1px 8px"><span style="opacity:.5">' + (i + 1) + '</span> - ' + esc(oldL[i]) + '</div>';
				if (newL[i] !== undefined) h += '<div style="background:var(--db-green-soft);color:var(--db-green);padding:1px 8px"><span style="opacity:.5">' + (i + 1) + '</span> + ' + esc(newL[i]) + '</div>';
			}
			if (!changes) h = '<div class="db-empty"><p>No differences.</p></div>';
			$('#db-diff-view').remove();
			const $v = $('<div id="db-diff-view" style="margin-bottom:12px;border:1px solid var(--db-border);border-radius:8px;overflow:hidden"></div>');
			const $head = $('<div class="db-flex-between" style="padding:8px 12px;background:var(--db-surface-2);border-bottom:1px solid var(--db-border)"><strong style="font-size:12px">Diff vs ' + esc(e.label) + '</strong></div>');
			$head.append($('<button class="db-btn db-btn-xs">Close</button>').on('click', () => $('#db-diff-view').remove()));
			$v.append($head).append($('<div style="max-height:280px;overflow:auto;font-family:var(--db-mono);font-size:11.5px;line-height:1.7"></div>').html(h));
			$('#db-version-list').prepend($v);
		},
		reset() { VC.history = []; $('#db-editor-vbadge').hide(); if (Editor.$ta) Editor.$ta.off('input.vc'); }
	};
	window.DBVC = VC;

	/* ============================================================
	   SHARED EDITOR LAUNCHER (used by Files + Search)
	   ============================================================ */
	let _editPath = null, _autoTimer = null;

	window.DBOpenEditor = function (path, name, jumpLine) {
		Editor.init();
		_editPath = path;
		const ext = (name || path).split('.').pop().toLowerCase();
		$('#db-editor-filename').text(name || path.split('/').pop());
		$('#db-editor-icon').html(fileIcon(ext));
		Editor.reset();
		VC.reset();
		$('#db-editor-modified').hide();
		$('#db-version-panel').removeClass('open');
		$('#db-editor-overlay').addClass('open');
		$('body').css('overflow', 'hidden');

		ajax('files', 'read', { path: path }).done(r => {
			if (r.success) {
				Editor.load(r.data.content);
				VC.init(r.data.content);
				$('#db-editor-perms').text(r.data.perms + ' \u00b7 ' + r.data.size);
				if (!r.data.writable) Toast.show('File is read-only', 'error');
				if (jumpLine) setTimeout(() => Editor.goToLine(jumpLine), 60);
			} else {
				Editor.load('// ' + (r.data || 'Could not load file'));
				Toast.show(r.data || 'Load failed', 'error');
			}
		}).fail(() => Editor.load('// AJAX request failed'));

		bindEditorControls();
		clearInterval(_autoTimer);
		_autoTimer = setInterval(() => VC.auto('auto'), 120000);
	};

	function doSave() {
		if (!_editPath) return Toast.show('No file open', 'error');
		const content = Editor.$ta.val();
		const $b = $('#db-editor-save').prop('disabled', true).html('Saving\u2026');
		VC.auto('before save');
		ajax('files', 'write', { path: _editPath, content: content }).done(r => {
			if (r.success) { VC.snapshot(content, 'saved'); Toast.show('Saved', 'success'); $('#db-editor-modified').hide(); }
			else Toast.show(r.data || 'Save failed', 'error');
		}).always(() => $b.prop('disabled', false).html('Save'));
	}

	function closeEditor() {
		clearInterval(_autoTimer);
		$('#db-editor-overlay').removeClass('open');
		$('#db-version-panel').removeClass('open');
		$('body').css('overflow', '');
		_editPath = null;
		Editor.reset();
		VC.reset();
	}

	function bindEditorControls() {
		$('#db-editor-save').off('click.ed').on('click.ed', doSave);
		$('#db-editor-close').off('click.ed').on('click.ed', closeEditor);
		$('#db-editor-overlay').off('click.ed').on('click.ed', function (e) {
			if ($(e.target).is('#db-editor-overlay')) closeEditor();
		});
		$('#db-editor-history').off('click.ed').on('click.ed', function () {
			const $p = $('#db-version-panel');
			$p.hasClass('open') ? $p.removeClass('open') : (VC.render(), $p.addClass('open'));
		});
		$('#db-version-close').off('click.ed').on('click.ed', () => $('#db-version-panel').removeClass('open'));
		$('#db-editor-goto').off('click.ed').on('click.ed', function () {
			const n = parseInt(prompt('Go to line:'), 10);
			if (n) Editor.goToLine(n);
		});
		$('#db-editor-findbtn').off('click.ed').on('click.ed', function () {
			$('#db-editor-find').toggleClass('db-hidden');
			if (!$('#db-editor-find').hasClass('db-hidden')) $('#db-find-input').focus();
		});
		$('#db-find-input').off('keydown.ed').on('keydown.ed', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); Editor.find($(this).val(), e.shiftKey ? -1 : 1); }
			if (e.key === 'Escape') $('#db-editor-find').addClass('db-hidden');
		});
		$('#db-find-next').off('click.ed').on('click.ed', () => Editor.find($('#db-find-input').val(), 1));
		$('#db-find-prev').off('click.ed').on('click.ed', () => Editor.find($('#db-find-input').val(), -1));
		$('#db-find-close').off('click.ed').on('click.ed', () => $('#db-editor-find').addClass('db-hidden'));

		$(document).off('keydown.ed').on('keydown.ed', function (e) {
			if (!$('#db-editor-overlay').hasClass('open')) return;
			if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); doSave(); }
			if ((e.ctrlKey || e.metaKey) && e.key === 'f') { e.preventDefault(); $('#db-editor-find').removeClass('db-hidden'); $('#db-find-input').focus(); }
			if (e.key === 'Escape') {
				if ($('#db-version-panel').hasClass('open')) $('#db-version-panel').removeClass('open');
				else if (!$('#db-editor-find').hasClass('db-hidden')) $('#db-editor-find').addClass('db-hidden');
				else closeEditor();
			}
		});
		$(document).off('click.vc').on('click.vc', '.vc-restore', function () {
			if (confirm('Restore this version and save it to the file?')) {
				VC.restore($(this).data('id'));
				VC.render();
				doSave(); // persist the restored content to disk immediately
			}
		}).on('click.vc', '.vc-diff', function () { VC.diff($(this).data('id')); });
	}
	window.DBCloseEditor = closeEditor;

	/* ============================================================
	   THEME TOGGLE (dark default, persisted)
	   ============================================================ */
	$(document).on('click', '#db-theme-toggle', function () {
		const light = document.documentElement.classList.toggle('devbench-theme-light');
		try { localStorage.setItem('devbench-theme', light ? 'light' : 'dark'); } catch (e) {}
	});

	/* ============================================================
	   FILL THE WRAPPER
	   ============================================================ */
	/*
	 * WordPress's own left admin menu is often taller than a DevBench page —
	 * with DevBench's submenu expanded it exceeds the viewport by itself.
	 * #wpwrap then stretches to fit the menu while .devbench-app stays capped
	 * at --db-viewport, so the pinned sidebar bottoms out early and leaves a
	 * band of bare page beneath it. The band is (menu height - app height),
	 * which is why it differs per screen and disappears on the tall dashboard.
	 *
	 * This cannot be solved in CSS: #wpwrap is height:auto with only a
	 * min-height, so a percentage height on our side resolves to auto. So
	 * measure the menu and give the app a matching floor.
	 */
	function fitToWrapper() {
		const app = document.querySelector('.devbench-app');
		if (!app) return;

		const menu = document.getElementById('adminmenuwrap');
		app.style.minHeight = '';                       // release, then measure natural height
		const needed = menu ? menu.offsetHeight : 0;

		if (needed > app.offsetHeight) {
			app.style.minHeight = needed + 'px';
		}
	}
	window.DBFitToWrapper = fitToWrapper;

	let fitPending;
	function scheduleFit() {
		clearTimeout(fitPending);
		fitPending = setTimeout(fitToWrapper, 100);
	}

	$(window).on('resize', scheduleFit);
	// Folding the admin menu changes its height without firing a resize.
	$(document).on('wp-collapse-menu wp-menu-state-set', scheduleFit);

	/* ============================================================
	   DESTRUCTIVE ACTION GUARD
	   ============================================================ */
	/*
	 * Every .db-btn-danger asks before it runs, as does anything carrying a
	 * data-confirm message.
	 *
	 * Bound on document in the CAPTURE phase so it runs ahead of both jQuery's
	 * delegated handlers (which listen on document as the event bubbles) and
	 * any handler bound straight to the element — stopping propagation here
	 * reaches both. Declining therefore cancels the action without each page
	 * needing to remember to ask.
	 *
	 * A danger button that omits data-confirm still gets a generic prompt, so
	 * the guarantee does not depend on anyone remembering to add one.
	 */
	const GENERIC_CONFIRM = 'This cannot be undone. Continue?';

	document.addEventListener('click', function (e) {
		const target = e.target;
		if (!target || typeof target.closest !== 'function') return;

		const el = target.closest('[data-confirm], .db-btn-danger');
		if (!el || el.disabled) return;

		const message = el.getAttribute('data-confirm') || GENERIC_CONFIRM;
		if (!window.confirm(message)) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
		}
	}, true);

	/* ============================================================
	   PAGE ROUTER
	   ============================================================ */
	window.DBPages = window.DBPages || {};

	$(function () {
		const page = $('.devbench-app').data('page');
		if (window.DBPages && typeof window.DBPages[page] === 'function') {
			window.DBPages[page]();
		}
		fitToWrapper();
	});

})(jQuery);
