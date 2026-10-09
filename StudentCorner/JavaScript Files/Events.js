document.addEventListener('DOMContentLoaded', async function () {
    const rows = document.getElementById('eventRows');
    const status = document.getElementById('eventLoadStatus');
    if (!rows || !status) {
        return;
    }

    try {
        const response = await fetch('PHP Files/api/events.php', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Upcoming events could not be loaded.');
        }

        rows.replaceChildren();
        if (!data.events.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.textContent = 'There are no open events at this time.';
            row.append(cell);
            rows.append(row);
            return;
        }

        data.events.forEach(function (event) {
            const row = document.createElement('tr');
            [event.eventDate, event.title, event.venue, event.category, event.status].forEach(function (value) {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.append(cell);
            });
            rows.append(row);
        });
    } catch (error) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.textContent = 'Upcoming events could not be loaded. Please check the database connection.';
        row.append(cell);
        rows.replaceChildren(row);
        status.textContent = error.message || 'Event service unavailable.';
    }
});
