(() => {
    'use strict';

    const $ = selector => document.querySelector(selector);
    const boot = JSON.parse($('#rw-data').textContent);
    const schema = boot.schema;
    const views = Object.keys(schema);
    const states = ['planned', 'in progress', 'in review', 'verified', 'approved'];
    const form = $('#rw-form');
    const dialog = $('#rw-dialog');
    let items = boot.items, templates = boot.templates;
    let view = views[0], current = null, page = 1, dirty = false, busy = false;
    const pageSize = 12;
    const designKeys = ['title', 'course', 'assessment', 'version', 'environment', 'rule', 'reviewer', 'details'];
    const field = name => form.elements.namedItem(name);
    const node = (tag, text, className) => {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    const button = (label, handler) => {
        const element = node('button', label);
        element.type = 'button';
        element.addEventListener('click', handler);
        return element;
    };
    const date = value => value ? new Date(value).toLocaleString() : 'Earlier record';
    const notice = text => { $('#rw-message').textContent = text; };
    const selectedTemplate = () => templates.find(item => item.id === $('#rw-template').value);

    function options(select, entries, emptyLabel) {
        const previous = select.value;
        select.replaceChildren();
        if (emptyLabel) {
            const option = node('option', emptyLabel);
            option.value = '';
            select.append(option);
        }
        for (const entry of entries) {
            const option = node('option', entry.label);
            option.value = entry.value;
            select.append(option);
        }
        if ([...select.options].some(option => option.value === previous)) select.value = previous;
    }

    function releaseSummary(course, version, environment) {
        return views.slice(0, -1).map(section => {
            const records = items.filter(item => item.view === section && item.course === course
                && item.version === version && item.environment === environment);
            const approved = records.filter(item => item.status === 'approved' && item.decision === 'acceptance complete'
                && ['local', 'staging'].includes(item.environment) && item.defects === 'none'
                && ['rule', 'reviewer', 'evidence', 'rollback'].every(key => String(item[key] || '').trim() !== '')
                && Object.entries(schema[item.view]).every(([key, definition]) => !definition.required || String(item.fields?.[key] ?? '').trim() !== '')).length;
            return { section, total: records.length, approved };
        });
    }

    function renderCoverage() {
        const target = $('#rw-release');
        target.hidden = view !== 'Release readiness';
        target.replaceChildren();
        if (target.hidden) return;
        target.append(node('h3', 'Release coverage'), node('p', 'Each course/version/environment requires approved records in all six workflow areas. Missing work remains blocked. Production publishing stays disabled.'));
        const scopes = new Map();
        for (const item of items) {
            if ($('#rw-course').value && !item.course.toLowerCase().includes($('#rw-course').value.toLowerCase())) continue;
            if ($('#rw-version').value && !item.version.toLowerCase().includes($('#rw-version').value.toLowerCase())) continue;
            if ($('#rw-env').value && item.environment !== $('#rw-env').value) continue;
            scopes.set(JSON.stringify([item.course, item.version, item.environment]), item);
        }
        if (!scopes.size) target.append(node('p', 'No matching course scope has been recorded.'));
        for (const scope of scopes.values()) {
            const details = node('details', undefined, 'rw-section');
            const groups = releaseSummary(scope.course, scope.version, scope.environment);
            const ready = groups.every(group => group.total > 0 && group.approved === group.total) && scope.environment !== 'production';
            details.append(node('summary', `${scope.course} · ${scope.version} · ${scope.environment} — ${ready ? 'workflow coverage complete' : 'blocked'}`));
            for (const group of groups) details.append(node('p', `${group.section}: ${group.approved}/${group.total} approved${group.total ? '' : ' — missing'}`));
            details.append(node('p', 'Installation, permission, backup and device acceptance are recorded separately in the release record.'));
            target.append(details);
        }
    }

    function visibleItems() {
        const q = $('#rw-search').value.toLowerCase();
        const status = $('#rw-status').value, environment = $('#rw-env').value;
        const matches = items.filter(item => item.view === view
            && (!status || item.status === status) && (!environment || item.environment === environment)
            && ['course', 'version', 'reviewer'].every(key => (item[key] || '').toLowerCase().includes($(`#rw-${key}`).value.toLowerCase()))
            && JSON.stringify({ ...item, snapshots: undefined, history: undefined }).toLowerCase().includes(q));
        matches.sort((a, b) => {
            if ($('#rw-sort').value === 'title') return a.title.localeCompare(b.title);
            if ($('#rw-sort').value === 'status') return states.indexOf(a.status) - states.indexOf(b.status) || a.title.localeCompare(b.title);
            return (b.updated_at || '').localeCompare(a.updated_at || '') || a.title.localeCompare(b.title);
        });
        return matches;
    }

    function evidenceLinks(value, target) {
        target.replaceChildren();
        for (const raw of (value || '').split(/\r?\n/)) {
            try {
                const url = new URL(raw.trim());
                if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) continue;
                const a = node('a', url.href);
                a.href = url.href; a.target = '_blank'; a.rel = 'noopener noreferrer';
                target.append(a);
            } catch { /* Incomplete inputs are validated by the server on save. */ }
        }
    }

    function render() {
        const stats = $('#rw-stats');
        stats.replaceChildren();
        for (const [label, value] of [
            ['Tracked items', items.length], ['In review', items.filter(item => item.status === 'in review').length],
            ['Verified / approved', items.filter(item => ['verified', 'approved'].includes(item.status)).length], ['Production publishing', 'Disabled'],
        ]) {
            const card = node('div'); card.append(node('strong', String(value)), node('span', label)); stats.append(card);
        }
        options($('#rw-template'), templates.filter(item => item.view === view).map(item => ({ value: item.id, label: item.name })), 'Built-in blank template');
        $('#rw-delete-template').disabled = !selectedTemplate();
        for (const [id, key] of [['#rw-courses', 'course'], ['#rw-reviewers', 'reviewer']]) {
            $(id).replaceChildren(...[...new Set(items.map(item => item[key]).filter(Boolean))].sort().map(value => {
                const option = node('option'); option.value = value; return option;
            }));
        }
        const visible = visibleItems();
        const pages = Math.max(1, Math.ceil(visible.length / pageSize));
        page = Math.min(page, pages);
        const list = $('#rw-list'); list.replaceChildren();
        if (!visible.length) {
            const empty = node('div', undefined, 'rw-empty');
            empty.append(node('h3', 'No records to show'), node('p', items.some(item => item.view === view)
                ? 'Adjust the filters to see more records.' : 'Start with a built-in or saved template. Acceptance remains pending until evidence is reviewed.'));
            list.append(empty);
        }
        for (const item of visible.slice((page - 1) * pageSize, page * pageSize)) {
            const card = node('article', undefined, 'rw-card');
            const heading = node('div', undefined, 'rw-heading');
            heading.append(node('h3', item.title), node('span', item.status, 'rw-pill'));
            card.append(heading, node('p', `${item.course} · ${item.version} · ${item.environment}`),
                node('p', `Reviewer: ${item.reviewer || 'Unassigned'} · Decision: ${item.decision}`));
            if (item.score !== undefined && item.score !== '') card.append(node('p', `Observed score: ${item.score}%`));
            const steps = node('div', undefined, 'rw-steps');
            states.forEach((status, index) => steps.append(node('span', `${index + 1} ${status}`, index <= states.indexOf(item.status) ? 'done' : '')));
            card.append(steps);
            if (item.defects && item.defects !== 'none') card.append(node('p', `Open defects: ${item.defects}`, 'rw-warning'));
            const links = node('div', undefined, 'rw-links'); evidenceLinks(item.evidence, links); card.append(links);
            const actions = node('div', undefined, 'rw-toolbar');
            actions.append(button('Review record', () => edit(item)), button('Copy to new planned item', () => edit(item, true)));
            card.append(actions, node('small', `Updated ${date(item.updated_at)} · revision ${item.revision}`));
            list.append(card);
        }
        const pagination = $('#rw-pagination');
        pagination.replaceChildren();
        const previous = button('Previous', () => { page--; render(); }); previous.disabled = page <= 1;
        const next = button('Next', () => { page++; render(); }); next.disabled = page >= pages;
        pagination.append(previous, node('span', `${visible.length} records · Page ${page} of ${pages}`), next);
        for (const id of ['#rw-left', '#rw-right']) {
            options($(id), items.filter(item => item.view === 'Course revision history').map(item => ({
                value: item.id, label: `${item.course} / ${item.version} / ${item.environment} / ${item.title}`,
            })), 'Select course revision');
        }
        $('#rw-diff').replaceChildren();
        renderCoverage();
    }

    function structuredFields(values = {}) {
        const target = $('#rw-structured'); target.replaceChildren();
        const definitions = schema[field('view').value];
        for (const [key, definition] of Object.entries(definitions)) {
            const label = node('label', definition.label + (definition.required ? ' (required for review)' : ''));
            const input = node(definition.type === 'textarea' ? 'textarea' : definition.type === 'select' ? 'select' : 'input');
            input.name = `fields[${key}]`;
            if (definition.type === 'select') {
                options(input, definition.options.map(value => ({ value, label: value })), 'Not specified');
            } else if (definition.type === 'textarea') {
                input.rows = 3; input.maxLength = 12000; label.className = 'rw-wide';
            } else {
                input.type = definition.type;
                if (definition.type === 'number') { input.min = definition.min; input.max = definition.max; input.step = '1'; }
                else input.maxLength = 12000;
            }
            input.value = values[key] ?? '';
            label.append(input); target.append(label);
        }
    }

    function lockControl(control, locked) {
        if (control.tagName === 'SELECT') control.disabled = locked;
        else control.readOnly = locked;
    }

    function updateGuidance() {
        const index = current ? states.indexOf(current.status) : 0;
        const reopening = field('status').value === 'planned';
        const locked = index >= 2 && !reopening;
        designKeys.forEach(key => lockControl(field(key), locked));
        for (const [key, definition] of Object.entries(schema[field('view').value])) {
            lockControl(field(`fields[${key}]`), locked && !(index === 2 && definition.review));
        }
        ['evidence', 'defects', 'rollback', 'score'].forEach(key => lockControl(field(key), index >= 3 && !reopening));
        if (reopening) field('decision').value = 'hold';
        $('#rw-reopen').hidden = !current || current.status === 'planned';
        $('#rw-advance').hidden = !current || current.status === 'approved' || (current.status !== 'planned' && reopening);
        $('#rw-advance').textContent = `Move to ${states[index + 1] || 'approved'}`;
        $('#rw-next-step').textContent = !current ? 'Start as planned. Save the design before progressing.' : reopening && index > 0
            ? 'Saving as planned resets the publication decision and starts a fresh review cycle.'
            : ({ planned: 'Next: start implementation and complete the design fields.', 'in progress': 'Next: assign a reviewer, complete required fields and define the grading or acceptance rule.',
                'in review': 'Add evidence, record results and rollback notes, and resolve defects. Design changes require returning to planned.',
                verified: 'Evidence is locked. Set the publication decision to acceptance complete when approving.',
                approved: 'Acceptance is recorded. Production publishing remains disabled. Reopen before changing approved content.' })[current.status];
    }

    function snapshotRows(snapshot) {
        const rows = {};
        for (const key of ['title','course','assessment','version','status','environment','rule','reviewer','score','details','evidence','defects','rollback','decision']) rows[key] = String(snapshot[key] ?? '');
        for (const [key, definition] of Object.entries(schema[snapshot.view] || {})) rows[definition.label] = snapshot.fields?.[key] ?? '';
        if (snapshot.view === 'Release readiness') rows['Accepted dependency revisions'] = Object.entries(snapshot.dependencies || {}).map(([id, revision]) => `${id}: revision ${revision}`).join('\n');
        return rows;
    }

    function comparison(a, b, onlyChanges) {
        const table = node('table');
        const head = node('thead'), tr = node('tr');
        ['Field', 'Earlier', 'Later / current'].forEach(text => { const th = node('th', text); th.scope = 'col'; tr.append(th); });
        head.append(tr); table.append(head);
        const body = node('tbody'), left = snapshotRows(a), right = snapshotRows(b);
        for (const key of new Set([...Object.keys(left), ...Object.keys(right)])) {
            const changed = (left[key] || '') !== (right[key] || '');
            if (onlyChanges && !changed) continue;
            const row = node('tr', undefined, changed ? 'rw-changed' : '');
            const th = node('th', key); th.scope = 'row';
            row.append(th, node('td', left[key] || '—'), node('td', right[key] || '—')); body.append(row);
        }
        if (!body.children.length) { const row = node('tr'), td = node('td', 'No changed fields.'); td.colSpan = 3; row.append(td); body.append(row); }
        table.append(body); return table;
    }

    function history(item) {
        const target = $('#rw-history'); target.replaceChildren();
        if (!item) { target.append(node('p', 'History begins when the item is saved.')); return; }
        for (const event of [...item.history].reverse()) {
            const details = node('details', undefined, 'rw-section');
            details.append(node('summary', `${date(event.at)} · User #${event.actor} · ${event.from || 'new'} → ${event.to} · revision ${event.revision}`));
            details.append(node('p', `${event.event || 'save'} · Decision: ${event.decision}${event.restored_revision ? ` · Restored from revision ${event.restored_revision}` : ''}`));
            const snapshot = item.snapshots?.[event.revision];
            if (snapshot) {
                const wrap = node('div', undefined, 'rw-table-wrap'); wrap.append(comparison(snapshot, item, false)); details.append(wrap);
                if (event.revision !== item.revision) details.append(button('Restore content as planned', async () => {
                    if (!window.confirm('Restore this snapshot as a new planned revision? Fresh evidence and acceptance will be required.')) return;
                    const data = formData(); data.set('action', 'restore'); data.set('snapshot_revision', String(event.revision));
                    const result = await mutate(data);
                    if (result) { edit(items.find(record => record.id === item.id)); notice('Snapshot restored as planned. Review and acceptance are required again.'); }
                }));
            } else details.append(node('p', 'Full content was not captured for this earlier revision.'));
            target.append(details);
        }
    }

    function edit(item = null, copy = false) {
        current = copy ? null : item;
        form.reset(); $('#rw-error').textContent = '';
        const template = selectedTemplate();
        let data = item ? structuredClone(item) : {
            title: '', course: '', assessment: '', version: 'v1', environment: 'staging', status: 'planned', decision: 'hold',
            rule: template?.rule || '', details: template?.details || '', fields: template?.fields || {},
        };
        if (copy) {
            data.fields = data.fields || {};
            data = { ...data, id: '', revision: '', title: `${item.title} (copy)`, status: 'planned', decision: 'hold', evidence: '', score: '', defects: '', rollback: '', reviewer: '' };
            for (const [key, definition] of Object.entries(schema[data.view])) if (definition.review) data.fields[key] = definition.options?.includes('pending') ? 'pending' : '';
        }
        field('view').value = data.view || view;
        options(field('status'), (current ? states.filter((state, index) => state === 'planned' || state === current.status || index === states.indexOf(current.status) + 1) : ['planned']).map(value => ({ value, label: value })));
        for (const [key, value] of Object.entries(data)) {
            const control = field(key);
            if (control && typeof value !== 'object') control.value = value;
        }
        field('id').value = current?.id || '';
        field('revision').value = current?.revision || '';
        $('#rw-editor-title').textContent = copy ? 'New planned copy' : current ? current.title : 'New readiness record';
        $('#rw-structured-title').textContent = field('view').value;
        structuredFields(data.fields);
        $('#rw-template-name').value = template?.name || '';
        $('#rw-update-template').disabled = !template || template.view !== field('view').value;
        evidenceLinks(data.evidence, $('#rw-evidence-links'));
        history(current); updateGuidance(); dirty = false;
        if (!dialog.open) dialog.showModal();
    }

    function formData() {
        const data = new FormData(form);
        // Reviewed selects are disabled in the editor but retain their canonical values on save.
        for (const control of form.elements) if (control.name && control.disabled) data.set(control.name, control.value);
        return data;
    }

    async function parseResponse(response) {
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Session expired or server unavailable. Reload and sign in.');
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'The request could not be completed.');
        return result;
    }

    async function mutate(data) {
        if (busy) return null;
        busy = true;
        form.inert = true;
        document.querySelectorAll('#rw-form button').forEach(control => { control.disabled = true; });
        $('#rw-error').textContent = '';
        try {
            const result = await parseResponse(await fetch('/admin/readiness', { method: 'POST', body: data, headers: { Accept: 'application/json' } }));
            if (result.items) items = result.items;
            if (result.templates) templates = result.templates;
            render(); return result;
        } catch (error) {
            if (dialog.open) $('#rw-error').textContent = error.message;
            else notice(error.message);
            return null;
        } finally {
            busy = false;
            form.inert = false;
            document.querySelectorAll('#rw-form button').forEach(control => { control.disabled = false; });
            $('#rw-update-template').disabled = !selectedTemplate() || selectedTemplate().view !== field('view').value;
        }
    }

    async function save() {
        if (!form.reportValidity()) return;
        const data = formData(); data.set('action', 'save');
        if (await mutate(data)) {
            dirty = false; dialog.close(); notice('Record saved. Release coverage has been recalculated. Production publishing remains disabled.');
        }
    }

    async function saveTemplate(update) {
        const template = selectedTemplate();
        if (update && !template) return;
        const data = formData();
        data.set('action', 'save-template'); data.set('template_name', $('#rw-template-name').value);
        if (update) { data.set('template_id', template.id); data.set('template_revision', String(template.revision)); }
        if (await mutate(data)) $('#rw-error').textContent = 'Template saved. The readiness record is unchanged until you save it separately.';
    }

    document.querySelectorAll('[data-view]').forEach(control => control.addEventListener('click', () => {
        view = control.dataset.view; page = 1; $('#rw-title').textContent = view;
        document.querySelectorAll('[data-view]').forEach(item => item.removeAttribute('aria-current'));
        control.setAttribute('aria-current', 'page'); render();
    }));
    for (const id of ['#rw-search','#rw-status','#rw-env','#rw-course','#rw-version','#rw-reviewer','#rw-sort']) $(id).addEventListener('input', () => { page = 1; render(); });
    $('#rw-clear').onclick = () => { for (const id of ['#rw-search','#rw-status','#rw-env','#rw-course','#rw-version','#rw-reviewer']) $(id).value = ''; page = 1; render(); };
    $('#rw-template').onchange = () => { $('#rw-delete-template').disabled = !selectedTemplate(); };
    $('#rw-new').onclick = () => edit();
    function closeEditor() {
        if (!busy && (!dirty || window.confirm('Close without saving these edits?'))) { dirty = false; dialog.close(); }
    }
    $('#rw-close').onclick = closeEditor;
    dialog.addEventListener('cancel', event => { event.preventDefault(); closeEditor(); });
    form.addEventListener('input', () => { dirty = true; });
    field('status').onchange = () => { dirty = true; updateGuidance(); };
    field('evidence').oninput = () => evidenceLinks(field('evidence').value, $('#rw-evidence-links'));
    form.onsubmit = event => { event.preventDefault(); save(); };
    $('#rw-advance').onclick = () => {
        if (!current) return;
        const next = states[states.indexOf(current.status) + 1];
        if (!next) return;
        field('status').value = next;
        if (next === 'approved') field('decision').value = 'acceptance complete';
        dirty = true; updateGuidance(); save();
    };
    $('#rw-reopen').onclick = () => { field('status').value = 'planned'; dirty = true; updateGuidance(); };
    $('#rw-save-template').onclick = () => saveTemplate(false);
    $('#rw-update-template').onclick = () => saveTemplate(true);
    $('#rw-delete-template').onclick = async () => {
        const template = selectedTemplate();
        if (!template || !window.confirm(`Delete template “${template.name}”? Existing records remain available.`)) return;
        const data = new FormData();
        data.set('_token', field('_token').value); data.set('action', 'delete-template');
        data.set('template_id', template.id); data.set('template_revision', String(template.revision));
        if (await mutate(data)) notice('Reusable template deleted.');
    };
    $('#rw-refresh').onclick = async () => {
        try {
            const result = await parseResponse(await fetch('/admin/readiness/data', { headers: { Accept: 'application/json' }, cache: 'no-store' }));
            items = result.items; templates = result.templates; render(); notice('Latest records loaded.');
        } catch (error) { notice(error.message); }
    };
    function compareCourses() {
        const left = items.find(item => item.id === $('#rw-left').value), right = items.find(item => item.id === $('#rw-right').value);
        const target = $('#rw-diff'); target.replaceChildren();
        if (!left || !right || left.id === right.id || left.course !== right.course) {
            target.textContent = 'Select two different revision records for the same course.'; return;
        }
        target.append(comparison(left, right, $('#rw-changes-only').checked));
    }
    $('#rw-compare').onclick = compareCourses;
    $('#rw-changes-only').onchange = compareCourses;
    window.addEventListener('beforeunload', event => { if (dirty && dialog.open) { event.preventDefault(); event.returnValue = ''; } });
    render();
})();
