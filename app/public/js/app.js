document.addEventListener('DOMContentLoaded', () => {
    initializeSidebar();
    initializeCopyButtons(document);
    initializeCurrentDateTime(document);
    initializeNativeDateInputs(document);
    initializeListDatePickers(document);
    initializeDateTimePickers(document);
    initializeComingSoon();
    initializeCustomerDrawer();
    initializeCustomerSortLinks(document);
    initializeImportModal();
    initializeUserInviteModal();
    initializeRowActionMenus();
    initializeBulkOwnerForm();
    initializeSubmitGuards(document);
    initializeActivityCollapse(document);
    initializeCustomerStatusSync(document);
    initializeCustomerAlerts();
});

let drawerIsLoading = false;

const CUSTOMER_STATUS_CLASSES = {
    '未対応': 'not-started',
    '担当不在': 'unavailable',
    '受付ブロック': 'reception-block',
    'メール': 'email',
    '連絡済み': 'contacted',
    'やり取り中': 'in-progress',
    '見込み（アポイント時）': 'prospect-before-appointment',
    'アポイント': 'appointment',
    '見込み（アポイント後）': 'prospect-after-appointment',
    '追客': 'follow-up',
    '現アナ': 'current-analysis',
    '商談中': 'negotiation',
    '契約': 'contracted',
    'NG': 'ng',
    '失注': 'lost',
};

const CUSTOMER_STATUS_SYNC_CHANNEL = 'opnavi-customer-status';
const CUSTOMER_ALERT_STORAGE_PREFIX = 'opnavi-customer-alert';
const CUSTOMER_ALERT_CHECK_INTERVAL_MS = 60 * 1000;

function initializeSidebar() {
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector('[data-shell]')?.classList.toggle('is-sidebar-collapsed');
        });
    });
}

function initializeRowActionMenus() {
    if (window.rowActionMenusBound === true) {
        return;
    }

    window.rowActionMenusBound = true;

    document.addEventListener('toggle', (event) => {
        const menu = event.target;

        if (! menu.matches?.('.row-action-menu') || ! menu.open) {
            return;
        }

        document.querySelectorAll('.row-action-menu[open]').forEach((otherMenu) => {
            if (otherMenu !== menu) {
                otherMenu.removeAttribute('open');
            }
        });

        positionRowActionMenu(menu);
    }, true);

    document.addEventListener('click', (event) => {
        document.querySelectorAll('.row-action-menu[open]').forEach((menu) => {
            if (! menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.row-action-menu[open]').forEach((menu) => {
            menu.removeAttribute('open');
        });
    });
}

function positionRowActionMenu(menu) {
    const items = menu.querySelector('.row-action-menu__items');
    const summary = menu.querySelector('summary');
    if (! items || ! summary) {
        return;
    }

    const margin = 8;
    const gap = 6;
    const summaryRect = summary.getBoundingClientRect();
    const itemWidth = items.offsetWidth || 190;
    const itemHeight = items.offsetHeight;

    const maxLeft = window.innerWidth - itemWidth - margin;
    const maxTop = window.innerHeight - itemHeight - margin;
    const left = Math.max(margin, Math.min(maxLeft, summaryRect.right - itemWidth));

    let top = summaryRect.bottom + gap;
    if (top + itemHeight + margin > window.innerHeight) {
        top = summaryRect.top - itemHeight - gap;
    }

    items.style.left = `${left}px`;
    items.style.top = `${Math.max(margin, Math.min(maxTop, top))}px`;
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

function initializeCustomerSortLinks(scope) {
    scope.querySelectorAll('[data-customer-sort-link]').forEach((link) => {
        if (link.dataset.customerSortBound === 'true') {
            return;
        }

        link.dataset.customerSortBound = 'true';
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            await sortCustomerList(link.href);
        });
    });
}

async function sortCustomerList(url) {
    const panel = document.querySelector('[data-customer-list-panel]');
    if (! panel || panel.dataset.loading === 'true') {
        return;
    }

    if (hasUnsavedDrawerChanges() && ! window.confirm('保存していない変更があります。並び替えを実行しますか？')) {
        return;
    }

    panel.dataset.loading = 'true';
    panel.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (! response.ok) {
            throw new Error('一覧の並び替えに失敗しました。時間をおいて再度お試しください。');
        }

        const html = await response.text();
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
        const nextPanel = nextDocument.querySelector('[data-customer-list-panel]');

        if (! nextPanel) {
            throw new Error('一覧の更新に必要なHTMLが見つかりませんでした。');
        }

        panel.replaceWith(nextPanel);
        window.history.pushState({}, '', url);
        initializeCustomerListPanel(nextPanel);
    } catch (error) {
        alert(error.message);
        panel.dataset.loading = 'false';
        panel.removeAttribute('aria-busy');
    }
}

function initializeCustomerListPanel(scope) {
    initializeCustomerSortLinks(scope);
    initializeListDatePickers(scope);
    initializeSubmitGuards(scope);
    initializeBulkOwnerForm();
    scope.querySelectorAll('[data-drawer-url]').forEach((link) => {
        bindDrawerLink(link);
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
    initializeNativeDateInputs(scope);
    initializeListDatePickers(scope);
    initializeDateTimePickers(scope);
    initializeDrawerChangeGuard(scope);
    initializeDrawerForms(scope);
    initializeCopyButtons(scope);
    initializeActivityCollapse(scope);
    initializeCustomerStatusSync(scope);
    scope.querySelectorAll('[data-drawer-url]').forEach((link) => {
        bindDrawerLink(link);
    });
}

function initializeCustomerStatusSync(scope) {
    if (scope === document) {
        listenForCustomerStatusUpdates();
    }

    const currentStatusElement = scope.querySelector('[data-current-customer-status]');
    if (! currentStatusElement || currentStatusElement.dataset.statusSyncBroadcasted === 'true') {
        return;
    }

    currentStatusElement.dataset.statusSyncBroadcasted = 'true';
    const customerId = currentStatusElement.dataset.customerId;
    const status = currentStatusElement.textContent.trim();

    if (customerId && status && hasStatusSuccessMessage(scope)) {
        broadcastCustomerStatusUpdate(customerId, status);
    }
}

function hasStatusSuccessMessage(scope) {
    return Boolean(scope.querySelector('.toast.success'));
}

function listenForCustomerStatusUpdates() {
    if (window.customerStatusSyncListening === true) {
        return;
    }

    window.customerStatusSyncListening = true;

    if ('BroadcastChannel' in window) {
        const channel = new BroadcastChannel(CUSTOMER_STATUS_SYNC_CHANNEL);
        channel.addEventListener('message', (event) => {
            applyCustomerStatusUpdate(event.data);
        });
    }

    window.addEventListener('storage', (event) => {
        if (event.key !== CUSTOMER_STATUS_SYNC_CHANNEL || ! event.newValue) {
            return;
        }

        try {
            applyCustomerStatusUpdate(JSON.parse(event.newValue));
        } catch {
            // Ignore malformed cross-tab messages.
        }
    });
}

function broadcastCustomerStatusUpdate(customerId, status) {
    const payload = {
        customerId: String(customerId),
        status,
        timestamp: Date.now(),
    };

    applyCustomerStatusUpdate(payload);

    if ('BroadcastChannel' in window) {
        const channel = new BroadcastChannel(CUSTOMER_STATUS_SYNC_CHANNEL);
        channel.postMessage(payload);
        channel.close();
    }

    try {
        localStorage.setItem(CUSTOMER_STATUS_SYNC_CHANNEL, JSON.stringify(payload));
        localStorage.removeItem(CUSTOMER_STATUS_SYNC_CHANNEL);
    } catch {
        // Storage can be unavailable in private or restricted browser contexts.
    }
}

function applyCustomerStatusUpdate(payload) {
    if (! payload?.customerId || ! payload?.status) {
        return;
    }

    const row = findCustomerRow(payload.customerId);
    updateCustomerStatusPill(row?.querySelector('[data-customer-status-pill]'), payload.status);
}

function findCustomerRow(customerId) {
    return Array.from(document.querySelectorAll('[data-customer-row]')).find((row) => {
        return row.dataset.customerRow === String(customerId);
    });
}

function updateCustomerStatusPill(statusPill, status) {
    if (! statusPill || ! status) {
        return;
    }

    statusPill.textContent = status;
    statusPill.className = `status-pill status-pill--${CUSTOMER_STATUS_CLASSES[status] || 'default'}`;
}

function initializeCustomerAlerts() {
    const shell = document.querySelector('[data-customer-alerts-url]');
    if (! shell || window.customerAlertsBound === true) {
        return;
    }

    window.customerAlertsBound = true;
    const alertsUrl = shell.dataset.customerAlertsUrl;

    const checkAlerts = async () => {
        if (! alertsUrl || document.hidden) {
            return;
        }

        try {
            const response = await fetch(alertsUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            showCustomerAlerts(payload.alerts || []);
        } catch {
            // Alert checks should never interrupt normal screen operation.
        }
    };

    window.setTimeout(checkAlerts, 1000);
    window.setInterval(checkAlerts, CUSTOMER_ALERT_CHECK_INTERVAL_MS);
    document.addEventListener('visibilitychange', () => {
        if (! document.hidden) {
            checkAlerts();
        }
    });
}

function showCustomerAlerts(alerts) {
    alerts.forEach((customerAlert) => {
        const storageKey = customerAlertStorageKey(customerAlert);
        if (! storageKey || hasShownCustomerAlert(storageKey)) {
            return;
        }

        rememberCustomerAlert(storageKey);
        showCustomerAlertPopup(customerAlert);
    });
}

function showCustomerAlertPopup(customerAlert) {
    const container = customerAlertContainer();
    const popup = document.createElement('section');
    const message = document.createElement('p');
    const openLink = document.createElement('a');

    popup.className = 'customer-alert-popup';
    popup.setAttribute('role', 'alertdialog');
    popup.setAttribute('aria-live', 'assertive');

    message.className = 'customer-alert-popup__message';
    message.textContent = customerAlert.message || `${customerAlert.business_name}の次回アクション日時が近づいてきました`;

    openLink.className = 'button primary customer-alert-popup__open';
    openLink.href = customerAlert.detail_url || '#';
    openLink.target = '_blank';
    openLink.rel = 'noreferrer';
    openLink.textContent = '開く';
    openLink.addEventListener('click', () => {
        popup.remove();
        if (container.children.length === 0) {
            container.remove();
        }
    });

    popup.append(message, openLink);
    container.append(popup);
}

function customerAlertContainer() {
    let container = document.querySelector('[data-customer-alert-popups]');
    if (! container) {
        container = document.createElement('div');
        container.className = 'customer-alert-popups';
        container.dataset.customerAlertPopups = 'true';
        document.body.append(container);
    }

    return container;
}

function customerAlertStorageKey(customerAlert) {
    if (! customerAlert?.id || ! customerAlert?.next_action_at) {
        return null;
    }

    return `${CUSTOMER_ALERT_STORAGE_PREFIX}:${customerAlert.id}:${customerAlert.next_action_at}`;
}

function hasShownCustomerAlert(storageKey) {
    window.shownCustomerAlertKeys = window.shownCustomerAlertKeys || new Set();
    if (window.shownCustomerAlertKeys.has(storageKey)) {
        return true;
    }

    try {
        return window.localStorage.getItem(storageKey) === 'shown';
    } catch {
        return false;
    }
}

function rememberCustomerAlert(storageKey) {
    window.shownCustomerAlertKeys = window.shownCustomerAlertKeys || new Set();
    window.shownCustomerAlertKeys.add(storageKey);

    try {
        window.localStorage.setItem(storageKey, 'shown');
    } catch {
        // localStorage can be unavailable in private or restricted browser contexts.
    }
}

function syncActiveCustomerRowStatus(scope) {
    const currentStatusElement = scope.querySelector('[data-current-customer-status]');
    const activeRow = document.querySelector('.is-drawer-active');

    updateCustomerStatusPill(
        activeRow?.querySelector('[data-customer-status-pill]'),
        currentStatusElement?.textContent.trim(),
    );
}

function initializeActivityCollapse(scope) {
    scope.querySelectorAll('[data-activity-toggle]').forEach((button) => {
        if (button.dataset.activityToggleBound === 'true') {
            return;
        }

        button.dataset.activityToggleBound = 'true';
        const item = button.closest('.activity-item');
        const icon = button.querySelector('.activity-toggle-icon');

        if (! item) {
            return;
        }

        const setCollapsed = (isCollapsed) => {
            item.classList.toggle('is-collapsed', isCollapsed);
            if (icon) {
                icon.textContent = isCollapsed ? '▶' : '▼';
            }
            button.setAttribute('aria-label', isCollapsed ? '履歴を展開する' : '履歴を折りたたむ');
            button.setAttribute('aria-expanded', String(! isCollapsed));
        };

        setCollapsed(item.classList.contains('is-collapsed'));

        button.addEventListener('click', () => {
            setCollapsed(! item.classList.contains('is-collapsed'));
        });
    });
}

function initializeNativeDateInputs(scope) {
    scope.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach((input) => {
        if (input.dataset.nativeDateBound === 'true') {
            return;
        }

        input.dataset.nativeDateBound = 'true';
        input.addEventListener('change', () => {
            if (input.dataset.submitOnApply === 'true') {
                input.form?.requestSubmit();
            }
        });
    });
}

function initializeListDatePickers(scope) {
    scope.querySelectorAll('[data-list-date-picker]').forEach((picker) => {
        if (picker.dataset.listDatePickerBound === 'true') {
            return;
        }

        picker.dataset.listDatePickerBound = 'true';
        mountListDatePicker(picker);
    });
}

function initializeDateTimePickers(scope) {
    scope.querySelectorAll('[data-date-time-picker]').forEach((picker) => {
        if (picker.dataset.dateTimePickerBound === 'true') {
            return;
        }

        picker.dataset.dateTimePickerBound = 'true';
        mountDateTimePicker(picker);
    });
}

function mountDateTimePicker(picker) {
    const valueInput = picker.querySelector('[data-date-time-value]');
    const trigger = picker.querySelector('.date-time-picker__trigger');
    const panel = picker.querySelector('.date-time-picker__panel');
    const label = picker.querySelector('[data-date-time-label]');
    const monthLabel = picker.querySelector('[data-month-label]');
    const dates = picker.querySelector('[data-dates]');
    const hourSelect = picker.querySelector('[data-hour]');
    const minuteSelect = picker.querySelector('[data-minute]');

    if (! valueInput || ! trigger || ! panel || ! label || ! monthLabel || ! dates || ! hourSelect || ! minuteSelect) {
        return;
    }

    fillDateTimeSelect(hourSelect, 0, 23, 1);
    fillDateTimeSelect(minuteSelect, 0, 55, 5);

    let committedDateTime = parseLocalDateTime(valueInput.value);
    let draftDateTime = committedDateTime ? new Date(committedDateTime) : roundToFiveMinutes(new Date());
    let viewDate = startOfMonth(draftDateTime);
    let focusDay = draftDateTime.getDate();
    let lastActiveCell = null;

    const syncTimeControls = () => {
        const minute = draftDateTime.getMinutes() - (draftDateTime.getMinutes() % 5);
        hourSelect.value = String(draftDateTime.getHours());
        minuteSelect.value = String(minute);
    };

    const render = () => {
        label.textContent = formatJapaneseDateTime(committedDateTime) || '日時を選択';
        monthLabel.textContent = `${viewDate.getFullYear()}年（令和${viewDate.getFullYear() - 2018}年）${viewDate.getMonth() + 1}月`;
        syncTimeControls();
        dates.replaceChildren();
        lastActiveCell = null;

        const firstWeekday = viewDate.getDay();
        const monthDays = daysInMonth(viewDate);

        for (let index = 0; index < 42; index += 1) {
            const dayOffset = index - firstWeekday + 1;
            const cellDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), dayOffset);
            const isOutside = dayOffset < 1 || dayOffset > monthDays;
            const isSelected = sameDay(cellDate, draftDateTime);
            const isFocusTarget = ! isOutside && cellDate.getDate() === Math.min(focusDay, monthDays);
            const button = document.createElement('button');

            button.type = 'button';
            button.className = `date-time-picker__day${isOutside ? ' is-outside' : ''}${isSelected ? ' is-selected' : ''}${isFocusTarget ? ' is-focused' : ''}`;
            button.textContent = cellDate.getDate();
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', formatJapaneseDate(cellDate));
            button.setAttribute('aria-selected', String(isSelected));
            button.tabIndex = isFocusTarget ? 0 : -1;
            button.addEventListener('click', () => {
                draftDateTime.setFullYear(cellDate.getFullYear(), cellDate.getMonth(), cellDate.getDate());
                viewDate = startOfMonth(draftDateTime);
                focusDay = draftDateTime.getDate();
                render();
            });
            dates.append(button);

            if (isFocusTarget) {
                lastActiveCell = button;
            }
        }
    };

    const setPanelPosition = () => {
        positionFloatingPanel(trigger, panel);
    };

    const closePanel = (returnFocus = false) => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');

        if (returnFocus) {
            trigger.focus();
        }
    };

    const openPanel = () => {
        committedDateTime = parseLocalDateTime(valueInput.value);
        draftDateTime = committedDateTime ? new Date(committedDateTime) : roundToFiveMinutes(new Date());
        viewDate = startOfMonth(draftDateTime);
        focusDay = draftDateTime.getDate();
        render();
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        setPanelPosition();
        requestAnimationFrame(() => lastActiveCell?.focus());
    };

    const moveMonth = (delta) => {
        viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + delta, 1);
        focusDay = Math.min(focusDay, daysInMonth(viewDate));
        render();
        setPanelPosition();
        requestAnimationFrame(() => lastActiveCell?.focus());
    };

    trigger.addEventListener('click', () => {
        if (panel.hidden) {
            openPanel();
            return;
        }

        closePanel();
    });
    picker.querySelector('[data-prev-month]')?.addEventListener('click', () => moveMonth(-1));
    picker.querySelector('[data-next-month]')?.addEventListener('click', () => moveMonth(1));
    picker.querySelector('[data-clear]')?.addEventListener('click', () => {
        committedDateTime = null;
        valueInput.value = '';
        valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        closePanel(true);
        render();
    });
    picker.querySelector('[data-now]')?.addEventListener('click', () => {
        draftDateTime = roundToFiveMinutes(new Date());
        viewDate = startOfMonth(draftDateTime);
        focusDay = draftDateTime.getDate();
        committedDateTime = new Date(draftDateTime);
        valueInput.value = toLocalDateTimeValue(committedDateTime);
        valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        closePanel(true);
        render();
    });
    picker.querySelector('[data-cancel]')?.addEventListener('click', () => closePanel(true));
    picker.querySelector('[data-apply]')?.addEventListener('click', () => {
        draftDateTime.setHours(Number(hourSelect.value), Number(minuteSelect.value), 0, 0);
        committedDateTime = new Date(draftDateTime);
        valueInput.value = toLocalDateTimeValue(committedDateTime);
        valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        closePanel(true);
        render();
    });
    hourSelect.addEventListener('change', () => {
        draftDateTime.setHours(Number(hourSelect.value), draftDateTime.getMinutes(), 0, 0);
    });
    minuteSelect.addEventListener('change', () => {
        draftDateTime.setHours(draftDateTime.getHours(), Number(minuteSelect.value), 0, 0);
    });
    picker.querySelectorAll('[data-time-shortcut]').forEach((button) => {
        button.addEventListener('click', () => {
            const [hour, minute] = button.dataset.timeShortcut.split(':').map(Number);
            draftDateTime.setHours(hour, minute, 0, 0);
            syncTimeControls();
        });
    });
    document.addEventListener('pointerdown', (event) => {
        if (! picker.contains(event.target) && ! panel.hidden) {
            closePanel();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! panel.hidden) {
            closePanel(true);
        }
    });
    window.addEventListener('resize', () => {
        if (! panel.hidden) {
            setPanelPosition();
        }
    });
    window.addEventListener('scroll', () => {
        if (! panel.hidden) {
            setPanelPosition();
        }
    }, true);

    render();
}

function fillDateTimeSelect(select, min, max, step) {
    for (let value = min; value <= max; value += step) {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = String(value).padStart(2, '0');
        select.append(option);
    }
}

function parseLocalDateTime(value) {
    if (! value) {
        return null;
    }

    const [datePart, timePart = '00:00'] = value.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);

    return new Date(year, month - 1, day, hour || 0, minute || 0, 0, 0);
}

function toLocalDateTimeValue(date) {
    return `${toYmd(date)}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function formatJapaneseDateTime(date) {
    if (! date) {
        return '';
    }

    return `${formatJapaneseDate(date)} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function roundToFiveMinutes(date) {
    const rounded = new Date(date);
    rounded.setSeconds(0, 0);
    rounded.setMinutes(Math.floor(rounded.getMinutes() / 5) * 5);

    return rounded;
}

function positionFloatingPanel(trigger, panel) {
    const margin = 12;
    const gap = 4;
    const rect = trigger.getBoundingClientRect();
    const panelWidth = panel.offsetWidth;
    const panelHeight = panel.offsetHeight;
    const belowTop = rect.bottom + gap;
    const aboveTop = rect.top - panelHeight - gap;
    const fitsBelow = belowTop + panelHeight <= window.innerHeight - margin;
    const top = fitsBelow ? belowTop : Math.max(margin, aboveTop);
    const left = Math.max(margin, Math.min(rect.left, window.innerWidth - panelWidth - margin));

    panel.style.top = `${top}px`;
    panel.style.left = `${left}px`;
    panel.style.maxHeight = `${window.innerHeight - (margin * 2)}px`;
}

function mountListDatePicker(picker) {
    const valueInput = picker.querySelector('[data-list-date-value]');
    const trigger = picker.querySelector('.list-date-picker__trigger');
    const calendar = picker.querySelector('.list-date-picker__calendar');
    const monthLabel = picker.querySelector('[data-month-label]');
    const dates = picker.querySelector('[data-dates]');

    if (! valueInput || ! trigger || ! calendar || ! monthLabel || ! dates) {
        return;
    }

    let selectedDate = parseYmd(valueInput.value);
    let viewDate = startOfMonth(selectedDate || new Date());
    let focusDay = selectedDate?.getDate() || new Date().getDate();
    let lastActiveCell = null;

    const render = () => {
        trigger.querySelector('[data-list-date-label]').textContent = formatJapaneseDate(selectedDate) || '日付を選択';
        monthLabel.textContent = `${viewDate.getFullYear()}年（令和${viewDate.getFullYear() - 2018}年）${viewDate.getMonth() + 1}月`;
        dates.replaceChildren();
        lastActiveCell = null;

        const firstWeekday = viewDate.getDay();
        const monthDays = daysInMonth(viewDate);
        const cells = 42;

        for (let index = 0; index < cells; index += 1) {
            const dayOffset = index - firstWeekday + 1;
            const cellDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), dayOffset);
            const isOutside = dayOffset < 1 || dayOffset > monthDays;
            const isSelected = sameDay(cellDate, selectedDate);
            const isFocusTarget = ! isOutside && cellDate.getDate() === Math.min(focusDay, monthDays);
            const button = document.createElement('button');

            button.type = 'button';
            button.className = `list-date-picker__day${isOutside ? ' is-outside' : ''}${isSelected ? ' is-selected' : ''}${isFocusTarget ? ' is-focused' : ''}`;
            button.textContent = cellDate.getDate();
            button.dataset.date = toYmd(cellDate);
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', formatJapaneseDate(cellDate));
            button.setAttribute('aria-selected', String(isSelected));
            button.tabIndex = isFocusTarget ? 0 : -1;
            button.addEventListener('click', () => selectDate(cellDate));
            dates.append(button);

            if (isFocusTarget) {
                lastActiveCell = button;
            }
        }
    };

    const setCalendarPosition = () => {
        positionFloatingPanel(trigger, calendar);
    };

    const moveMonth = (delta) => {
        viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + delta, 1);
        render();
        setCalendarPosition();
        lastActiveCell?.focus();
    };

    const closeCalendar = () => {
        calendar.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };

    const selectDate = (date) => {
        selectedDate = date;
        viewDate = startOfMonth(date);
        focusDay = date.getDate();
        valueInput.value = toYmd(date);
        valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        closeCalendar();
        render();
    };

    const openCalendar = () => {
        if (selectedDate) {
            viewDate = startOfMonth(selectedDate);
            focusDay = selectedDate.getDate();
        }

        render();
        calendar.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        setCalendarPosition();
        requestAnimationFrame(() => lastActiveCell?.focus());
    };

    trigger.addEventListener('click', () => {
        if (calendar.hidden) {
            openCalendar();
            return;
        }

        closeCalendar();
    });

    picker.querySelector('[data-prev-month]')?.addEventListener('click', () => moveMonth(-1));
    picker.querySelector('[data-next-month]')?.addEventListener('click', () => moveMonth(1));
    picker.querySelector('[data-clear]')?.addEventListener('click', () => {
        selectedDate = null;
        valueInput.value = '';
        valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        closeCalendar();
        render();
    });
    picker.querySelector('[data-today]')?.addEventListener('click', () => selectDate(new Date()));
    valueInput.addEventListener('change', () => {
        if (picker.dataset.submitOnApply === 'true') {
            valueInput.form?.requestSubmit();
        }
    });
    document.addEventListener('pointerdown', (event) => {
        if (! picker.contains(event.target)) {
            closeCalendar();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! calendar.hidden) {
            closeCalendar();
            trigger.focus();
        }
    });
    window.addEventListener('resize', () => {
        if (! calendar.hidden) {
            setCalendarPosition();
        }
    });
    window.addEventListener('scroll', () => {
        if (! calendar.hidden) {
            setCalendarPosition();
        }
    }, true);

    render();
}

function parseYmd(ymd) {
    if (! ymd) {
        return null;
    }

    const [year, month, day] = ymd.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function toYmd(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatJapaneseDate(date) {
    return date
        ? new Intl.DateTimeFormat('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(date).replaceAll('-', '/')
        : '';
}

function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function sameDay(a, b) {
    return Boolean(a && b
        && a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate());
}

function initializeConfirmedDateInputs(scope) {
    scope.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach((input) => {
        if (input.dataset.confirmedDateBound === 'true' || input.disabled) {
            return;
        }

        input.dataset.confirmedDateBound = 'true';
        const originalType = input.type;
        input.type = 'hidden';
        input.dataset.originalDateType = originalType;

        const control = buildConfirmedDateControl(input, originalType);
        input.insertAdjacentElement('afterend', control);
    });
}

function buildConfirmedDateControl(input, originalType) {
    const control = document.createElement('div');
    control.className = 'confirmed-date';

    const display = document.createElement('input');
    display.type = 'text';
    display.readOnly = true;
    display.className = 'confirmed-date__display';
    display.value = formatConfirmedDateDisplay(input.value, originalType);
    display.placeholder = originalType === 'datetime-local' ? '年/月/日 時:分' : '年/月/日';

    const openButton = document.createElement('button');
    openButton.type = 'button';
    openButton.className = 'confirmed-date__button';
    openButton.setAttribute('aria-label', '日付を選択');
    openButton.title = '日付を選択';

    const icon = document.createElement('img');
    icon.src = '/images/calendar.png';
    icon.alt = '';
    icon.className = 'confirmed-date__icon';
    openButton.append(icon);

    const panel = document.createElement('div');
    panel.className = 'confirmed-date__panel';
    panel.hidden = true;

    const fields = buildConfirmedDateFields(input.value, originalType);
    panel.append(fields.wrap);

    const actions = document.createElement('div');
    actions.className = 'confirmed-date__actions';

    const applyButton = document.createElement('button');
    applyButton.type = 'button';
    applyButton.className = 'button primary small';
    applyButton.textContent = '反映';

    const cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'button small';
    cancelButton.textContent = 'キャンセル';

    actions.append(applyButton, cancelButton);
    panel.append(actions);
    control.append(display, openButton, panel);

    openButton.addEventListener('click', () => {
        refreshConfirmedDateFields(fields, input.value, originalType);
        panel.hidden = ! panel.hidden;
    });

    cancelButton.addEventListener('click', () => {
        panel.hidden = true;
    });

    applyButton.addEventListener('click', () => {
        input.value = composeConfirmedDateValue(fields, originalType);
        display.value = formatConfirmedDateDisplay(input.value, originalType);
        panel.hidden = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));

        if (input.dataset.submitOnApply === 'true') {
            input.form?.requestSubmit();
        }
    });

    return control;
}

function buildConfirmedDateFields(value, originalType) {
    const wrap = document.createElement('div');
    wrap.className = 'confirmed-date__fields';

    const parsed = parseConfirmedDateValue(value, originalType);
    const fields = {
        wrap,
        year: createNumberSelect(2020, 2050, parsed.year),
        month: createNumberSelect(1, 12, parsed.month),
        day: createNumberSelect(1, daysInMonth(parsed.year, parsed.month), parsed.day),
        hour: null,
        minute: null,
    };

    wrap.append(
        createSelectField('年', fields.year),
        createSelectField('月', fields.month),
        createSelectField('日', fields.day),
    );

    fields.year.addEventListener('change', () => syncConfirmedDayOptions(fields));
    fields.month.addEventListener('change', () => syncConfirmedDayOptions(fields));

    if (originalType === 'datetime-local') {
        fields.hour = createNumberSelect(0, 23, parsed.hour);
        fields.minute = createNumberSelect(0, 59, parsed.minute, 5);
        wrap.append(
            createSelectField('時', fields.hour),
            createSelectField('分', fields.minute),
        );
    }

    return fields;
}

function refreshConfirmedDateFields(fields, value, originalType) {
    const parsed = parseConfirmedDateValue(value, originalType);
    fields.year.value = parsed.year;
    fields.month.value = parsed.month;
    syncConfirmedDayOptions(fields, parsed.day);

    if (originalType === 'datetime-local') {
        fields.hour.value = parsed.hour;
        fields.minute.value = Math.floor(parsed.minute / 5) * 5;
    }
}

function createSelectField(text, select) {
    const field = document.createElement('div');
    field.className = 'confirmed-date__field';

    const label = document.createElement('span');
    label.textContent = text;
    field.append(label, select);

    return field;
}

function createNumberSelect(min, max, selected, step = 1) {
    const select = document.createElement('select');

    for (let value = min; value <= max; value += step) {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = String(value).padStart(value < 100 ? 2 : 4, '0');
        option.selected = value === Number(selected);
        select.append(option);
    }

    return select;
}

function syncConfirmedDayOptions(fields, preferredDay = null) {
    const selectedDay = preferredDay || Number(fields.day.value);
    const maxDay = daysInMonth(Number(fields.year.value), Number(fields.month.value));
    fields.day.innerHTML = '';

    for (let day = 1; day <= maxDay; day += 1) {
        const option = document.createElement('option');
        option.value = String(day);
        option.textContent = String(day).padStart(2, '0');
        option.selected = day === Math.min(selectedDay, maxDay);
        fields.day.append(option);
    }
}

function daysInMonth(year, month) {
    return new Date(year, month, 0).getDate();
}

function parseConfirmedDateValue(value, originalType) {
    const now = new Date();
    const fallback = {
        year: now.getFullYear(),
        month: now.getMonth() + 1,
        day: now.getDate(),
        hour: now.getHours(),
        minute: Math.floor(now.getMinutes() / 5) * 5,
    };

    if (! value) {
        return fallback;
    }

    const [date, time = '00:00'] = value.split('T');
    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute] = time.split(':').map(Number);

    return {
        year: year || fallback.year,
        month: month || fallback.month,
        day: day || fallback.day,
        hour: originalType === 'datetime-local' ? (hour || 0) : fallback.hour,
        minute: originalType === 'datetime-local' ? (minute || 0) : fallback.minute,
    };
}

function composeConfirmedDateValue(fields, originalType) {
    const year = fields.year.value;
    const month = fields.month.value.padStart(2, '0');
    const day = fields.day.value.padStart(2, '0');

    if (originalType !== 'datetime-local') {
        return `${year}-${month}-${day}`;
    }

    const hour = fields.hour.value.padStart(2, '0');
    const minute = fields.minute.value.padStart(2, '0');

    return `${year}-${month}-${day}T${hour}:${minute}`;
}

function formatConfirmedDateDisplay(value, originalType) {
    if (! value) {
        return '';
    }

    const [date, time = ''] = value.split('T');
    const displayDate = date.replaceAll('-', '/');

    if (originalType !== 'datetime-local') {
        return displayDate;
    }

    return `${displayDate} ${time}`;
}

function initializeCurrentDateTime(scope) {
    scope.querySelectorAll('[data-current-datetime]').forEach((input) => {
        input.value = formatLocalDateTime(new Date());
    });
}

function formatLocalDateTime(date) {
    const rounded = roundToFiveMinutes(date);
    const year = rounded.getFullYear();
    const month = String(rounded.getMonth() + 1).padStart(2, '0');
    const day = String(rounded.getDate()).padStart(2, '0');
    const hours = String(rounded.getHours()).padStart(2, '0');
    const minutes = String(rounded.getMinutes()).padStart(2, '0');

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
                syncActiveCustomerRowStatus(body);
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

function initializeUserInviteModal() {
    const modal = document.querySelector('[data-user-invite-modal]');
    if (! modal) {
        return;
    }

    document.querySelector('[data-user-invite-open]')?.addEventListener('click', () => {
        openUserInviteModal();
    });

    document.querySelectorAll('[data-user-invite-close]').forEach((button) => {
        button.addEventListener('click', () => {
            closeUserInviteModal();
        });
    });
}

function openUserInviteModal() {
    const modal = document.querySelector('[data-user-invite-modal]');
    if (! modal) {
        return;
    }

    modal.querySelector('[data-user-invite-password]').value = generateInitialPassword();
    modal.hidden = false;
    lockPageScroll();
}

function closeUserInviteModal() {
    const modal = document.querySelector('[data-user-invite-modal]');
    if (! modal) {
        return;
    }

    modal.hidden = true;
    modal.querySelector('[data-user-invite-form]')?.reset();
    unlockPageScroll();
}

function generateInitialPassword() {
    const characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    const bytes = new Uint32Array(12);
    window.crypto.getRandomValues(bytes);

    return Array.from(bytes, (byte) => characters[byte % characters.length]).join('');
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
