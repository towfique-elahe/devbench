<?php defined( 'ABSPATH' ) || exit; ?>

<!-- Code Editor Modal -->
<div class="db-editor-overlay" id="db-editor-overlay">
	<div class="db-editor">
		<div class="db-editor-head">
			<div class="db-editor-title">
				<span id="db-editor-icon"><?php DevBench_Helpers::the_icon( 'code', 15 ); ?></span>
				<span class="db-editor-filename" id="db-editor-filename">untitled</span>
				<span class="db-badge db-badge-gray db-hidden" id="db-editor-vbadge"></span>
				<span class="db-muted" id="db-editor-perms" style="font-size:11px;font-family:var(--db-mono)"></span>
			</div>
			<div class="db-flex db-gap-8">
				<span class="db-muted db-hidden" id="db-editor-modified" style="font-size:12px;color:var(--db-amber)">● Unsaved</span>
				<button class="db-btn db-btn-ghost db-btn-sm" id="db-editor-findbtn">Find</button>
				<button class="db-btn db-btn-ghost db-btn-sm" id="db-editor-goto">Go to line</button>
				<button class="db-btn db-btn-ghost db-btn-sm" id="db-editor-history">History</button>
				<button class="db-btn db-btn-success db-btn-sm" id="db-editor-save">Save</button>
				<button class="db-btn db-btn-ghost db-btn-sm db-btn-icon" id="db-editor-close">✕</button>
			</div>
		</div>
		<div class="db-editor-find db-hidden" id="db-editor-find">
			<input type="text" class="db-input mono" id="db-find-input" placeholder="Find in file…" style="width:240px;height:30px">
			<span class="db-muted" id="db-find-count" style="font-size:12px;min-width:80px"></span>
			<button class="db-btn db-btn-ghost db-btn-xs" id="db-find-prev">↑ Prev</button>
			<button class="db-btn db-btn-ghost db-btn-xs" id="db-find-next">↓ Next</button>
			<button class="db-btn db-btn-ghost db-btn-xs" id="db-find-close">✕</button>
		</div>
		<div class="db-editor-body">
			<div class="db-editor-lines" id="db-editor-lines"></div>
			<textarea class="db-editor-textarea" id="db-editor-ta" spellcheck="false" disabled></textarea>
		</div>
		<div class="db-editor-foot">
			<span id="db-editor-cursor">Ln 1, Col 1</span>
			<span>Tab = 4 spaces &nbsp;·&nbsp; <kbd>Ctrl</kbd>+<kbd>S</kbd> save &nbsp;·&nbsp; <kbd>Ctrl</kbd>+<kbd>F</kbd> find &nbsp;·&nbsp; <kbd>Esc</kbd> close</span>
		</div>
	</div>
</div>

<!-- Version History Panel -->
<div class="db-version-panel" id="db-version-panel">
	<div class="db-flex-between" style="padding:14px 16px;border-bottom:1px solid var(--db-border);background:var(--db-surface-2)">
		<strong style="font-size:14px">Version History</strong>
		<button class="db-btn db-btn-ghost db-btn-sm db-btn-icon" id="db-version-close">✕</button>
	</div>
	<div class="db-muted" style="padding:10px 16px;font-size:11.5px;border-bottom:1px solid var(--db-border)">
		Session snapshots — cleared when you close the editor.
	</div>
	<div id="db-version-list" style="flex:1;overflow-y:auto;padding:6px"></div>
</div>
