export function haConfirm(message, onConfirm, title = 'Confirm', confirmText = 'OK', confirmClass = 'btn-ha-primary') {
    const modalEl = document.getElementById('haConfirmModal');
    if (!modalEl) return;

    const titleEl = document.getElementById('haConfirmTitle');
    const messageEl = document.getElementById('haConfirmMessage');
    const confirmBtn = document.getElementById('haConfirmBtn');

    if (titleEl) titleEl.innerText = title;
    if (messageEl) messageEl.innerHTML = message;

    if (confirmBtn) {
        confirmBtn.innerText = confirmText;
        confirmBtn.className = `btn btn-sm px-4 ${confirmClass}`;

        // Remove old listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', () => {
            onConfirm();
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

export function haAlert(message, title = 'Info') {
    const modalEl = document.getElementById('haAlertModal');
    if (!modalEl) {
        alert(message);
        return;
    }

    const titleEl = document.getElementById('haAlertTitle');
    const messageEl = document.getElementById('haAlertMessage');

    if (titleEl) titleEl.innerText = title;
    if (messageEl) messageEl.innerHTML = message;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

export function resetAccordion() {
    const accordionItems = document.querySelectorAll('#formAccordion .accordion-collapse');
    accordionItems.forEach((item, index) => {
        const collapse = bootstrap.Collapse.getOrCreateInstance(item, {toggle: false});
        if (index === 0) {
            collapse.show();
        } else {
            collapse.hide();
        }
    });
}

export function showLogs() {
    const modalEl = document.getElementById('haLogModal');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Trigger HTMX load for content
    const logContent = document.getElementById('logContent');
    if (logContent) {
        htmx.ajax('GET', `${basePath}/fragments/logs`, {target: '#logContent'});
    }
}
