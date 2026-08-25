const toggle = document.querySelector('[data-nav-toggle]');
const navigation = document.querySelector('[data-navigation]');

if (toggle instanceof HTMLButtonElement && navigation instanceof HTMLElement) {
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        navigation.toggleAttribute('data-open', open);
        toggle.closest('.app-sidebar')?.toggleAttribute('data-open', open);
    });
}

const validationSummary = document.querySelector('[data-validation-summary]');
if (validationSummary instanceof HTMLElement) {
    validationSummary.focus();
}

document.querySelectorAll('[data-dialog]').forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) return;
    const openerSelector = dialog.dataset.dialog;
    const opener = openerSelector ? document.querySelector(openerSelector) : null;
    const close = dialog.querySelector('[data-dialog-close]');
    if (opener instanceof HTMLElement) opener.addEventListener('click', () => dialog.showModal());
    if (close instanceof HTMLElement) close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (opener instanceof HTMLElement) opener.focus();
    });
});

document.querySelectorAll('[data-dialog-open]').forEach((opener) => {
    if (!(opener instanceof HTMLElement)) return;
    const dialog = document.getElementById(opener.dataset.dialogOpen || '');
    if (!(dialog instanceof HTMLDialogElement)) return;
    opener.addEventListener('click', () => {
        const companySelect = document.querySelector('#prospect-company');
        const modalCompanySelect = dialog.querySelector('[data-copy-company]');
        if (companySelect instanceof HTMLSelectElement && modalCompanySelect instanceof HTMLSelectElement) {
            modalCompanySelect.value = companySelect.value;
        }
        dialog.showModal();
    });
});

document.querySelectorAll('[data-dialog-close]').forEach((close) => {
    if (!(close instanceof HTMLElement)) return;
    close.addEventListener('click', () => close.closest('dialog')?.close());
});

document.querySelectorAll('[data-quick-create]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const error = form.querySelector('[data-quick-error]');
        if (error instanceof HTMLElement) error.hidden = true;
        const submit = form.querySelector('button[type="submit"]');
        if (submit instanceof HTMLButtonElement) submit.disabled = true;
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form)});
            const payload = await response.json();
            if (!response.ok || !payload.record) throw new Error(payload.error || 'Could not save this record.');
            const target = document.getElementById(form.dataset.targetSelect || '');
            if (!(target instanceof HTMLSelectElement)) throw new Error('The new record was saved, but the form could not select it. Refresh the page to continue.');
            const option = new Option(payload.record.label, String(payload.record.id), true, true);
            target.add(option);
            if (target.id === 'prospect-company') {
                document.querySelectorAll('[data-copy-company]').forEach((select) => {
                    if (select instanceof HTMLSelectElement) select.add(new Option(payload.record.label, String(payload.record.id)));
                });
            }
            form.reset();
            form.closest('dialog')?.close();
        } catch (exception) {
            if (error instanceof HTMLElement) {
                error.textContent = exception instanceof Error ? exception.message : 'Could not save this record.';
                error.hidden = false;
            }
        } finally {
            if (submit instanceof HTMLButtonElement) submit.disabled = false;
        }
    });
});

const priorityRows = (target) => {
    if (!(target instanceof HTMLElement)) return [];
    return [...target.querySelectorAll('[data-profile-weight-row]')];
};

const appendPriorityRow = (target, term = '', weight = '1') => {
    if (!(target instanceof HTMLElement)) return null;
    const template = target.parentElement?.querySelector('[data-profile-weight-template]');
    if (!(template instanceof HTMLTemplateElement)) return null;
    const row = template.content.firstElementChild?.cloneNode(true);
    if (!(row instanceof HTMLElement)) return null;
    const input = row.querySelector('input[name="positive_keyword[]"]');
    const select = row.querySelector('select[name="positive_keyword_weight[]"]');
    if (input instanceof HTMLInputElement) input.value = term;
    if (select instanceof HTMLSelectElement) select.value = String(weight);
    target.append(row);
    return row;
};

document.querySelectorAll('[data-profile-weight-add]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;
    button.addEventListener('click', () => {
        const target = button.parentElement?.querySelector('[data-profile-weight-rows]');
        const row = appendPriorityRow(target);
        row?.querySelector('input')?.focus();
    });
});

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-profile-weight-remove]') : null;
    if (!(button instanceof HTMLButtonElement)) return;
    const row = button.closest('[data-profile-weight-row]');
    const list = button.closest('[data-profile-weight-rows]');
    if (!(row instanceof HTMLElement) || !(list instanceof HTMLElement)) return;
    if (priorityRows(list).length === 1) {
        const input = row.querySelector('input');
        if (input instanceof HTMLInputElement) { input.value = ''; input.focus(); }
        const select = row.querySelector('select');
        if (select instanceof HTMLSelectElement) select.value = '1';
        return;
    }
    row.remove();
});

const fillPriorityRows = (target, text) => {
    if (!(target instanceof HTMLElement)) return false;
    const values = text.split(/\r?\n/).map((line) => {
        const [term, weight = '1'] = line.split('|', 2);
        return {term: term.trim(), weight: String(Math.max(1, Math.min(20, Number.parseInt(weight.trim(), 10) || 1)))};
    }).filter((value) => value.term !== '');
    priorityRows(target).forEach((row) => row.remove());
    (values.length > 0 ? values : [{term: '', weight: '1'}]).forEach((value) => appendPriorityRow(target, value.term, value.weight));
    return true;
};

document.querySelectorAll('[data-profile-upload]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) return;
    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;
        const form = input.closest('form');
        const target = document.getElementById(input.dataset.target || '');
        const status = input.closest('.profile-upload')?.querySelector('[data-profile-upload-status]');
        const csrf = form?.querySelector('input[name="_csrf"]');
        const uploadUrl = form?.dataset.profileUploadUrl;
        const priorityTarget = target instanceof HTMLElement && target.hasAttribute('data-profile-weight-rows');
        if (!(form instanceof HTMLFormElement) || (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) && !priorityTarget) || !(csrf instanceof HTMLInputElement) || !uploadUrl) return;
        input.disabled = true;
        if (status instanceof HTMLElement) status.textContent = 'Reading file…';
        try {
            const data = new FormData();
            data.append('_csrf', csrf.value);
            data.append('field', input.dataset.field || '');
            data.append('profile_file', file);
            const response = await fetch(uploadUrl, {method: 'POST', body: data});
            const payload = await response.json();
            if (!response.ok || typeof payload.text !== 'string') throw new Error(payload.error || 'Could not read that file.');
            if (priorityTarget) fillPriorityRows(target, payload.text);
            else if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                target.value = payload.text;
                target.dispatchEvent(new Event('input', {bubbles: true}));
                target.focus();
            }
            if (status instanceof HTMLElement) status.textContent = 'Field filled. You can edit it before saving.';
        } catch (exception) {
            if (status instanceof HTMLElement) status.textContent = exception instanceof Error ? exception.message : 'Could not read that file.';
        } finally {
            input.value = '';
            input.disabled = false;
        }
    });
});
