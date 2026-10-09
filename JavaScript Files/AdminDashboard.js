document.addEventListener('DOMContentLoaded', function () {
    const apiUrl = document.body.dataset.dashboardApi;
    const messageBox = document.getElementById('dashboardMessage');
    let csrfToken = document.body.dataset.csrfToken || '';

    function showMessage(message, isError) {
        if (!messageBox) {
            return;
        }
        messageBox.textContent = message;
        messageBox.className = `dashboard-message ${isError ? 'error' : 'success'}`;
        messageBox.hidden = !message;
    }

    function addCell(row, value) {
        const cell = document.createElement('td');
        cell.textContent = value == null ? '' : String(value);
        row.append(cell);
        return cell;
    }

    function setEmptyRow(container, columnCount, message) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = columnCount;
        cell.textContent = message;
        row.append(cell);
        container.replaceChildren(row);
    }

    function renderRegistrations(registrations) {
        const body = document.getElementById('recentRegistrations');
        if (!body) {
            return;
        }
        body.replaceChildren();
        if (!registrations.length) {
            setEmptyRow(body, 4, 'No event registrations yet.');
            return;
        }
        registrations.forEach((registration) => {
            const row = document.createElement('tr');
            addCell(row, registration.full_name);
            addCell(row, registration.course_code);
            addCell(row, registration.event_title);
            addCell(row, registration.registered_at);
            body.append(row);
        });
    }

    function renderAuditLogs(logs) {
        const list = document.getElementById('auditLogList');
        if (!list) {
            return;
        }
        list.replaceChildren();
        if (!logs.length) {
            const item = document.createElement('li');
            item.textContent = 'No administrator actions recorded yet.';
            list.append(item);
            return;
        }
        logs.forEach((log) => {
            const item = document.createElement('li');
            const action = document.createElement('strong');
            action.textContent = String(log.action_type).replace(/_/g, ' ');
            const description = document.createElement('span');
            description.textContent = `${log.entity_type} #${log.entity_id} · ${log.actor_email}`;
            const time = document.createElement('time');
            time.textContent = log.created_at;
            item.append(action, description, time);
            list.append(item);
        });
    }

    function renderAccounts(accounts) {
        const body = document.getElementById('studentAccounts');
        if (!body) {
            return;
        }
        body.replaceChildren();
        if (!accounts.length) {
            setEmptyRow(body, 4, 'No student accounts found.');
            return;
        }
        accounts.forEach((account) => {
            const row = document.createElement('tr');
            addCell(row, account.full_name);
            addCell(row, account.email);
            const statusCell = addCell(row, Number(account.is_active) === 1 ? 'Active' : 'Inactive');
            statusCell.className = Number(account.is_active) === 1 ? 'account-active' : 'account-inactive';
            const actionCell = document.createElement('td');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'account-toggle';
            button.dataset.userId = String(account.user_id);
            button.dataset.active = Number(account.is_active) === 1 ? '1' : '0';
            button.textContent = Number(account.is_active) === 1 ? 'Deactivate' : 'Activate';
            actionCell.append(button);
            row.append(actionCell);
            body.append(row);
        });
    }

    async function loadDashboard() {
        const response = await fetch(apiUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        });
        const data = await response.json();
        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                window.location.replace('LoginHome.html');
                return;
            }
            throw new Error(data.error || 'Dashboard data could not be loaded.');
        }
        csrfToken = data.csrfToken || csrfToken;
        document.getElementById('studentCount').textContent = data.stats.students;
        document.getElementById('activeAccountCount').textContent = data.stats.activeStudentAccounts;
        document.getElementById('eventCount').textContent = data.stats.events;
        document.getElementById('registrationCount').textContent = data.stats.registrations;
        renderRegistrations(data.recentRegistrations || []);
        renderAuditLogs(data.auditLogs || []);
        renderAccounts(data.studentAccounts || []);
    }

    document.getElementById('studentAccounts')?.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-user-id]');
        if (!button) {
            return;
        }
        const form = new FormData();
        form.set('action', 'set_account_status');
        form.set('user_id', button.dataset.userId);
        form.set('is_active', button.dataset.active === '1' ? '0' : '1');
        button.disabled = true;
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
                body: form
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || 'Account status could not be updated.');
            }
            showMessage(result.message, false);
            await loadDashboard();
        } catch (error) {
            showMessage(error.message || 'Account status could not be updated.', true);
            button.disabled = false;
        }
    });

    document.getElementById('logoutBtn')?.addEventListener('click', function () {
        window.location.href = 'PHP Files/logout.php';
    });

    loadDashboard().catch((error) => showMessage(error.message || 'Dashboard data could not be loaded.', true));
});
