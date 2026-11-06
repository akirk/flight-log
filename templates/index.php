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

$airports_by_country = [];
foreach ( $flights as $flight ) {
    foreach ( [ 'from', 'to' ] as $endpoint ) {
        $country = $flight[ $endpoint . '_country' ];
        $airport = $flight[ $endpoint . '_airport' ];
        $airports_by_country[ $country ][ $airport ] = $summary['airports'][ $airport ];
    }
}
foreach ( $airports_by_country as &$country_airports ) {
    arsort( $country_airports );
}
unset( $country_airports );

$json_flights = array_map(
    static function( array $flight ): array {
        unset( $flight['date_obj'] );
        return $flight;
    },
    $flights
);

$render_count_list = static function( string $title, array $counts, string $filter_key, string $id = '', bool $show_heading = true, array $display_names = [] ): void {
    ?>
    <section class="panel summary-panel"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>>
        <?php if ( $show_heading ) : ?>
        <h2>
            <?php if ( count( $counts ) > 12 ) : ?>
                <button type="button" class="count-list-toggle" aria-expanded="false" aria-controls="count-list-<?php echo esc_attr( $filter_key ); ?>">
                    <?php echo esc_html( $title ); ?>
                    <span class="count-list-hint" aria-hidden="true">Show all (<?php echo esc_html( (string) count( $counts ) ); ?>)</span>
                </button>
            <?php else : ?>
                <?php echo esc_html( $title ); ?>
            <?php endif; ?>
        </h2>
        <?php endif; ?>
        <div class="count-list" id="count-list-<?php echo esc_attr( $filter_key ); ?>">
            <?php foreach ( $counts as $name => $count ) : ?>
                <button type="button" class="count-row" data-filter-label="<?php echo esc_attr( $title ); ?>" data-filter-key="<?php echo esc_attr( $filter_key ); ?>" data-filter-value="<?php echo esc_attr( $name ); ?>">
                    <span><?php echo esc_html( $display_names[ $name ] ?? $name ); ?></span>
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
        <div class="stat">
            <span>Flights</span>
            <strong><?php echo esc_html( (string) $summary['logged'] ); ?> <small title="<?php echo esc_attr( $summary['planned'] . ' upcoming flights' ); ?>" aria-label="<?php echo esc_attr( $summary['planned'] . ' upcoming flights' ); ?>">(<?php echo esc_html( (string) $summary['planned'] ); ?>)</small></strong>
        </div>
        <button type="button" class="stat stat-button" id="airport-filter-open"><span>Airports</span><strong><?php echo esc_html( (string) count( $summary['airports'] ) ); ?></strong></button>
        <button type="button" class="stat stat-button" id="route-filter-open"><span>Routes</span><strong><?php echo esc_html( (string) count( $summary['routes'] ) ); ?></strong></button>
        <button type="button" class="stat stat-button" id="aircraft-filter-open"><span>Aircraft</span><strong><?php echo esc_html( (string) count( $summary['aircraft'] ) ); ?></strong></button>
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
            <section class="panel grouping-panel" id="airport-filter-panel" data-grouping-filter>
                <div class="grouping-filter-header">
                    <h2>Airports</h2>
                    <div class="grouping-modes" role="group" aria-label="Group airports by">
                        <button type="button" data-grouping-mode="airport" aria-pressed="true" aria-controls="airport-mode-airport">Airport</button>
                        <button type="button" data-grouping-mode="country" aria-pressed="false" aria-controls="airport-mode-country">Country</button>
                    </div>
                    <?php foreach ( [ 'airport' => 'airports', 'country' => 'countries' ] as $mode => $group ) : ?>
                        <?php if ( count( $summary[ $group ] ) > 12 ) : ?>
                            <button type="button" class="count-list-toggle grouping-list-toggle" data-grouping-expand="<?php echo esc_attr( $mode ); ?>" aria-expanded="false" aria-controls="count-list-<?php echo esc_attr( $mode ); ?>"<?php echo 'airport' !== $mode ? ' hidden' : ''; ?>>
                                <span class="count-list-hint">Show all (<?php echo esc_html( (string) count( $summary[ $group ] ) ); ?>)</span>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div id="airport-mode-airport" data-grouping-view="airport">
                    <?php $render_count_list( 'Airports', $summary['airports'], 'airport', '', false ); ?>
                </div>
                <div id="airport-mode-country" data-grouping-view="country" hidden>
                    <div class="count-list country-groups" id="count-list-country">
                        <?php foreach ( $summary['countries'] as $country => $count ) : ?>
                            <details class="country-group" open>
                                <summary class="country-heading">
                                    <span><?php echo esc_html( $country ); ?></span>
                                    <button type="button" class="country-filter" data-filter-label="Countries" data-filter-key="country" data-filter-value="<?php echo esc_attr( $country ); ?>" aria-label="<?php echo esc_attr( 'Filter flights by country ' . $country ); ?>" title="Filter flights by country">
                                        <?php echo esc_html( (string) $count ); ?>
                                    </button>
                                </summary>
                                <div class="country-airports">
                                    <?php foreach ( $airports_by_country[ $country ] as $airport => $airport_count ) : ?>
                                        <button type="button" class="count-row" data-filter-label="Airports" data-filter-key="airport" data-filter-value="<?php echo esc_attr( $airport ); ?>">
                                            <span><?php echo esc_html( $airport ); ?></span>
                                            <strong><?php echo esc_html( (string) $airport_count ); ?></strong>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <section class="panel grouping-panel" id="route-filter-panel" data-grouping-filter>
                <div class="grouping-filter-header">
                    <h2>Routes</h2>
                    <div class="grouping-modes" role="group" aria-label="Group flights by">
                        <button type="button" data-grouping-mode="route" aria-pressed="true" aria-controls="route-mode-route">Route</button>
                        <button type="button" data-grouping-mode="leg" aria-pressed="false" aria-controls="route-mode-leg">Leg</button>
                    </div>
                    <?php foreach ( [ 'route' => [ 'routes', 'route_display' ], 'leg' => [ 'legs', 'route_key' ] ] as $mode => $group ) : ?>
                        <?php if ( count( $summary[ $group[0] ] ) > 12 ) : ?>
                            <button type="button" class="count-list-toggle grouping-list-toggle" data-grouping-expand="<?php echo esc_attr( $mode ); ?>" aria-expanded="false" aria-controls="count-list-<?php echo esc_attr( $group[1] ); ?>"<?php echo 'route' !== $mode ? ' hidden' : ''; ?>>
                                <span class="count-list-hint">Show all (<?php echo esc_html( (string) count( $summary[ $group[0] ] ) ); ?>)</span>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div id="route-mode-route" data-grouping-view="route">
                    <?php $render_count_list( 'Routes', $summary['routes'], 'route_display', '', false ); ?>
                </div>
                <div id="route-mode-leg" data-grouping-view="leg" hidden>
                    <?php $render_count_list( 'Legs', $summary['legs'], 'route_key', '', false ); ?>
                </div>
            </section>
            <section class="panel grouping-panel" id="aircraft-filter-panel" data-grouping-filter>
                <div class="grouping-filter-header">
                    <h2>Aircraft</h2>
                    <div class="grouping-modes" role="group" aria-label="Group aircraft by">
                        <button type="button" data-grouping-mode="manufacturer" aria-pressed="false" aria-controls="aircraft-mode-manufacturer" aria-label="Manufacturer">Make</button>
                        <button type="button" data-grouping-mode="family" aria-pressed="false" aria-controls="aircraft-mode-family">Family</button>
                        <button type="button" data-grouping-mode="type" aria-pressed="true" aria-controls="aircraft-mode-type">Type</button>
                        <button type="button" data-grouping-mode="body" aria-pressed="false" aria-controls="aircraft-mode-body">Body</button>
                    </div>
                    <?php foreach ( [ 'manufacturer' => [ 'manufacturers', 'manufacturer' ], 'family' => [ 'families', 'aircraft_family' ], 'type' => [ 'types', 'aircraft_type' ], 'body' => [ 'body_types', 'body_type' ] ] as $mode => $group ) : ?>
                        <?php if ( count( $summary[ $group[0] ] ) > 12 ) : ?>
                            <button type="button" class="count-list-toggle grouping-list-toggle" data-grouping-expand="<?php echo esc_attr( $mode ); ?>" aria-expanded="false" aria-controls="count-list-<?php echo esc_attr( $group[1] ); ?>"<?php echo 'type' !== $mode ? ' hidden' : ''; ?>>
                                <span class="count-list-hint">Show all (<?php echo esc_html( (string) count( $summary[ $group[0] ] ) ); ?>)</span>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div id="aircraft-mode-manufacturer" data-grouping-view="manufacturer" hidden>
                    <?php $render_count_list( 'Manufacturers', $summary['manufacturers'], 'manufacturer', '', false ); ?>
                </div>
                <div id="aircraft-mode-family" data-grouping-view="family" hidden>
                    <?php $render_count_list( 'Aircraft families', $summary['families'], 'aircraft_family', '', false ); ?>
                </div>
                <div id="aircraft-mode-type" data-grouping-view="type">
                    <?php $render_count_list( 'Aircraft types', $summary['types'], 'aircraft_type', '', false ); ?>
                </div>
                <div id="aircraft-mode-body" data-grouping-view="body" hidden>
                    <?php $render_count_list( 'Body types', $summary['body_types'], 'body_type', '', false ); ?>
                </div>
            </section>
            <section class="panel grouping-panel" id="seat-filter-panel">
                <h2>Seat</h2>
                <p class="seat-filter-note">Estimated from seat letter and aircraft body type; airline and cabin layouts may vary.</p>
                <h3>Position</h3>
                <?php $render_count_list( 'Seat position', $summary['seat_pos'], 'seat_position', '', false ); ?>
                <h3>Side</h3>
                <?php $render_count_list( 'Seat side', $summary['seat_sides'], 'seat_side', '', false, [ 'Left' => 'Port', 'Right' => 'Starboard' ] ); ?>
            </section>
            <?php $render_count_list( 'Years', $summary['years'], 'year' ); ?>
        </section>
    </details>

    <div class="flight-workspace" id="flight-workspace">
        <aside class="filter-sidebar" id="filter-sidebar" aria-label="Active filter" hidden>
            <button type="button" class="button secondary" id="browse-filters">All filters</button>
            <div id="active-filter-panel"></div>
        </aside>
        <div class="flight-results">
            <p class="filter-results-status" id="filter-results-status" role="status" aria-live="polite"></p>
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
        </div>
    </div>
</main>
<?php wp_app_body_close(); ?>
</body>
</html>
