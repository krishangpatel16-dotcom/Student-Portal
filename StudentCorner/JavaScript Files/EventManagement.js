document.addEventListener('DOMContentLoaded', function () {
    const apiUrl = document.body.dataset.eventsApi;
    const eventForm = document.getElementById('eventForm');
    const filtersForm = document.getElementById('eventFilters');
    const eventList = document.getElementById('eventList');
    const emptyState = document.querySelector('[data-events-empty]');
    const loadingMessage = document.getElementById('eventsLoading');
    const notice = document.querySelector('[data-event-notice]');
    const formTitle = document.getElementById('event-form-title');
    const submitButton = eventForm?.querySelector('button[type="submit"]');
    const cancelButton = document.querySelector('[data-cancel-edit]');
    const posterInput = document.querySelector('[data-poster-input]');
    const posterPreview = document.querySelector('[data-poster-preview]');
    const posterStatus = document.querySelector('[data-poster-status]');
    const eventCache = new Map();
    let csrfToken = '';
    let searchTimer;
    let previewUrl = '';

    if (!apiUrl || !eventForm || !filtersForm || !eventList || !emptyState) {
        return;
    }

    function showNotice(message, type) {
        if (!notice) {
            return;
        }
        notice.textContent = message;
        notice.className = `notice ${type}`;
        notice.hidden = !message;
    }

    async function readJson(response) {
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.error || 'The event request failed.');
        }
        return result;
    }

    function currentFilters() {
        return new URLSearchParams(new FormData(filtersForm));
    }

    function createPoster(event) {
        if (event.posterUrl) {
            const image = document.createElement('img');
            image.className = 'event-poster';
            image.src = event.posterUrl;
            image.alt = `Poster for ${event.title}`;
            return image;
        }
        const placeholder = document.createElement('div');
        placeholder.className = 'poster-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.textContent = 'Campus event';
        return placeholder;
    }

    function renderEvents(events) {
        eventCache.clear();
        eventList.replaceChildren();
        emptyState.hidden = events.length !== 0;

        events.forEach((event) => {
            eventCache.set(String(event.eventId), event);
            const card = document.createElement('article');
            card.className = 'event-card';
            card.append(createPoster(event));

            const details = document.createElement('div');
            details.className = 'event-details';
            const titleRow = document.createElement('div');
            titleRow.className = 'event-title-row';
            const title = document.createElement('h3');
            title.textContent = event.title;
            const badge = document.createElement('span');
            badge.className = `event-status ${event.status.toLowerCase()}`;
            badge.textContent = event.status;
            titleRow.append(title, badge);
            const meta = document.createElement('p');
            meta.textContent = `${event.category} · ${event.venue}`;
            const date = document.createElement('time');
            date.dateTime = event.eventDate;
            date.textContent = event.eventDate;
            details.append(titleRow, meta, date);

            const actions = document.createElement('div');
            actions.className = 'event-actions';
            const edit = document.createElement('button');
            edit.className = 'button button-muted';
            edit.type = 'button';
            edit.dataset.editEvent = String(event.eventId);
            edit.textContent = 'Edit';
            const deleteForm = document.createElement('form');
            deleteForm.dataset.deleteEventForm = '';
            const deleteButton = document.createElement('button');
            deleteButton.className = 'button button-danger';
            deleteButton.type = 'submit';
            deleteButton.textContent = 'Delete';
            deleteForm.append(deleteButton);
            deleteForm.dataset.eventId = String(event.eventId);
            deleteForm.dataset.eventTitle = event.title;
            actions.append(edit, deleteForm);
            card.append(details, actions);
            eventList.append(card);
        });

        const count = document.getElementById('eventCount');
        if (count) {
            count.textContent = `${events.length} matching records`;
        }
    }

    async function loadEvents() {
        if (loadingMessage) {
            loadingMessage.hidden = false;
        }
        const query = currentFilters();
        const queryString = query.toString();
        try {
            const response = await fetch(`${apiUrl}${queryString ? `?${queryString}` : ''}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            });
            const result = await readJson(response);
            csrfToken = result.csrfToken || csrfToken;
            renderEvents(result.events || []);
        } finally {
            if (loadingMessage) {
                loadingMessage.hidden = true;
            }
        }
    }

    function clearForm() {
        eventForm.reset();
        eventForm.elements.event_id.value = '0';
        if (formTitle) {
            formTitle.textContent = 'Add an event';
        }
        if (submitButton) {
            submitButton.textContent = 'Create event';
        }
        if (cancelButton) {
            cancelButton.hidden = true;
        }
        document.querySelector('.current-poster')?.remove();
        if (posterPreview) {
            posterPreview.hidden = true;
        }
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = '';
        }
        if (posterStatus) {
            posterStatus.textContent = 'Choose an image no larger than 2 MB.';
        }
    }

    function editEvent(event) {
        eventForm.elements.event_id.value = String(event.eventId);
        eventForm.elements.title.value = event.title;
        eventForm.elements.event_date.value = event.eventDate;
        eventForm.elements.venue.value = event.venue;
        eventForm.elements.category.value = event.category;
        eventForm.elements.event_status.value = event.status;
        if (posterInput) {
            posterInput.value = '';
        }
        if (formTitle) {
            formTitle.textContent = 'Edit event';
        }
        if (submitButton) {
            submitButton.textContent = 'Save changes';
        }
        if (cancelButton) {
            cancelButton.hidden = false;
        }

        document.querySelector('.current-poster')?.remove();
        if (event.posterUrl) {
            const currentPoster = document.createElement('div');
            currentPoster.className = 'current-poster';
            const label = document.createElement('span');
            label.textContent = 'Current poster';
            const image = document.createElement('img');
            image.src = event.posterUrl;
            image.alt = `Current poster for ${event.title}`;
            currentPoster.append(label, image);
            posterPreview?.before(currentPoster);
        }
        eventForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    eventForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!eventForm.reportValidity()) {
            return;
        }

        const payload = new FormData(eventForm);
        const eventId = Number(eventForm.elements.event_id.value);
        payload.set('action', eventId > 0 ? 'update' : 'create');
        payload.set('csrf_token', csrfToken);
        if (submitButton) {
            submitButton.disabled = true;
        }
        showNotice('Saving event…', 'success');

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
                body: payload
            });
            const result = await readJson(response);
            showNotice(result.message, 'success');
            clearForm();
            await loadEvents();
        } catch (error) {
            showNotice(error.message || 'The event could not be saved.', 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    filtersForm.addEventListener('submit', function (event) {
        event.preventDefault();
        loadEvents().catch((error) => showNotice(error.message, 'error'));
    });

    filtersForm.elements.q.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            loadEvents().catch((error) => showNotice(error.message, 'error'));
        }, 300);
    });

    ['category', 'status'].forEach((fieldName) => {
        filtersForm.elements[fieldName].addEventListener('change', function () {
            loadEvents().catch((error) => showNotice(error.message, 'error'));
        });
    });

    filtersForm.querySelector('[data-reset-filters]')?.addEventListener('click', function (event) {
        event.preventDefault();
        filtersForm.reset();
        loadEvents().catch((error) => showNotice(error.message, 'error'));
    });

    eventList.addEventListener('click', function (event) {
        const editButton = event.target.closest('[data-edit-event]');
        if (!editButton) {
            return;
        }
        const record = eventCache.get(editButton.dataset.editEvent);
        if (record) {
            editEvent(record);
        }
    });

    eventList.addEventListener('submit', async function (event) {
        const deleteForm = event.target.closest('[data-delete-event-form]');
        if (!deleteForm) {
            return;
        }
        event.preventDefault();
        if (!window.confirm(`Delete "${deleteForm.dataset.eventTitle}"? This also removes registrations for the event.`)) {
            return;
        }

        const payload = new FormData();
        payload.set('action', 'delete');
        payload.set('event_id', deleteForm.dataset.eventId);
        payload.set('csrf_token', csrfToken);
        try {
            showNotice('Deleting event…', 'success');
            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
                body: payload
            });
            const result = await readJson(response);
            showNotice(result.message, 'success');
            await loadEvents();
        } catch (error) {
            showNotice(error.message || 'The event could not be deleted.', 'error');
        }
    });

    cancelButton?.addEventListener('click', clearForm);

    posterInput?.addEventListener('change', function () {
        const file = posterInput.files && posterInput.files[0];
        if (!file) {
            if (posterPreview) {
                posterPreview.hidden = true;
            }
            if (posterStatus) {
                posterStatus.textContent = 'Choose an image no larger than 2 MB.';
            }
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024) {
            posterInput.value = '';
            if (posterPreview) {
                posterPreview.hidden = true;
            }
            if (posterStatus) {
                posterStatus.textContent = 'Choose a JPEG, PNG, or WebP image no larger than 2 MB.';
            }
            return;
        }

        const image = posterPreview?.querySelector('img');
        if (image && posterPreview) {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
            previewUrl = URL.createObjectURL(file);
            image.src = previewUrl;
            posterPreview.hidden = false;
        }
        if (posterStatus) {
            posterStatus.textContent = `${file.name} selected`;
        }
    });

    if (Number(eventForm.elements.event_id.value) > 0) {
        if (formTitle) {
            formTitle.textContent = 'Edit event';
        }
        if (submitButton) {
            submitButton.textContent = 'Save changes';
        }
        if (cancelButton) {
            cancelButton.hidden = false;
        }
    }

    loadEvents().catch((error) => showNotice(error.message || 'Events could not be loaded.', 'error'));
});
