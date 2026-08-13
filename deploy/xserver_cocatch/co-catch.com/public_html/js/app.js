document.addEventListener('DOMContentLoaded', () => {
    initializeSidebar();
    initializeCopyButtons(document);
    initializeCurrentDateTime(document);
    initializeComingSoon();
    initializeCustomerDrawer();
    initializeImportModal();
    initializeBulkOwnerForm();
    initializeSubmitGuards(document);
});

let drawerIsLoading = false;

function initializeSidebar() {
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector('[data-shell]')?.classList.toggle('is-sidebar-collapsed');
        });
    });
}

function initializeCopyButtons(scope) {
    scope.querySelectorAll('[data-copy]').forEach((button) => {
        if (button.dataset.copyBound === 'true') {
            return;
        }

        button.dataset.copyBound = 'true';
        button.addEventListener('click', async () => {
            await navigator.clipboard.writeText(button.dataset.copy || '');
            button.textContent = 'コピーしました';
            setTimeout(() => button.textContent = 'コピー', 1200);
        });
    });
}

function initializeComingSoon() {
    document.querySelector('[data-coming-soon]')?.addEventListener('click', () => {
        document.querySelector('[data-coming-soon-message]').hidden = false;
    });
}

function initializeCustomerDrawer() {
    document.querySelectorAll('[data-drawer-url]').forEach((link) => {
        bindDrawerLink(link);
    });

    document.querySelectorAll('[data-drawer-close]').forEach((button) => {
        button.addEventListener('click', closeDrawerWithGuard);
    });
}

function bindDrawerLink(link) {
    if (link.dataset.drawerLinkBound === 'true') {
        return;
    }

    link.dataset.drawerLinkBound = 'true';
    link.addEventListener('click', async (event) => {
        event.preventDefault();

        if (drawerIsLoading) {
            return;
        }

        if (hasUnsavedDrawerChanges() && ! window.confirm('保存していない変更があります。本当に移動しますか？')) {
            return;
        }

        await openCustomerDrawer(link.dataset.drawerUrl, link.dataset.customerId);
    });
}

async function openCustomerDrawer(url, customerId) {
    const drawer = document.querySelector('[data-drawer]');
    const body = document.querySelector('[data-drawer-body]');
    drawerIsLoading = true;
    drawer.hidden = false;
    body.textContent = '読み込み中...';
    setActiveCustomerRow(customerId);

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (! response.ok) {
            throw new Error(await response.text() || '詳細情報の読み込みに失敗しました。時間をおいて再度お試しください。');
        }

        body.innerHTML = await response.text();
        initializeDrawerContent(body);
    } catch (error) {
        clearActiveCustomerRow();
        body.innerHTML = '';
        showDrawerError(body, error.message);
    } finally {
        drawerIsLoading = false;
    }
}

function setActiveCustomerRow(customerId) {
    clearActiveCustomerRow();

    if (! customerId) {
        return;
    }

    document.querySelector(`[data-customer-row="${customerId}"]`)?.classList.add('is-drawer-active');
}

function clearActiveCustomerRow() {
    document.querySelectorAll('.is-drawer-active').forEach((row) => {
        row.classList.remove('is-drawer-active');
    });
}

function initializeDrawerContent(scope) {
    initializeCurrentDateTime(scope);
    initializeDrawerChangeGuard(scope);
    initializeDrawerForms(scope);
    initializeCopyButtons(scope);
    scope.querySelectorAll('[data-drawer-url]').forEach((link) => {
        bindDrawerLink(link);
    });
}

function initializeCurrentDateTime(scope) {
    scope.querySelectorAll('[data-current-datetime]').forEach((input) => {
        input.value = formatLocalDateTime(new Date());
    });
}

function formatLocalDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function getFormSnapshot(form) {
    const formData = new FormData(form);

    return JSON.stringify(Array.from(formData.entries()));
}

function initializeDrawerChangeGuard(scope) {
    scope.querySelectorAll('form').forEach((form) => {
        form.dataset.initialSnapshot = getFormSnapshot(form);
    });
}

function initializeDrawerForms(scope) {
    scope.querySelectorAll('form').forEach((form) => {
        if (form.dataset.drawerFormBound === 'true') {
            return;
        }

        form.dataset.drawerFormBound = 'true';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (form.dataset.confirmSubmit && ! window.confirm(form.dataset.confirmSubmit)) {
                return;
            }

            if (form.dataset.submitting === 'true') {
                return;
            }

            form.dataset.submitting = 'true';

            const submitButton = event.submitter || form.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (! response.ok) {
                    throw new Error(await response.text() || '保存に失敗しました。入力内容を確認して、もう一度お試しください。');
                }

                const html = await response.text();
                const body = document.querySelector('[data-drawer-body]');
                body.innerHTML = html;
                initializeDrawerContent(body);
            } catch (error) {
                form.dataset.submitting = 'false';
                showDrawerError(form, error.message);
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    });
}

function showDrawerError(target, message) {
    const scope = target.closest('[data-drawer-body]') || target;
    scope.querySelector('[data-drawer-error]')?.remove();

    const error = document.createElement('div');
    error.className = 'toast error in-drawer';
    error.dataset.drawerError = 'true';
    error.textContent = message;

    scope.prepend(error);
}

function initializeSubmitGuards(scope) {
    scope.querySelectorAll('form').forEach((form) => {
        if (form.dataset.submitGuardBound === 'true' || form.closest('[data-drawer-body]')) {
            return;
        }

        form.dataset.submitGuardBound = 'true';
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            if (form.dataset.confirmSubmit && ! window.confirm(form.dataset.confirmSubmit)) {
                event.preventDefault();
                return;
            }

            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            const submitButton = event.submitter || form.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }
        });
    });
}

function hasUnsavedDrawerChanges() {
    const drawer = document.querySelector('[data-drawer]');
    if (! drawer || drawer.hidden) {
        return false;
    }

    return Array.from(drawer.querySelectorAll('form')).some((form) => {
        return form.dataset.submitting !== 'true' && form.dataset.initialSnapshot !== getFormSnapshot(form);
    });
}

function closeDrawerWithGuard() {
    if (hasUnsavedDrawerChanges() && ! window.confirm('保存していない変更があります。本当に閉じますか？')) {
        return;
    }

    document.querySelector('[data-drawer]').hidden = true;
    clearActiveCustomerRow();
}

function initializeImportModal() {
    const modal = document.querySelector('[data-import-modal]');
    if (! modal) {
        return;
    }

    document.querySelector('[data-import-open]')?.addEventListener('click', () => {
        openImportModal();
    });

    document.querySelectorAll('[data-import-close]').forEach((button) => {
        button.addEventListener('click', () => {
            closeImportModal();
        });
    });

    if (! modal.hidden) {
        lockPageScroll();
    }
}

function openImportModal() {
    const modal = document.querySelector('[data-import-modal]');
    if (! modal) {
        return;
    }

    modal.hidden = false;
    lockPageScroll();
}

function closeImportModal() {
    const modal = document.querySelector('[data-import-modal]');
    if (! modal) {
        return;
    }

    modal.hidden = true;
    modal.querySelector('[data-import-errors]')?.remove();
    modal.querySelector('[data-import-form]')?.reset();
    unlockPageScroll();
}

function lockPageScroll() {
    document.body.classList.add('is-scroll-locked');
}

function unlockPageScroll() {
    document.body.classList.remove('is-scroll-locked');
}

function initializeBulkOwnerForm() {
    const form = document.querySelector('[data-bulk-owner-form]');
    if (! form) {
        return;
    }

    const checkAll = document.querySelector('[data-bulk-check-all]');
    const checkboxes = Array.from(document.querySelectorAll('[data-bulk-check]'));
    const selectedCount = document.querySelector('[data-bulk-selected-count]');

    const updateSelectedState = () => {
        const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (selectedCount) {
            selectedCount.textContent = `${checkedCount}件選択中`;
        }

        if (checkAll) {
            checkAll.checked = checkedCount > 0 && checkedCount === checkboxes.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }
    };

    checkAll?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = checkAll.checked;
        });
        updateSelectedState();
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectedState);
    });

    form.addEventListener('submit', (event) => {
        if (! checkboxes.some((checkbox) => checkbox.checked)) {
            event.preventDefault();
            alert('担当者を一括設定する顧客を選択してください。');
        }
    });

    updateSelectedState();
}
