(() => {
    'use strict';
    const $ = selector => document.querySelector(selector);
    let locks = { reviewed: false, verified: false };
    const changed = () => $('#rw-form').dispatchEvent(new Event('input', { bubbles: true }));
    const node = (tag, text) => {
        const result = document.createElement(tag);
        if (text !== undefined) result.textContent = text;
        return result;
    };
    const control = (labelText, key, value, choices, multiline = false) => {
        const label = node('label', labelText);
        const input = node(choices ? 'select' : multiline ? 'textarea' : 'input');
        input.dataset.key = key;
        if (choices) choices.forEach(choice => { const option = node('option', choice); option.value = choice; input.append(option); });
        else { input.maxLength = key === 'resolution' ? 4000 : key === 'evidence' ? 2000 : 500; if (multiline) input.rows = 3; }
        input.value = value || '';
        if (['title', 'severity', 'status'].includes(key)) input.required = true;
        label.append(input); return label;
    };
    function reindex() {
        for (const [selector, prefix] of [['#rw-checklist', 'checklist'], ['#rw-issues', 'issues']]) {
            [...$(selector).children].forEach((row, index) => {
                row.querySelectorAll('[data-key]').forEach(input => { input.name = `${prefix}[${index}][${input.dataset.key}]`; });
            });
        }
        applyLocks();
    }
    function addTask(task = {}, notify = true) {
        if ($('#rw-checklist').children.length >= 50) return;
        const row = node('div'); row.className = 'rw-task-row';
        const title = control('Task', 'title', task.title);
        const doneLabel = node('label', 'Complete'); doneLabel.className = 'rw-checkbox';
        const done = node('input'); done.type = 'checkbox'; done.dataset.key = 'done'; done.value = '1'; done.checked = !!task.done;
        doneLabel.prepend(done);
        const remove = node('button', 'Remove'); remove.type = 'button'; remove.dataset.removeTask = '';
        remove.onclick = () => { row.remove(); reindex(); changed(); };
        row.append(title, doneLabel, remove); $('#rw-checklist').append(row); reindex(); if (notify) changed();
    }
    function addIssue(issue = {}, notify = true) {
        if ($('#rw-issues').children.length >= 30) return;
        const row = node('div'); row.className = 'rw-issue-row rw-fields'; row.dataset.saved = issue.id ? 'true' : 'false';
        const id = node('input'); id.type = 'hidden'; id.dataset.key = 'id'; id.value = issue.id || ''; row.append(id);
        row.append(control('Defect title', 'title', issue.title), control('Severity', 'severity', issue.severity || 'medium', ['low', 'medium', 'high', 'critical']),
            control('Status', 'status', issue.status || 'open', ['open', 'in progress', 'resolved']), control('Assigned to', 'assignee', issue.assignee),
            control('Resolution notes (required when resolved)', 'resolution', issue.resolution, null, true),
            control('Resolution evidence URL (required when resolved)', 'evidence', issue.evidence));
        const remove = node('button', 'Remove defect'); remove.type = 'button'; remove.dataset.removeIssue = '';
        remove.onclick = () => { row.remove(); reindex(); changed(); }; row.append(remove);
        $('#rw-issues').append(row); reindex(); if (notify) changed();
    }
    function applyLocks() {
        for (const row of $('#rw-checklist').children) {
            row.querySelector('[data-key="title"]').readOnly = locks.reviewed;
            row.querySelector('[data-key="done"]').disabled = locks.verified;
            row.querySelector('[data-remove-task]').disabled = locks.reviewed;
        }
        for (const row of $('#rw-issues').children) {
            const existing = row.dataset.saved === 'true';
            row.querySelectorAll('[data-key]').forEach(input => {
                const locked = locks.verified || (locks.reviewed && existing && ['title', 'severity'].includes(input.dataset.key));
                if (input.tagName === 'SELECT') input.disabled = locked;
                else input.readOnly = locked;
            });
            row.querySelector('[data-remove-issue]').disabled = locks.verified || (locks.reviewed && existing);
        }
        $('#rw-add-task').disabled = locks.reviewed || $('#rw-checklist').children.length >= 50;
        $('#rw-add-issue').disabled = locks.verified || $('#rw-issues').children.length >= 30;
    }
    function localToday() {
        const date = new Date();
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }
    function dueSoon(item) {
        if (!item.due_date || item.status === 'approved') return false;
        const start = localToday();
        const date = new Date(`${start}T12:00:00`); date.setDate(date.getDate() + 7);
        const end = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        return item.due_date >= start && item.due_date <= end;
    }
    const openIssues = item => (item.issues || []).filter(issue => issue.status !== 'resolved');
    const overdue = item => !!item.due_date && item.due_date < localToday() && item.status !== 'approved';
    const complete = item => (item.checklist || []).every(task => task.done) && (item.issues || []).every(issue => issue.status === 'resolved' && issue.resolution && issue.evidence);
    window.LearmingoReadinessPlanning = {
        render(item) {
            locks = { reviewed: false, verified: false };
            $('#rw-checklist').replaceChildren(); $('#rw-issues').replaceChildren();
            (item.checklist || []).forEach(task => addTask(task, false));
            (item.issues || []).forEach(issue => addIssue(issue, false)); applyLocks();
        },
        lock(reviewed, verified) { locks = { reviewed, verified }; applyLocks(); },
        openIssues, overdue, dueSoon, complete,
        copyChecklist: item => (item.checklist || []).map(task => ({ title: task.title, done: false })),
        summary(item) {
            const tasks = item.checklist || [];
            return `${item.work_owner || 'Unassigned'} · ${item.priority || 'normal'} priority${item.due_date ? ` · Due ${item.due_date}${overdue(item) ? ' (overdue)' : ''}` : ''} · ${tasks.filter(task => task.done).length}/${tasks.length} tasks complete · ${openIssues(item).length} open defects`;
        },
        snapshotRows(item) {
            return {
                'Work owner': item.work_owner || '', Priority: item.priority || 'normal', 'Due date': item.due_date || '',
                Checklist: (item.checklist || []).map(task => `${task.done ? '[Complete]' : '[Pending]'} ${task.title}`).join('\n'),
                'Tracked defects': (item.issues || []).map(issue => `${issue.id} · ${issue.title} · ${issue.severity} · ${issue.status}\nAssigned: ${issue.assignee || '—'}\nResolution: ${issue.resolution || '—'}\nEvidence: ${issue.evidence || '—'}`).join('\n\n'),
            };
        },
    };
    $('#rw-add-task').onclick = () => addTask();
    $('#rw-add-issue').onclick = () => addIssue();
})();
