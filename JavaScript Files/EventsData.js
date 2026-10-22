document.addEventListener('DOMContentLoaded', () => {
    const state = {
        type: 'events',
        query: '',
        sort: 'title',
        page: 1,
        pageSize: 5,
        data: []
    };

    const typeButtons = document.querySelectorAll('.dataset-btn');
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    const pageSizeSelect = document.getElementById('pageSizeSelect');
    const statusBox = document.getElementById('statusMessage');
    const dataGrid = document.getElementById('dataGrid');
    const pagination = document.getElementById('pagination');

    const fileMap = {
        events: 'JSON Files/events.json',
        students: 'PHP Files/api/student_records.php'
    };

    const formatText = (value) => value ? String(value).trim() : '';

    const updateStatus = (message, isError = false) => {
        if (!statusBox) return;
        statusBox.textContent = message;
        statusBox.classList.toggle('error', isError);
    };

    const fetchJson = async (url) => {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`Unable to load ${url}`);
        }
        const result = await response.json();
        return Array.isArray(result) ? result : (result.data || []);
    };

    const getDisplayValue = (item) => {
        if (state.type === 'events') {
            return `${item.title || ''} ${item.date || ''} ${item.venue || ''} ${item.category || ''}`;
        }

        return `${item.name || ''} ${item.course || ''} ${item.year || ''} ${item.city || ''} ${item.cgpa || ''}`;
    };

    const getSortedData = (items) => {
        const cloned = [...items];

        if (state.sort === 'date') {
            cloned.sort((a, b) => new Date(a.date || 0) - new Date(b.date || 0));
            return cloned;
        }

        if (state.sort === 'name') {
            cloned.sort((a, b) => {
                const aText = formatText(a.name || a.title || a.question).toLowerCase();
                const bText = formatText(b.name || b.title || b.question).toLowerCase();
                return aText.localeCompare(bText);
            });
            return cloned;
        }

        return cloned;
    };

    const renderCards = () => {
        const filtered = getSortedData(state.data).filter((item) => {
            const text = getDisplayValue(item).toLowerCase();
            return text.includes(state.query.toLowerCase());
        });

        const totalPages = Math.max(1, Math.ceil(filtered.length / state.pageSize));
        if (state.page > totalPages) state.page = totalPages;

        const startIndex = (state.page - 1) * state.pageSize;
        const pageItems = filtered.slice(startIndex, startIndex + state.pageSize);

        if (!pageItems.length) {
            dataGrid.innerHTML = '<div class="empty-state">No matching records found.</div>';
            pagination.innerHTML = '';
            return;
        }

        dataGrid.innerHTML = pageItems.map((item) => {
            if (state.type === 'events') {
                return `
                    <article class="data-card event-card">
                        <span class="tag">${item.category || 'Event'}</span>
                        <h3>${item.title}</h3>
                        <p><strong>Date:</strong> ${item.date}</p>
                        <p><strong>Venue:</strong> ${item.venue}</p>
                    </article>
                `;
            }

            return `
                <article class="data-card student-card">
                    <span class="tag">${item.course || 'Student'}</span>
                    <h3>${item.name}</h3>
                    <p><strong>Year:</strong> ${item.year}</p>
                    <p><strong>City:</strong> ${item.city}</p>
                    <p><strong>CGPA:</strong> ${item.cgpa}</p>
                </article>
            `;
        }).join('');

        const pageNumbers = Array.from({ length: totalPages }, (_, index) => index + 1);
        pagination.innerHTML = pageNumbers.map((page) => `
            <button class="page-btn ${page === state.page ? 'active' : ''}" data-page="${page}">${page}</button>
        `).join('');

        pagination.querySelectorAll('.page-btn').forEach((button) => {
            button.addEventListener('click', () => {
                state.page = Number(button.dataset.page);
                renderCards();
            });
        });
    };

    const loadData = async () => {
        try {
            updateStatus('Loading data...');
            const items = await fetchJson(fileMap[state.type]);
            state.data = items.map((item) => state.type === 'students'
                ? { ...item, name: item.full_name, year: item.year_of_study }
                : item);
            state.page = 1;
            renderCards();
            updateStatus(`${state.data.length} records loaded.`);
        } catch (error) {
            dataGrid.innerHTML = '<div class="empty-state error-box">Unable to load data. Check your sign-in and database connection.</div>';
            pagination.innerHTML = '';
            updateStatus(error.message, true);
        }
    };

    typeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.type = button.dataset.type;
            typeButtons.forEach((item) => item.classList.toggle('active', item === button));
            loadData();
        });
    });

    searchInput.addEventListener('input', (event) => {
        state.query = event.target.value.trim();
        state.page = 1;
        renderCards();
    });

    sortSelect.addEventListener('change', (event) => {
        state.sort = event.target.value;
        state.page = 1;
        renderCards();
    });

    pageSizeSelect.addEventListener('change', (event) => {
        state.pageSize = Number(event.target.value);
        state.page = 1;
        renderCards();
    });

    loadData();
});
