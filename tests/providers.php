<?php
// Standalone checks: php tests/providers.php.
require_once __DIR__ . '/../src/Providers.php';
require_once __DIR__ . '/../src/ReferenceOverrides.php';

$provider_test_settings = [];
function get_current_user_id() { return 42; }
function get_user_meta( $id, $key, $single ) {
    global $provider_test_settings;
    if ( '_flight_log_reference_overrides' === $key ) {
        return [ 'airlines' => [ 'XY' => [ 'icao' => 'XYZ' ] ] ];
    }
    if ( 42 !== $id || '_flight_log_link_providers' !== $key || ! $single ) {
        throw new RuntimeException( 'Unexpected preference lookup.' );
    }
    return $provider_test_settings;
}
function get_transient( $key ) { return [ 'OS' => 'AUA', 'U2' => 'EZY', 'A3' => 'AEE' ]; }

function expect_provider( $actual, $expected ) {
    if ( $actual !== $expected ) {
        throw new RuntimeException( var_export( [ 'actual' => $actual, 'expected' => $expected ], true ) );
    }
}

use FlightLog\Providers;

expect_provider( Providers::validate( [ 'flightnr' => 'airfleets', 'regnr' => [] ] ), [ 'flightnr' => 'flightradar24', 'regnr' => 'airfleets' ] );
foreach ( [ 'OS0012' => 'os12', 'U20012' => 'u212', 'A30012' => 'a312', 'AUA0012A' => 'aua12a', 'OS0000' => 'os0' ] as $number => $normalized ) {
    $links = Providers::links( [ 'flightnr' => $number ] );
    expect_provider( $links['flightnr']['url'], 'https://www.flightradar24.com/data/flights/' . $normalized );
    expect_provider( $links['regnr']['url'], '' );
}
$provider_test_settings = [ 'flightnr' => 'flightaware', 'regnr' => 'flightaware' ];
foreach ( [ 'OS00184' => 'AUA184', 'U20012' => 'EZY12', 'AUA0012' => 'AUA12' ] as $number => $normalized ) {
    $links = Providers::links( [ 'flightnr' => $number, 'regnr' => ' OE-LZQ ' ] );
    expect_provider( $links['flightnr']['url'], 'https://www.flightaware.com/live/flight/' . $normalized );
    expect_provider( $links['regnr']['url'], 'https://www.flightaware.com/live/flight/OELZQ' );
}
expect_provider( Providers::links( [ 'flightnr' => 'ZZ0012' ] )['flightnr']['url'], '' );
expect_provider( Providers::links( [ 'flightnr' => 'XY0012' ] )['flightnr']['url'], 'https://www.flightaware.com/live/flight/XYZ12' );
$provider_test_settings = [ 'flightnr' => 'none', 'regnr' => 'none' ];
expect_provider( Providers::links( [ 'flightnr' => 'OS12', 'regnr' => 'OE-LZQ' ] )['flightnr']['url'], '' );
$provider_test_settings = [ 'regnr' => 'airfleets' ];
expect_provider( Providers::links( [ 'regnr' => 'A/?#' ] )['regnr']['url'], 'https://www.airfleets.net/recherche/?key=a%2F%3F%23' );
echo "PASS provider defaults, validation, user preferences, URL normalization, ICAO conversion, disabled links, missing codes and encoding.\n";
