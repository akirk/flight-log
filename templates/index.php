<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$app = \FlightLog\App::get_instance();
if ( ! $app ) {
    wp_die( esc_html__( 'Flight Log is not initialized.', 'flight-log' ) );
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

$render_count_list = static function( string $title, array $counts, string $filter_key, string $id = '' ): void {
    ?>
    <section class="panel summary-panel"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>>
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
    <title><?php wp_app_the_title( 'Flight Log' ); ?></title>
    <?php
    $flight_log_style_path  = dirname( __DIR__ ) . '/assets/css/index.css';
    $flight_log_script_path = dirname( __DIR__ ) . '/assets/js/index.js';
    wp_app_enqueue_style(
        'flight-log-index',
        plugins_url( 'assets/css/index.css', dirname( __DIR__ ) . '/flight-log.php' ),
        [],
        file_exists( $flight_log_style_path ) ? (string) filemtime( $flight_log_style_path ) : false,
        'flight-log'
    );
    wp_app_enqueue_script(
        'flight-log-index',
        plugins_url( 'assets/js/index.js', dirname( __DIR__ ) . '/flight-log.php' ),
        [],
        file_exists( $flight_log_script_path ) ? (string) filemtime( $flight_log_script_path ) : false,
        true,
        'flight-log'
    );
    ?>
    <?php wp_app_head(); ?>
</head>
<body>
<?php wp_app_body_open(); ?>
<main class="app-shell" data-flight-log-flights="<?php echo esc_attr( wp_json_encode( $json_flights, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?>">
    <header class="page-header">
        <div>
            <p class="eyebrow">Flight log</p>
            <h1>Flights</h1>
        </div>
        <div class="header-actions">
            <span class="metric"><strong><?php echo esc_html( (string) $summary['total'] ); ?></strong> total</span>
            <span class="metric"><strong><?php echo esc_html( (string) $summary['planned'] ); ?></strong> planned</span>
            <button type="button" class="button" id="add-flight-toggle"><?php echo $form['show_form'] ? esc_html__( 'Close form', 'flight-log' ) : esc_html__( 'Add flight', 'flight-log' ); ?></button>
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

    <form id="flight-form" class="flight-form<?php echo $form['show_form'] ? '' : ' hidden'; ?>" method="post" autocomplete="off" aria-labelledby="flight-form-title">
        <?php wp_nonce_field( \FlightLog\App::NONCE_ACTION, \FlightLog\App::NONCE_NAME ); ?>
        <input type="hidden" id="flight_action" name="action" value="<?php echo esc_attr( 'edit' === $form['mode'] ? 'edit_flight' : 'add_flight' ); ?>">
        <input type="hidden" id="original_flightnr" name="original_flightnr" value="<?php echo esc_attr( $form['original_flightnr'] ); ?>">
        <input type="hidden" id="original_date" name="original_date" value="<?php echo esc_attr( $form['original_date'] ); ?>">
        <div class="form-header">
            <h2 id="flight-form-title"><?php echo esc_html( 'edit' === $form['mode'] ? 'Edit flight' : 'Add flight' ); ?></h2>
            <button type="button" class="button secondary" id="flight-form-close">Close</button>
        </div>
        <div class="form-grid">
            <div class="field field-wide"><label for="flight_date">Date and time</label><input id="flight_date" name="date" type="text" required placeholder="today 9:30, 17.7.26 930, 2026-07-17 09:30" value="<?php echo esc_attr( $values['date'] ); ?>"></div>
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
            <button type="submit" class="button danger<?php echo 'edit' === $form['mode'] ? '' : ' hidden-action'; ?>" id="flight-delete" formnovalidate>Delete flight</button>
            <button type="submit" class="button" id="flight-submit"><?php echo esc_html( 'edit' === $form['mode'] ? 'Save changes' : 'Add flight' ); ?></button>
        </div>
    </form>

    <section class="overview" aria-label="Flight log summary" data-ai-assistant-important>
        <div class="stat"><span>Logged flights</span><strong><?php echo esc_html( (string) $summary['logged'] ); ?></strong></div>
        <button type="button" class="stat stat-button" id="airport-filter-open"><span>Airports</span><strong><?php echo esc_html( (string) count( $summary['airports'] ) ); ?></strong></button>
        <div class="stat"><span>Routes</span><strong><?php echo esc_html( (string) count( $summary['routes'] ) ); ?></strong></div>
        <div class="stat"><span>Aircraft</span><strong><?php echo esc_html( (string) count( $summary['aircraft'] ) ); ?></strong></div>
    </section>

    <div class="toolbar">
        <input id="flight-search" type="search" placeholder="Search flights">
        <button type="button" class="button secondary" id="clear-filter">Reset</button>
        <span class="active-filter" id="active-filter"></span>
    </div>

    <details class="filter-panel" id="filter-panel">
        <summary>Filters</summary>
        <section class="summary-grid">
            <?php $render_count_list( 'Airlines', $summary['airlines'], 'airline' ); ?>
            <?php $render_count_list( 'Airports', $summary['airports'], 'airport', 'airport-filter-panel' ); ?>
            <?php $render_count_list( 'Routes', $summary['routes'], 'route_key' ); ?>
            <?php $render_count_list( 'Aircraft types', $summary['types'], 'aircraft_type' ); ?>
            <?php $render_count_list( 'Body types', $summary['body_types'], 'body_type' ); ?>
            <?php $render_count_list( 'Years', $summary['years'], 'year' ); ?>
        </section>
    </details>

    <section class="table-panel">
        <div class="table-scroll">
            <table>
                <caption class="screen-reader-text">Flights</caption>
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
<?php wp_app_body_close(); ?>
</body>
</html>
