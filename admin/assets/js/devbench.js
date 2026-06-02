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
	function esc(s) {
		return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}
	window.DBEsc = esc;

	function humanSize(b) {
		if (!b) return '0 B';
		const u = ['B', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(b) / Math.log(1024));
		return parseFloat((b / Math.pow(1024, i)).toFixed(1)) + ' ' + u[i];
	}
	window.DBHumanSize = humanSize;

	function fileIcon(ext) {
		const m = {
			php:'\u{1F418}', js:'\u{1F4DC}', mjs:'\u{1F4DC}', ts:'\u{1F4DC}', jsx:'\u{1F4DC}', tsx:'\u{1F4DC}',
			css:'\u{1F3A8}', scss:'\u{1F3A8}', sass:'\u{1F3A8}', less:'\u{1F3A8}',
			html:'\u{1F310}', htm:'\u{1F310}', twig:'\u{1F310}',
			json:'\u{1F4CB}', yml:'\u{1F4CB}', yaml:'\u{1F4CB}', xml:'\u{1F4CB}', toml:'\u{1F4CB}',
			md:'\u{1F4DD}', txt:'\u{1F4C4}', csv:'\u{1F4CA}', log:'\u{1FAB5}',
			sql:'\u{1F5C4}', sqlite:'\u{1F5C4}',
			svg:'\u{1F537}', png:'\u{1F5BC}', jpg:'\u{1F5BC}', jpeg:'\u{1F5BC}', gif:'\u{1F5BC}', webp:'\u{1F5BC}', ico:'\u{1F5BC}',
			woff:'\u{1F524}', woff2:'\u{1F524}', ttf:'\u{1F524}', otf:'\u{1F524}',
			zip:'\u{1F5DC}', gz:'\u{1F5DC}', tar:'\u{1F5DC}',
			pdf:'\u{1F4D5}', sh:'\u2699\uFE0F', py:'\u{1F40D}', env:'\u{1F511}', lock:'\u{1F512}'
		};
		return m[ext] || '\u{1F4C4}';
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
				if (oldL[i] !== undefined) h += '<div style="background:#fef2f2;color:#dc2626;padding:1px 8px"><span style="opacity:.5">' + (i + 1) + '</span> - ' + esc(oldL[i]) + '</div>';
				if (newL[i] !== undefined) h += '<div style="background:#f0fdf4;color:#16a34a;padding:1px 8px"><span style="opacity:.5">' + (i + 1) + '</span> + ' + esc(newL[i]) + '</div>';
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
		$('#db-editor-icon').text(fileIcon(ext));
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
			if (confirm('Restore this version?')) { VC.restore($(this).data('id')); VC.render(); }
		}).on('click.vc', '.vc-diff', function () { VC.diff($(this).data('id')); });
	}
	window.DBCloseEditor = closeEditor;

	/* ============================================================
	   PAGE ROUTER
	   ============================================================ */
	$(function () {
		const page = $('.devbench-app').data('page');
		if (window.DBPages && typeof window.DBPages[page] === 'function') {
			window.DBPages[page]();
		}
	});

	window.DBPages = {};

})(jQuery);
