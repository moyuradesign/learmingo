<link rel="stylesheet" href="/assets/css/readiness.css">
<section class="rw" id="readiness">
    <header class="rw-heading">
        <div><p class="rw-eyebrow">LEARMINGO / MILESTONE 03</p><h1>Assessment & certificate readiness</h1><p>Plan the work. Capture evidence. Review every release.</p></div>
        <span class="rw-lock">Production changes disabled</span>
    </header>
    <noscript><p>This interactive workspace requires JavaScript. Enable it to view and manage records.</p></noscript>
    <div class="rw-stats" id="rw-stats"></div>
    <div class="rw-shell">
        <nav class="rw-nav" aria-label="Workspace views">
            <?php foreach ($views as $i => $label): ?>
                <button type="button" data-view="<?= e($label) ?>"<?= $i === 0 ? ' aria-current="page"' : '' ?>><span><?= sprintf('%02d', $i + 1) ?></span><?= e($label) ?></button>
            <?php endforeach; ?>
        </nav>
        <div class="rw-main">
            <div class="rw-heading">
                <div><p class="rw-eyebrow">ACCEPTANCE WORKSPACE</p><h2 id="rw-title">Assessment design</h2></div>
                <button id="rw-new" type="button">+ New item</button>
            </div>
            <p id="rw-guide">Planned → In progress → In review → Verified → Approved</p>
            <div class="rw-toolbar">
                <label>Reusable template<select id="rw-template"></select></label>
                <button type="button" id="rw-delete-template" disabled>Delete template</button>
                <button type="button" id="rw-refresh">Reload records</button>
                <a class="rw-button" href="/admin/readiness/export">Export workspace</a>
            </div>
            <div class="rw-filters">
                <label>Search<input id="rw-search" type="search" placeholder="Title, assessment, evidence…"></label>
                <label>Status<select id="rw-status"><option value="">All statuses</option><?php foreach (\Learmingo\Services\ReadinessWorkspace::STATES as $state): ?><option><?= e($state) ?></option><?php endforeach; ?></select></label>
                <label>Environment<select id="rw-env"><option value="">All environments</option><option>local</option><option>staging</option><option>production</option></select></label>
                <label>Course<input id="rw-course" list="rw-courses" placeholder="Filter course"></label>
                <label>Version<input id="rw-version" placeholder="Filter version"></label>
                <label>Reviewer<input id="rw-reviewer" list="rw-reviewers" placeholder="Filter reviewer"></label>
                <label>Sort<select id="rw-sort"><option value="updated">Recently updated</option><option value="title">Title</option><option value="status">Workflow order</option></select></label>
                <button type="button" id="rw-clear">Clear filters</button>
            </div>
            <p id="rw-message" role="status" aria-live="polite"></p>
            <section id="rw-release" hidden aria-label="Release coverage"></section>
            <div id="rw-list"></div>
            <div class="rw-toolbar" id="rw-pagination"></div>
            <details class="rw-compare">
                <summary>Compare course revisions</summary>
                <p>Compare saved course records, including their structured content and publication decisions.</p>
                <div class="rw-filters">
                    <label>Earlier revision<select id="rw-left"></select></label>
                    <label>Later revision<select id="rw-right"></select></label>
                    <button type="button" id="rw-compare">Compare</button>
                    <label class="rw-checkbox"><input id="rw-changes-only" type="checkbox" checked> Only changed fields</label>
                </div>
                <div id="rw-diff" class="rw-table-wrap" aria-live="polite"></div>
            </details>
        </div>
    </div>
    <datalist id="rw-courses"></datalist><datalist id="rw-reviewers"></datalist>
    <dialog id="rw-dialog" aria-labelledby="rw-editor-title">
        <form id="rw-form">
            <div class="rw-heading"><h2 id="rw-editor-title">Readiness record</h2><button type="button" id="rw-close">Close</button></div>
            <?= csrf_field() ?>
            <input type="hidden" name="id"><input type="hidden" name="revision"><input type="hidden" name="view">
            <p id="rw-next-step" class="rw-notice"></p>
            <div class="rw-fields">
                <?php foreach (['title'=>'Title','course'=>'Course','assessment'=>'Assessment / application / template','version'=>'Version','reviewer'=>'Assigned reviewer'] as $key => $label): ?>
                    <label><?= e($label) ?><input name="<?= $key ?>" maxlength="500"<?= in_array($key, ['title','course','version']) ? ' required' : '' ?><?= $key === 'course' ? ' list="rw-courses"' : '' ?><?= $key === 'reviewer' ? ' list="rw-reviewers"' : '' ?>></label>
                <?php endforeach; ?>
                <label>Environment<select name="environment"><option>staging</option><option>local</option><option>production</option></select></label>
                <label>Status<select name="status"></select></label>
                <label>Observed score (%)<input type="number" name="score" min="0" max="100" step="any"></label>
                <label class="rw-wide">Grading / acceptance rule<textarea name="rule" rows="2" maxlength="12000"></textarea></label>
            </div>
            <fieldset class="rw-section"><legend id="rw-structured-title">Structured details</legend><div id="rw-structured" class="rw-fields"></div></fieldset>
            <div class="rw-fields">
                <?php foreach (['details'=>'Additional design notes','evidence'=>'Evidence links (one HTTP or HTTPS URL per line)','defects'=>'Open defect notes (enter “none” only when resolved)','rollback'=>'Rollback notes'] as $key => $label): ?>
                    <label class="rw-wide"><?= e($label) ?><textarea name="<?= $key ?>" rows="3" maxlength="12000"></textarea></label>
                <?php endforeach; ?>
                <label class="rw-wide">Publication decision<select name="decision"><option value="hold">Hold</option><option value="changes required">Changes required</option><option value="acceptance complete">Acceptance complete — production remains disabled</option></select></label>
            </div>
            <div id="rw-evidence-links"></div>
            <p id="rw-error" role="alert"></p>
            <div class="rw-toolbar"><button type="submit" id="rw-save">Save record</button><button type="button" id="rw-advance">Move to next step</button><button type="button" id="rw-reopen">Return to planned</button></div>
            <details class="rw-section"><summary>Save reusable template</summary><p>Save design fields for future items. Evidence, scores and approvals are excluded.</p><label>Template name<input id="rw-template-name" maxlength="500"></label><div class="rw-toolbar"><button type="button" id="rw-save-template">Save as new template</button><button type="button" id="rw-update-template">Update selected template</button></div></details>
            <h3>Approval & change history</h3><p>Restoring a content snapshot creates a planned revision requiring fresh acceptance.</p><div id="rw-history"></div>
        </form>
    </dialog>
</section>
<script type="application/json" id="rw-data"><?= json_encode(['items'=>$items, 'templates'=>$templates, 'schema'=>$schema], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="/assets/js/readiness.js" defer></script>
