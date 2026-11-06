let flights = JSON.parse(document.querySelector('[data-flight-log-flights]').getAttribute('data-flight-log-flights') || '[]');
let activeFilter = null;
const rows = document.getElementById('flight-rows');
const search = document.getElementById('flight-search');
const activeFilterLabel = document.getElementById('active-filter');
const form = document.getElementById('flight-form');
const toggle = document.getElementById('add-flight-toggle');
const filterPanel = document.getElementById('filter-panel');
const airportFilterOpen = document.getElementById('airport-filter-open');
const airportFilterPanel = document.getElementById('airport-filter-panel');
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
        } else if (text(flight[activeFilter.key]) !== activeFilter.value) {
            return false;
        }
    }
    if (!q) return true;
    return ['date_display', 'from', 'to', 'from_airport', 'to_airport', 'route_display', 'flightnr', 'regnr', 'airline', 'aircraft', 'seat', 'remarks'].some((key) => text(flight[key]).toLowerCase().includes(q));
}

function applyFilter(key, value, label) {
    activeFilter = {key, value, label};
    renderRows();
}

function renderRows() {
    rows.replaceChildren();
    flights.filter(matches).forEach((flight) => {
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
            if ((key === 'from' || key === 'to') && flight[key]) {
                const airport = document.createElement('button');
                airport.type = 'button';
                airport.className = 'airport-filter';
                airport.textContent = text(flight[key]);
                airport.title = text(flight[`${key}_airport`] || flight[key]);
                airport.setAttribute('aria-label', `Filter flights by airport ${text(flight[key])}`);
                airport.addEventListener('click', () => applyFilter('airport', text(flight[`${key}_airport`] || flight[key]), 'Airports'));
                td.append(airport);
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
airportFilterOpen.addEventListener('click', () => {
    filterPanel.open = true;
    airportFilterPanel.scrollIntoView({block: 'nearest', behavior: 'smooth'});
});

['flightnr', 'from', 'to', 'route', 'regnr', 'seat'].forEach((id) => {
    document.getElementById(id).addEventListener('input', (event) => {
        event.target.value = event.target.value.toUpperCase();
    });
});
document.querySelectorAll('[data-filter-key]').forEach((button) => {
    button.addEventListener('click', () => {
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
    search.value = '';
    renderRows();
});
search.addEventListener('input', renderRows);
renderRows();

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
