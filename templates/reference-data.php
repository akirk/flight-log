<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$flight_log_references = \FlightLog\ReferenceOverrides::handle_submission();
$flight_log_reference_cache = get_option( 'flight_log_reference_names', [] );
// Include codes from flights even when no reference lookup has succeeded yet.
$flight_log_used_codes = [ 'airports' => [], 'airlines' => [], 'aircraft' => [] ];
$flight_log_aircraft_airlines = [];
$flight_log_posts = get_posts( [
    'post_type' => \FlightLog\App::POST_TYPE,
    'post_status' => [ 'private', 'publish', 'draft' ],
    'posts_per_page' => -1,
] );
foreach ( $flight_log_posts as $flight_log_post ) {
    $flight_log_aircraft_name = trim( (string) get_post_meta( $flight_log_post->ID, '_flight_log_aircraft', true ) );
    $flight_log_aircraft = strtoupper( $flight_log_aircraft_name );
    if ( $flight_log_aircraft ) {
        $flight_log_used_codes['aircraft'][] = $flight_log_aircraft;
        $flight_log_reference_cache['aircraft_names'][ $flight_log_aircraft ] = $flight_log_aircraft_name;
        $flight_log_reference_cache['aircraft'][ $flight_log_aircraft ] = \FlightLog\App::get_instance()->reference_body_type( $flight_log_aircraft );
    }
    foreach ( [ 'from', 'to' ] as $flight_log_endpoint ) {
        $flight_log_used_codes['airports'][] = strtoupper( trim( (string) get_post_meta( $flight_log_post->ID, '_flight_log_' . $flight_log_endpoint, true ) ) );
    }
    $flight_log_airline_code = strtoupper( substr( (string) get_post_meta( $flight_log_post->ID, '_flight_log_flightnr', true ), 0, 2 ) );
    $flight_log_used_codes['airlines'][] = $flight_log_airline_code;
    if ( $flight_log_aircraft && preg_match( '/^[A-Z0-9]{2}$/', $flight_log_airline_code ) ) {
        $flight_log_aircraft_airlines[ $flight_log_aircraft ][ $flight_log_airline_code ] = ( $flight_log_references['values']['airlines'][ $flight_log_airline_code ]['name'] ?? '' ) ?: ( $flight_log_reference_cache['airlines'][ $flight_log_airline_code ] ?? $flight_log_airline_code );
    }
}
$flight_log_seat_descriptions = [];
foreach ( \FlightLog\ReferenceOverrides::BODY_TYPES as $flight_log_body_type ) {
    $flight_log_seat_descriptions[ $flight_log_body_type ] = \FlightLog\App::get_instance()->automatic_seat_description( $flight_log_body_type );
}
$flight_log_tables = \FlightLog\ReferenceOverrides::table_rows( $flight_log_reference_cache, $flight_log_references['values'], $flight_log_used_codes );
?><!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_the_title( 'Airports, airlines and aircraft — Flight Log' ); ?></title>
    <?php
    wp_app_enqueue_style(
        'flight-log-index',
        plugins_url( 'assets/css/index.css', dirname( __DIR__ ) . '/flight-log.php' ),
        [],
        (string) filemtime( dirname( __DIR__ ) . '/assets/css/index.css' ),
        'flight-log'
    );
    wp_app_enqueue_script(
        'flight-log-reference-data',
        plugins_url( 'assets/js/reference-data.js', dirname( __DIR__ ) . '/flight-log.php' ),
        [],
        (string) filemtime( dirname( __DIR__ ) . '/assets/js/reference-data.js' ),
        true,
        'flight-log'
    );
    wp_app_head();
    ?>
</head>
<body>
<?php wp_app_body_open(); ?>
<main class="app-shell">
    <header class="page-header">
        <div>
            <p class="eyebrow">Flight log</p>
            <h1>Airports, airlines and aircraft</h1>
        </div>
        <a class="button secondary" href="<?php echo esc_url( home_url( '/flight-log/' ) ); ?>">Back to flights</a>
    </header>
        <?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice. ?>
            <p class="notice" role="status">Overrides saved.</p>
        <?php endif; ?>
    <form method="post" class="settings-form" aria-labelledby="reference-settings-title">
        <div class="panel settings-panel">
        <h2 id="reference-settings-title">Reference overrides</h2>
        <p>Correct names or fill in missing entries for your account. Changes apply to existing and new flights. Reference values appear as hints in empty fields. Leave a field blank to use the reference data; clear all fields for a code to remove its override.</p>
        <?php wp_nonce_field( 'flight_log_save_references' ); ?>
        <?php if ( $flight_log_references['error'] ) : ?>
            <p role="alert"><?php echo esc_html( $flight_log_references['error'] ); ?></p>
        <?php endif; ?>
        <?php foreach ( [ 'airports' => 'Airports', 'airlines' => 'Airlines', 'aircraft' => 'Aircraft' ] as $flight_log_type => $flight_log_label ) :
            $flight_log_index = 0;
            $flight_log_extra = 'airports' === $flight_log_type ? 'country' : 'icao';
            ?>
            <section class="reference-section" data-reference-section="<?php echo esc_attr( $flight_log_type ); ?>">
                <h3><?php echo esc_html( $flight_log_label ); ?></h3>
                <p><?php echo esc_html( 'aircraft' === $flight_log_type ? 'Use the aircraft name shown in your flights. Choose a seat layout to correct estimated seat positions independently of body type. Letters are shown from left to right, with | marking aisles. Leave either dropdown on Automatic to use the existing classification.' : ( 'airports' === $flight_log_type ? 'Use IATA (VIE) or ICAO (LOWW) airport codes. Country uses two letters, for example AT.' : 'Use the two-character airline prefix from the flight number, for example OS. ICAO (AUA) is used for FlightAware links.' ) ); ?></p>
                <?php foreach ( $flight_log_tables[ $flight_log_type ] as $flight_log_group => $flight_log_rows ) : ?>
                    <?php if ( 'known' === $flight_log_group ) : ?>
                        <details>
                            <summary>Known codes (<?php echo count( $flight_log_rows ); ?>)</summary>
                    <?php else : ?>
                        <h4>Missing codes and your overrides</h4>
                        <?php if ( ! $flight_log_rows ) : ?><p>No missing codes.</p><?php endif; ?>
                        <?php $flight_log_rows[] = []; ?>
                    <?php endif; ?>
                    <div class="reference-table-scroll">
                        <table class="reference-table">
                            <thead><tr><?php if ( 'aircraft' === $flight_log_type ) : ?><th scope="col">Aircraft</th><th scope="col">Body type</th><th scope="col">Seat layout</th><?php else : ?><th scope="col">Code</th><th scope="col">Name</th><th scope="col"><?php echo 'country' === $flight_log_extra ? 'Country code' : 'ICAO code'; ?></th><?php endif; ?><th scope="col">Override</th></tr></thead>
                            <tbody class="reference-rows" <?php if ( 'missing' === $flight_log_group ) : ?>data-new-references<?php endif; ?>>
                                <?php foreach ( $flight_log_rows as $flight_log_row ) : ?>
                                    <tr class="reference-row">
                                        <?php foreach ( ( 'aircraft' === $flight_log_type ? [ 'code' => 'Aircraft', 'body_type' => 'Body type', 'seat_layout' => 'Seat layout' ] : [ 'code' => 'Code', 'name' => 'Name', $flight_log_extra => 'country' === $flight_log_extra ? 'Country code' : 'ICAO code' ] ) as $flight_log_field => $flight_log_field_label ) :
                                            $flight_log_id = 'reference-' . $flight_log_type . '-' . $flight_log_index . '-' . $flight_log_field;
                                            ?>
                                            <td>
                                                <?php if ( 'body_type' === $flight_log_field ) : ?>
                                                    <select aria-label="Body type" data-automatic-body="<?php echo esc_attr( $flight_log_row['reference_body_type'] ?? 'Unknown' ); ?>" id="<?php echo esc_attr( $flight_log_id ); ?>" name="references[aircraft][<?php echo esc_attr( $flight_log_index ); ?>][body_type]">
                                                        <option value="">Automatic: <?php echo esc_html( $flight_log_row['reference_body_type'] ?? 'Unknown' ); ?></option>
                                                        <?php foreach ( \FlightLog\ReferenceOverrides::BODY_TYPES as $flight_log_body_type ) : ?>
                                                            <option value="<?php echo esc_attr( $flight_log_body_type ); ?>" <?php selected( $flight_log_row['body_type'] ?? '', $flight_log_body_type ); ?>><?php echo esc_html( $flight_log_body_type ); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php elseif ( 'seat_layout' === $flight_log_field ) : ?>
                                                    <select aria-label="<?php echo esc_attr( 'Seat layout ' . ( $flight_log_row['code'] ?? 'new entry' ) ); ?>" id="<?php echo esc_attr( $flight_log_id ); ?>" name="references[aircraft][<?php echo esc_attr( $flight_log_index ); ?>][seat_layout]">
                                                        <option value="">Automatic: <?php echo esc_html( ( $flight_log_row['body_type'] ?? '' ) ?: ( $flight_log_row['reference_body_type'] ?? 'Unknown' ) ); ?></option>
                                                        <?php foreach ( \FlightLog\ReferenceOverrides::SEAT_LAYOUTS as $flight_log_layout => $flight_log_layout_label ) : ?>
                                                            <option value="<?php echo esc_attr( $flight_log_layout ); ?>" <?php selected( $flight_log_row['seat_layout'] ?? '', $flight_log_layout ); ?>><?php echo esc_html( $flight_log_layout_label ); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small data-seat-automatic-description data-descriptions="<?php echo esc_attr( wp_json_encode( $flight_log_seat_descriptions ) ); ?>"<?php echo empty( $flight_log_row['seat_layout'] ) ? '' : ' hidden'; ?>><?php echo esc_html( $flight_log_seat_descriptions[ ( $flight_log_row['body_type'] ?? '' ) ?: ( $flight_log_row['reference_body_type'] ?? 'Unknown' ) ] ); ?></small>
                                                <?php else : ?>
                                                <input aria-label="<?php echo esc_attr( $flight_log_field_label . ' ' . ( $flight_log_row['code'] ?? 'new entry' ) ); ?>" id="<?php echo esc_attr( $flight_log_id ); ?>" name="references[<?php echo esc_attr( $flight_log_type ); ?>][<?php echo esc_attr( $flight_log_index ); ?>][<?php echo esc_attr( $flight_log_field ); ?>]" value="<?php echo esc_attr( ( 'code' === $flight_log_field ? ( $flight_log_row['display_code'] ?? $flight_log_row['code'] ?? '' ) : ( $flight_log_row[ $flight_log_field ] ?? '' ) ) ); ?>" placeholder="<?php echo esc_attr( $flight_log_row[ 'reference_' . $flight_log_field ] ?? '' ); ?>" <?php if ( 'name' !== $flight_log_field && 'aircraft' !== $flight_log_type ) : ?>class="reference-code" size="4" maxlength="<?php echo 'code' === $flight_log_field && 'airports' === $flight_log_type ? '4' : ( 'icao' === $flight_log_field ? '3' : '2' ); ?>"<?php endif; ?>>
                                                <?php endif; ?>
                                                <?php if ( 'aircraft' === $flight_log_type && 'code' === $flight_log_field ) : ?>
                                                    <?php $flight_log_seat_map_airlines = $flight_log_aircraft_airlines[ strtoupper( $flight_log_row['code'] ?? '' ) ] ?? []; ?>
                                                    <?php if ( $flight_log_seat_map_airlines ) : ?>
                                                        <small class="reference-seat-maps">
                                                            AeroLOPA:
                                                            <?php foreach ( $flight_log_seat_map_airlines as $flight_log_airline_code => $flight_log_airline_name ) : ?>
                                                                <a href="<?php echo esc_url( \FlightLog\ReferenceOverrides::seat_map_url( (string) $flight_log_airline_code, $flight_log_row['display_code'] ?? $flight_log_row['code'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $flight_log_airline_name . ' · ' . ( $flight_log_row['display_code'] ?? $flight_log_row['code'] ?? '' ) ); ?>"><?php echo esc_html( $flight_log_airline_code ); ?> ↗</a>
                                                            <?php endforeach; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <button type="button" class="button secondary remove-reference">Reset</button>
                                            <?php if ( ! empty( $flight_log_row['code'] ) && in_array( strtoupper( $flight_log_row['code'] ), $flight_log_used_codes[ $flight_log_type ], true ) ) : ?>
                                                <a class="reference-flights" href="<?php echo esc_url( add_query_arg( [ 'filter' => [ 'airports' => 'airport', 'airlines' => 'airline_code', 'aircraft' => 'aircraft' ][ $flight_log_type ], 'value' => $flight_log_row['code'] ], home_url( '/flight-log/' ) ) ); ?>">View flights</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php ++$flight_log_index; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ( 'known' === $flight_log_group ) : ?></details><?php endif; ?>
                <?php endforeach; ?>
                <button type="button" class="button secondary add-reference">Add <?php echo 'airports' === $flight_log_type ? 'airport' : ( 'aircraft' === $flight_log_type ? 'aircraft' : 'airline' ); ?></button>
            </section>
        <?php endforeach; ?>
        </div>
        <button type="submit" name="flight_log_save_references" value="1" class="button">Save overrides</button>
    </form>
</main>
<?php wp_app_body_close(); ?>
</body>
</html>
