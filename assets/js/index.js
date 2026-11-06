let flights = JSON.parse(document.querySelector('[data-flight-log-flights]').getAttribute('data-flight-log-flights') || '[]');
let activeFilter = null;
const rows = document.getElementById('flight-rows');
const search = document.getElementById('flight-search');
const activeFilterLabel = document.getElementById('active-filter');
const form = document.getElementById('flight-form');
const toggle = document.getElementById('add-flight-toggle');
const filterPanel = document.getElementById('filter-panel');
const workspace = document.getElementById('flight-workspace');
const filterSidebar = document.getElementById('filter-sidebar');
const activePanelHost = document.getElementById('active-filter-panel');
const filterCards = Array.from(filterPanel.querySelector('.summary-grid').children);
const filterHomes = new Map(filterCards.map((card) => {
    const home = document.createComment('Filter panel position');
    card.before(home);
    return [card, home];
}));

function restoreFilterPanel() {
    const card = activePanelHost.firstElementChild;
    if (card) filterHomes.get(card).after(card);
    filterSidebar.hidden = true;
    workspace.classList.remove('is-filtering');
}

function showFilterPanel(key) {
    const card = filterCards.find((panel) => panel.querySelector(`[data-filter-key="${key}"]`));
    if (!card) return;
    const changed = activePanelHost.firstElementChild !== card || filterPanel.open;
    restoreFilterPanel();
    activePanelHost.append(card);
    filterSidebar.hidden = false;
    workspace.classList.add('is-filtering');
    filterPanel.open = false;
    // Select the matching grouping view when filtering from a flight row.
    const mode = {airport: 'airport', country: 'country', route_display: 'route', route_key: 'leg', manufacturer: 'manufacturer', aircraft_family: 'family', aircraft_type: 'type', body_type: 'body'}[key];
    if (mode) {
        const modeButton = card.querySelector(`[data-grouping-mode="${mode}"]`);
        const visibleView = card.querySelector('[data-grouping-view]:not([hidden])');
        if (modeButton && (!visibleView || !visibleView.querySelector(`[data-filter-key="${key}"][data-filter-value="${CSS.escape(activeFilter.value)}"]`))) modeButton.click();
    }
    if (changed) workspace.scrollIntoView({block: 'start', behavior: 'smooth'});
}

document.getElementById('browse-filters').addEventListener('click', () => {
    restoreFilterPanel();
    filterPanel.open = true;
    filterPanel.scrollIntoView({block: 'start', behavior: 'smooth'});
});
filterPanel.addEventListener('toggle', () => {
    if (filterPanel.open) restoreFilterPanel();
});
const fields = ['date', 'flightnr', 'from', 'to', 'route', 'regnr', 'aircraft', 'seat', 'first_flight', 'msn', 'remarks'];

function text(value) {
    return value == null ? '' : String(value);
}

function appendProviderLink(container, value, providerLink) {
    if (!value || !providerLink || !providerLink.url) {
        container.textContent = text(value);
        return;
    }
    const link = document.createElement('a');
    link.className = 'provider-link';
    link.href = providerLink.url;
    link.textContent = text(value);
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.setAttribute('aria-label', `${text(value)} on ${providerLink.provider} (opens in a new tab)`);
    container.append(link);
}

function setFormOpen(open) {
    form.classList.toggle('hidden', !open);
    toggle.textContent = open ? 'Close form' : 'Add flight';
    if (open) document.getElementById('flight_date').focus();
}

function setFormMode(mode) {
    const edit = mode === 'edit';
    document.getElementById('flight_action').value = edit ? 'edit_flight' : 'add_flight';
    document.getElementById('flight-form-title').textContent = edit ? 'Edit flight' : 'Add flight';
    document.getElementById('flight-submit').textContent = edit ? 'Save changes' : 'Add flight';
    document.getElementById('flight-delete').classList.toggle('hidden-action', !edit);
}

function clearForm() {
    form.reset();
    document.getElementById('original_flightnr').value = '';
    document.getElementById('original_date').value = '';
    setFormMode('add');
}

function editFlight(flight) {
    fields.forEach((field) => {
        const input = document.getElementById(field === 'date' ? 'flight_date' : field);
        if (input) input.value = field === 'date' ? flight.date_display : text(flight[field]);
    });
    document.getElementById('original_flightnr').value = flight.flightnr;
    document.getElementById('original_date').value = flight.date;
    setFormMode('edit');
    setFormOpen(true);
    form.scrollIntoView({block: 'start', behavior: 'smooth'});
}

function matches(flight) {
    const q = search.value.trim().toLowerCase();
    if (activeFilter) {
        if (activeFilter.key === 'airport') {
            if (![flight.from_airport, flight.to_airport, flight.from, flight.to].map(text).includes(activeFilter.value)) return false;
        } else if (activeFilter.key === 'country') {
            if (![flight.from_country, flight.to_country].map(text).includes(activeFilter.value)) return false;
        } else if (activeFilter.key === 'airline_code') {
            if (text(flight.flightnr).slice(0, 2).toUpperCase() !== activeFilter.value.toUpperCase()) return false;
        } else if (activeFilter.key === 'aircraft') {
            if (text(flight.aircraft).trim().toUpperCase() !== activeFilter.value.trim().toUpperCase()) return false;
        } else if (text(flight[activeFilter.key]) !== activeFilter.value) {
            return false;
        }
    }
    if (!q) return true;
    return ['date_display', 'from', 'to', 'from_airport', 'to_airport', 'route_display', 'flightnr', 'regnr', 'airline', 'aircraft', 'seat', 'remarks'].some((key) => text(flight[key]).toLowerCase().includes(q));
}

function applyFilter(key, value, label) {
    activeFilter = {key, value, label};
    updateFilterHistory();
    showFilterPanel(key);
    renderRows();
}

function updateFilterHistory(method = 'pushState') {
    const url = new URL(window.location.href);
    if (activeFilter) {
        url.searchParams.set('filter', activeFilter.key);
        url.searchParams.set('value', activeFilter.value);
    } else {
        url.searchParams.delete('filter');
        url.searchParams.delete('value');
    }
    if (search.value) url.searchParams.set('q', search.value);
    else url.searchParams.delete('q');
    if (url.href !== window.location.href) window.history[method](window.history.state, '', url);
}

function restoreFiltersFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const labels = {
        airport: 'Airports', country: 'Countries', airline: 'Airlines', airline_code: 'Airline code',
        aircraft: 'Aircraft', manufacturer: 'Manufacturers', aircraft_family: 'Aircraft families',
        aircraft_type: 'Aircraft types', body_type: 'Body types', age_bucket: 'Aircraft age', route_display: 'Routes',
        route_key: 'Legs', seat_position: 'Seat position', seat_side: 'Seat side', year: 'Years'
    };
    const key = params.get('filter');
    activeFilter = Object.hasOwn(labels, key) && params.has('value')
        ? {key, value: params.get('value'), label: labels[key]} : null;
    search.value = params.get('q') || '';
    restoreFilterPanel();
    if (activeFilter) showFilterPanel(activeFilter.key);
    renderRows();
}

function renderRows() {
    rows.replaceChildren();
    const matchingFlights = flights.filter(matches);
    matchingFlights.forEach((flight) => {
        const tr = document.createElement('tr');
        if (flight.is_future) tr.className = 'is-future';
        [
            ['date_display', 'mono'],
            ['from', 'mono'],
            ['to', 'mono'],
            ['route_display', 'mono'],
            ['flightnr', 'mono'],
            ['regnr', 'mono'],
            ['airline', ''],
            ['aircraft', ''],
            ['seat', 'mono'],
            ['age', ''],
        ].forEach(([key, className]) => {
            const td = document.createElement('td');
            td.className = className;
            if (key === 'seat' && flight.seat) {
                const position = text(flight.seat_position || 'Unknown');
                const bodyType = text(flight.body_type || 'Unknown').toLowerCase();
                td.title = `${position} on this ${bodyType === 'unknown' ? 'aircraft (type unknown)' : bodyType}`;
            }
            if ((key === 'from' || key === 'to') && flight[key]) {
                const airport = document.createElement('button');
                airport.type = 'button';
                airport.className = 'airport-filter';
                airport.textContent = text(flight[key]);
                airport.title = text(flight[`${key}_airport`] || flight[key]);
                airport.setAttribute('aria-label', `Filter flights by airport ${text(flight[key])}`);
                airport.addEventListener('click', () => applyFilter('airport', text(flight[`${key}_airport`] || flight[key]), 'Airports'));
                td.append(airport);
            } else if ((key === 'airline' || key === 'aircraft') && flight[key]) {
                const filter = document.createElement('button');
                const filterKey = key === 'aircraft' ? 'aircraft_type' : 'airline';
                const filterValue = text(flight[filterKey]);
                const label = key === 'aircraft' ? 'Aircraft types' : 'Airlines';
                filter.type = 'button';
                filter.className = 'table-filter';
                filter.textContent = text(flight[key]);
                filter.setAttribute('aria-label', `Filter flights by ${key === 'aircraft' ? 'aircraft type' : 'airline'} ${filterValue}`);
                filter.addEventListener('click', () => applyFilter(filterKey, filterValue, label));
                td.append(filter);
            } else {
                appendProviderLink(td, flight[key], (flight.provider_links || {})[key]);
            }
            tr.append(td);
        });
        const action = document.createElement('td');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'row-edit';
        button.textContent = 'Edit';
        button.addEventListener('click', () => editFlight(flight));
        action.append(button);
        tr.append(action);
        rows.append(tr);
    });
    activeFilterLabel.textContent = activeFilter ? `${activeFilter.label}: ${activeFilter.value}` : '';
    document.getElementById('filter-results-status').textContent = `${matchingFlights.length} ${matchingFlights.length === 1 ? 'flight' : 'flights'}${activeFilter ? ` · ${activeFilter.label}: ${activeFilter.value}` : ''}${search.value.trim() ? ` · Search: ${search.value.trim()}` : ''}`;
    document.querySelectorAll('[data-filter-key]').forEach((button) => {
        button.setAttribute('aria-pressed', String(Boolean(activeFilter && button.dataset.filterKey === activeFilter.key && button.dataset.filterValue === activeFilter.value)));
    });
}

toggle.addEventListener('click', () => {
    const open = form.classList.contains('hidden');
    if (open) clearForm();
    setFormOpen(open);
});
document.getElementById('flight-form-close').addEventListener('click', () => {
    clearForm();
    setFormOpen(false);
});
document.getElementById('flight-delete').addEventListener('click', (event) => {
    if (!confirm('Delete this flight?')) {
        event.preventDefault();
        return;
    }
    document.getElementById('flight_action').value = 'delete_flight';
});
['airport', 'route', 'aircraft'].forEach((filter) => {
    document.getElementById(`${filter}-filter-open`).addEventListener('click', () => {
        filterPanel.open = true;
        document.getElementById(`${filter}-filter-panel`).scrollIntoView({block: 'nearest', behavior: 'smooth'});
    });
});

['flightnr', 'from', 'to', 'route', 'regnr', 'seat'].forEach((id) => {
    document.getElementById(id).addEventListener('input', (event) => {
        event.target.value = event.target.value.toUpperCase();
    });
});
document.querySelectorAll('[data-grouping-mode]').forEach((button) => {
    const panel = button.closest('[data-grouping-filter]');
    button.addEventListener('click', () => {
        panel.querySelectorAll('[data-grouping-mode]').forEach((mode) => {
            mode.setAttribute('aria-pressed', String(mode === button));
        });
        panel.querySelectorAll('[data-grouping-expand]').forEach((expand) => {
            expand.hidden = expand.dataset.groupingExpand !== button.dataset.groupingMode;
        });
        panel.querySelectorAll('[data-grouping-view]').forEach((view) => {
            view.hidden = view.dataset.groupingView !== button.dataset.groupingMode;
        });
    });
});

document.querySelectorAll('[data-filter-key]').forEach((button) => {
    button.addEventListener('click', (event) => {
        event.preventDefault();
        applyFilter(button.dataset.filterKey, button.dataset.filterValue, button.dataset.filterLabel);
    });
});
document.querySelectorAll('.count-list-toggle').forEach((button) => {
    const list = document.getElementById(button.getAttribute('aria-controls'));
    const hint = button.querySelector('.count-list-hint');
    const collapsedHint = hint.textContent;
    button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(expanded));
        list.classList.toggle('is-expanded', expanded);
        hint.textContent = expanded ? 'Show top 12' : collapsedHint;
    });
});
document.getElementById('clear-filter').addEventListener('click', () => {
    activeFilter = null;
    restoreFilterPanel();
    search.value = '';
    updateFilterHistory();
    renderRows();
});
search.addEventListener('input', () => {
    updateFilterHistory('replaceState');
    renderRows();
});
window.addEventListener('popstate', restoreFiltersFromUrl);
restoreFiltersFromUrl();

(function() {
    const sortFlights = () => {
        flights.sort((a, b) => text(b.date).localeCompare(text(a.date)));
    };
    const refreshAfterFlightAbility = (context) => {
        const output = context && context.result ? context.result : {};
        const ability = context && context.arguments ? context.arguments.ability : '';

        if (ability === 'flight-log/delete-flight') {
            const deletedId = Number(output.id || (output.flight && output.flight.id) || 0);
            if (deletedId) {
                flights = flights.filter((flight) => Number(flight.id) !== deletedId);
                renderRows();
            }
            return;
        }

        const savedFlight = output.result && typeof output.result === 'object' ? output.result : output;
        if (!savedFlight || !savedFlight.id) return;

        const savedId = Number(savedFlight.id);
        const index = flights.findIndex((flight) => Number(flight.id) === savedId);
        if (index >= 0) {
            flights[index] = savedFlight;
        } else {
            flights.unshift(savedFlight);
        }
        sortFlights();
        renderRows();
    };
    ['flight-log/save-flight', 'flight-log/delete-flight'].forEach((ability) => {
        const subscription = {
            criteria: {
                ability,
                success: true
            },
            callback: refreshAfterFlightAbility
        };

        if (window.aiAssistant && typeof window.aiAssistant.onToolCall === 'function') {
            window.aiAssistant.onToolCall(subscription.criteria, subscription.callback);
        } else {
            window.aiAssistantToolCallbacks = window.aiAssistantToolCallbacks || [];
            window.aiAssistantToolCallbacks.push(subscription);
        }
    });
})();
