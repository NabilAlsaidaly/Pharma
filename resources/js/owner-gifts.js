document.querySelectorAll('[data-gift-editor]').forEach((form) => {
    let nextIndex = 50;
    const container = form.querySelector('[data-gift-components]');
    const status = form.querySelector('[data-gift-status]');
    form.addEventListener('click', (event) => {
        const add = event.target.closest('[data-gift-add]');
        const remove = event.target.closest('[data-gift-remove]');
        if (add) {
            if (container.children.length >= 50) {
                status.textContent = 'الحد الأقصى 50 مكوناً.';
                return;
            }
            const template = form.querySelector(`[data-gift-template="${add.dataset.giftAdd}"]`);
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace('__INDEX__', String(nextIndex));
            });
            nextIndex += 1;
            container.append(fragment);
            container.lastElementChild.querySelector('select, input:not([type="hidden"])')?.focus();
            status.textContent = 'أضيف المكون إلى النموذج. احفظي المسودة لتثبيته.';
        }
        if (remove) {
            const row = remove.closest('[data-gift-row]');
            const target = row.nextElementSibling?.querySelector('input:not([type="hidden"]), select') || form.querySelector('[data-gift-add]');
            row.remove();
            target?.focus();
            status.textContent = 'أزيل السطر من النموذج؛ لا تتغير النسخة المنشورة حتى النشر الصريح.';
        }
    });
});

const giftConfirmation = document.querySelector('[data-gift-confirmation]');
if (giftConfirmation && typeof giftConfirmation.showModal === 'function') {
    let pendingForm = null;
    let submitter = null;
    document.querySelectorAll('[data-gift-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.giftConfirmed === 'yes') return;
            event.preventDefault();
            pendingForm = form;
            submitter = event.submitter;
            giftConfirmation.querySelector('[data-gift-confirm-label]').textContent = form.dataset.giftConfirm;
            giftConfirmation.showModal();
        });
    });
    giftConfirmation.querySelector('[data-gift-accept]').addEventListener('click', () => {
        if (!pendingForm) return;
        pendingForm.dataset.giftConfirmed = 'yes';
        const form = pendingForm;
        giftConfirmation.close();
        form.requestSubmit();
    });
    giftConfirmation.querySelector('[data-gift-cancel]').addEventListener('click', () => giftConfirmation.close());
    giftConfirmation.addEventListener('close', () => {
        submitter?.focus();
        pendingForm = null;
    });
}
