// Native dialogs supply focus containment, Escape and focus restoration.
document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-dialog]');
    if (trigger) document.getElementById(trigger.dataset.dialog)?.showModal();
    if (event.target.closest('[data-dialog-close]')) event.target.closest('dialog')?.close();
    if (event.target instanceof HTMLDialogElement) {
        const rect = event.target.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) event.target.close();
    }
});
