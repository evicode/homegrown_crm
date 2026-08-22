const toggle = document.querySelector('[data-nav-toggle]');
const navigation = document.querySelector('[data-navigation]');

if (toggle instanceof HTMLButtonElement && navigation instanceof HTMLElement) {
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        navigation.toggleAttribute('data-open', open);
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
