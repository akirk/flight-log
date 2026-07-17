<?php
$app = \FlightTracker\App::get_instance();
if ( ! $app ) {
    wp_die( esc_html__( 'Flight Tracker is not initialized.', 'flight-tracker' ) );
}

$data = $app->get_dashboard_data();
$flights = $data['flights'];
$summary = $data['summary'];
$form = $data['form'];
$values = $form['values'];

$json_flights = array_map(
    static function( array $flight ): array {
        unset( $flight['date_obj'] );
        return $flight;
    },
    $flights
);

$render_count_list = static function( string $title, array $counts, string $filter_key ): void {
    ?>
    <section class="panel summary-panel">
        <h2><?php echo esc_html( $title ); ?></h2>
        <div class="count-list">
            <?php foreach ( array_slice( $counts, 0, 12, true ) as $name => $count ) : ?>
                <button type="button" class="count-row" data-filter-key="<?php echo esc_attr( $filter_key ); ?>" data-filter-value="<?php echo esc_attr( $name ); ?>">
                    <span><?php echo esc_html( $name ); ?></span>
                    <strong><?php echo esc_html( (string) $count ); ?></strong>
                </button>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
};
?><!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_title(); ?></title>
    <?php wp_app_head(); ?>
    <style>
        :root {
            --bg: #f4f6f8;
            --surface: #fff;
            --surface-soft: #f8fafc;
            --text: #1f2933;
            --muted: #667085;
            --border: #d9dee7;
            --accent: #0f766e;
            --accent-soft: #dff6ef;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        button, input, textarea { font: inherit; }
        .app-shell { width: min(1440px, calc(100% - 28px)); margin: 0 auto; padding: 26px 0 40px; }
        .page-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .eyebrow { margin: 0 0 4px; color: var(--accent); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        h1 { margin: 0; font-size: 38px; line-height: 1; }
        h2 { margin: 0 0 12px; font-size: 15px; line-height: 1.2; }
        .header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .metric { display: inline-flex; align-items: baseline; gap: 6px; min-height: 38px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); padding: 8px 10px; color: var(--muted); font-weight: 700; }
        .metric strong { color: var(--text); font-size: 18px; line-height: 1; }
        .button { min-height: 38px; border: 1px solid var(--accent); border-radius: 6px; background: var(--accent); color: #fff; cursor: pointer; font-weight: 800; padding: 8px 12px; }
        .button.secondary { background: #fff; color: var(--accent); }
        .button:hover, .button:focus { box-shadow: 0 0 0 3px rgba(15, 118, 110, .16); outline: 0; }
        .notice { margin: 0 0 14px; border: 1px solid #a7f3d0; border-radius: 6px; background: #ecfdf5; color: #064e3b; padding: 10px 12px; font-weight: 700; }
        .notice.error { border-color: #fecaca; background: #fff1f0; color: var(--danger); }
        .notice ul { margin: 6px 0 0 18px; padding: 0; }
        .flight-form { margin: 0 0 18px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); padding: 16px; }
        .flight-form.hidden { display: none; }
        .form-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .form-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; }
        .field { display: flex; min-width: 0; flex-direction: column; gap: 6px; }
        .field-wide { grid-column: span 2; }
        .field-remarks { grid-column: span 6; }
        label { color: var(--muted); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        input, textarea { width: 100%; border: 1px solid var(--border); border-radius: 6px; background: #fff; color: var(--text); padding: 9px 10px; }
        textarea { min-height: 76px; resize: vertical; }
        input:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(15, 118, 110, .15); outline: 0; }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
        .overview { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
        .stat { border: 1px solid var(--border); border-radius: 8px; background: var(--surface); padding: 13px; }
        .stat span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .stat strong { display: block; margin-top: 5px; font-size: 24px; line-height: 1.1; }
        .toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0 0 14px; }
        .toolbar input { flex: 1 1 260px; max-width: 420px; }
        .active-filter { color: var(--muted); font-weight: 800; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
        .panel { border: 1px solid var(--border); border-radius: 8px; background: var(--surface); padding: 13px; min-width: 0; }
        .count-list { display: grid; gap: 4px; }
        .count-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; width: 100%; border: 0; border-radius: 5px; background: transparent; color: var(--text); cursor: pointer; padding: 6px 7px; text-align: left; }
        .count-row:hover, .count-row:focus { background: var(--accent-soft); outline: 0; }
        .count-row span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .count-row strong { font-variant-numeric: tabular-nums; }
        .table-panel { overflow: hidden; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
        .table-scroll { overflow: auto; max-height: calc(100vh - 220px); }
        table { width: 100%; min-width: 1040px; border-collapse: separate; border-spacing: 0; }
        th, td { border: 0; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 8px 10px; text-align: left; vertical-align: top; }
        th { position: sticky; top: 0; z-index: 1; background: var(--surface-soft); color: #475467; font-size: 12px; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
        th:last-child, td:last-child { border-right: 0; }
        tbody tr:nth-child(even) td { background: #fbfcfe; }
        tbody tr.is-future td { color: #98a2b3; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; white-space: nowrap; }
        .row-edit { min-height: 28px; border: 1px solid var(--border); border-radius: 6px; background: #fff; color: var(--accent); cursor: pointer; font-weight: 800; padding: 4px 8px; }
        .empty { padding: 30px; color: var(--muted); text-align: center; }
        @media (max-width: 900px) {
            .page-header { align-items: flex-start; flex-direction: column; }
            .overview, .summary-grid { grid-template-columns: 1fr 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .field-wide, .field-remarks { grid-column: span 1; }
        }
        @media (max-width: 620px) {
            .overview, .summary-grid { grid-template-columns: 1fr; }
            h1 { font-size: 32px; }
        }
    </style>
</head>
<body>
<?php wp_app_body_open(); ?>
<main class="app-shell">
    <header class="page-header">
        <div>
            <p class="eyebrow">Flight log</p>
            <h1>Flights</h1>
        </div>
        <div class="header-actions">
            <span class="metric"><strong><?php echo esc_html( (string) $summary['total'] ); ?></strong> total</span>
            <span class="metric"><strong><?php echo esc_html( (string) $summary['planned'] ); ?></strong> planned</span>
            <button type="button" class="button" id="add-flight-toggle"><?php echo $form['show_form'] ? esc_html__( 'Close form', 'flight-tracker' ) : esc_html__( 'Add flight', 'flight-tracker' ); ?></button>
        </div>
    </header>

    <?php if ( $form['flash'] ) : ?>
        <div class="notice"><?php echo esc_html( $form['flash'] ); ?></div>
    <?php endif; ?>

    <?php if ( $form['errors'] ) : ?>
        <div class="notice error">
            <?php echo esc_html( 'Could not save flight.' ); ?>
            <ul>
                <?php foreach ( $form['errors'] as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="flight-form" class="flight-form<?php echo $form['show_form'] ? '' : ' hidden'; ?>" method="post" autocomplete="off">
        <?php wp_nonce_field( \FlightTracker\App::NONCE_ACTION, \FlightTracker\App::NONCE_NAME ); ?>
        <input type="hidden" id="flight_action" name="action" value="<?php echo esc_attr( 'edit' === $form['mode'] ? 'edit_flight' : 'add_flight' ); ?>">
        <input type="hidden" id="original_flightnr" name="original_flightnr" value="<?php echo esc_attr( $form['original_flightnr'] ); ?>">
        <input type="hidden" id="original_date" name="original_date" value="<?php echo esc_attr( $form['original_date'] ); ?>">
        <div class="form-header">
            <h2 id="flight-form-title"><?php echo esc_html( 'edit' === $form['mode'] ? 'Edit flight' : 'Add flight' ); ?></h2>
            <button type="button" class="button secondary" id="flight-form-close">Close</button>
        </div>
        <div class="form-grid">
            <div class="field field-wide"><label for="flight_date">Date and time</label><input id="flight_date" name="date" type="datetime-local" step="1" required value="<?php echo esc_attr( str_replace( ' ', 'T', $values['date'] ) ); ?>"></div>
            <div class="field"><label for="flightnr">Flight #</label><input id="flightnr" name="flightnr" maxlength="8" required value="<?php echo esc_attr( $values['flightnr'] ); ?>"></div>
            <div class="field"><label for="from">From</label><input id="from" name="from" maxlength="10" required value="<?php echo esc_attr( $values['from'] ); ?>"></div>
            <div class="field"><label for="to">To</label><input id="to" name="to" maxlength="10" required value="<?php echo esc_attr( $values['to'] ); ?>"></div>
            <div class="field"><label for="seat">Seat</label><input id="seat" name="seat" maxlength="3" value="<?php echo esc_attr( $values['seat'] ); ?>"></div>
            <div class="field field-wide"><label for="route">Route</label><input id="route" name="route" maxlength="20" value="<?php echo esc_attr( $values['route'] ); ?>"></div>
            <div class="field"><label for="regnr">Reg</label><input id="regnr" name="regnr" maxlength="8" value="<?php echo esc_attr( $values['regnr'] ); ?>"></div>
            <div class="field field-wide"><label for="aircraft">Aircraft</label><input id="aircraft" name="aircraft" maxlength="100" value="<?php echo esc_attr( $values['aircraft'] ); ?>"></div>
            <div class="field"><label for="first_flight">First flight</label><input id="first_flight" name="first_flight" type="date" value="<?php echo esc_attr( $values['first_flight'] ); ?>"></div>
            <div class="field"><label for="msn">MSN</label><input id="msn" name="msn" type="number" min="0" value="<?php echo esc_attr( $values['msn'] ); ?>"></div>
            <div class="field field-remarks"><label for="remarks">Remarks</label><textarea id="remarks" name="remarks"><?php echo esc_textarea( $values['remarks'] ); ?></textarea></div>
        </div>
        <div class="form-actions">
            <button type="submit" class="button" id="flight-submit"><?php echo esc_html( 'edit' === $form['mode'] ? 'Save changes' : 'Add flight' ); ?></button>
        </div>
    </form>

    <section class="overview">
        <div class="stat"><span>Logged flights</span><strong><?php echo esc_html( (string) $summary['logged'] ); ?></strong></div>
        <div class="stat"><span>Airports</span><strong><?php echo esc_html( (string) count( $summary['airports'] ) ); ?></strong></div>
        <div class="stat"><span>Routes</span><strong><?php echo esc_html( (string) count( $summary['routes'] ) ); ?></strong></div>
        <div class="stat"><span>Aircraft</span><strong><?php echo esc_html( (string) count( $summary['aircraft'] ) ); ?></strong></div>
    </section>

    <div class="toolbar">
        <input id="flight-search" type="search" placeholder="Search flights">
        <button type="button" class="button secondary" id="clear-filter">Reset</button>
        <span class="active-filter" id="active-filter"></span>
    </div>

    <section class="summary-grid">
        <?php $render_count_list( 'Airlines', $summary['airlines'], 'airline' ); ?>
        <?php $render_count_list( 'Airports', $summary['airports'], 'airport' ); ?>
        <?php $render_count_list( 'Routes', $summary['routes'], 'route_key' ); ?>
        <?php $render_count_list( 'Aircraft types', $summary['types'], 'aircraft_type' ); ?>
        <?php $render_count_list( 'Body types', $summary['body_types'], 'body_type' ); ?>
        <?php $render_count_list( 'Years', $summary['years'], 'year' ); ?>
    </section>

    <section class="table-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Route</th>
                        <th>Flight #</th>
                        <th>Reg</th>
                        <th>Airline</th>
                        <th>Aircraft</th>
                        <th>Seat</th>
                        <th>Age</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody id="flight-rows"></tbody>
            </table>
            <?php if ( ! $flights ) : ?>
                <div class="empty">No flights yet.</div>
            <?php endif; ?>
        </div>
    </section>
</main>
<script>
const flights = <?php echo wp_json_encode( $json_flights ); ?>;
let activeFilter = null;
const rows = document.getElementById('flight-rows');
const search = document.getElementById('flight-search');
const activeFilterLabel = document.getElementById('active-filter');
const form = document.getElementById('flight-form');
const toggle = document.getElementById('add-flight-toggle');
const fields = ['date', 'flightnr', 'from', 'to', 'route', 'regnr', 'aircraft', 'seat', 'first_flight', 'msn', 'remarks'];

function text(value) {
    return value == null ? '' : String(value);
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
        if (input) input.value = field === 'date' ? flight.date_local : text(flight[field]);
    });
    document.getElementById('original_flightnr').value = flight.flightnr;
    document.getElementById('original_date').value = flight.date;
    setFormMode('edit');
    setFormOpen(true);
    form.scrollIntoView({block: 'start', behavior: 'smooth'});
}

function matches(flight) {
    const q = search.value.trim().toLowerCase();
    if (activeFilter && text(flight[activeFilter.key]) !== activeFilter.value) return false;
    if (!q) return true;
    return ['date_display', 'from', 'to', 'route_display', 'flightnr', 'regnr', 'airline', 'aircraft', 'seat', 'remarks'].some((key) => text(flight[key]).toLowerCase().includes(q));
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
            td.textContent = text(flight[key]);
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
['flightnr', 'from', 'to', 'route', 'regnr', 'seat'].forEach((id) => {
    document.getElementById(id).addEventListener('input', (event) => {
        event.target.value = event.target.value.toUpperCase();
    });
});
document.querySelectorAll('[data-filter-key]').forEach((button) => {
    button.addEventListener('click', () => {
        activeFilter = {
            key: button.dataset.filterKey,
            value: button.dataset.filterValue,
            label: button.closest('.summary-panel').querySelector('h2').textContent
        };
        renderRows();
    });
});
document.getElementById('clear-filter').addEventListener('click', () => {
    activeFilter = null;
    search.value = '';
    renderRows();
});
search.addEventListener('input', renderRows);
renderRows();
</script>
<?php wp_app_body_close(); ?>
</body>
</html>
