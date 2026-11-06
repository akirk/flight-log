<?php
// Standalone checks: php tests/airport-countries.php.
require_once __DIR__ . '/../vendor/autoload.php';

$country_test_cache = [ 'airports' => [ 'VIE' => 'VIE - Vienna', 'LOWW' => 'LOWW - Vienna' ], 'airlines' => [] ];
$country_test_requests = 0;
function get_option( $key, $default = false ) {
    global $country_test_cache;
    return $country_test_cache;
}
function update_option( $key, $value, $autoload = null ) {
    global $country_test_cache;
    $country_test_cache = $value;
}
function is_wp_error( $value ) { return false; }
function wp_remote_get( $url, $args ) {
    global $country_test_requests;
    ++$country_test_requests;
    return [ 'body' => "ident,name,iso_country,iata_code,icao_code,gps_code,local_code\nLOWW,Vienna,AT,VIE,LOWW,LOWW,\nEDDF,Frankfurt,DE,FRA,EDDF,EDDF,\n" ];
}
function wp_remote_retrieve_response_code( $response ) { return 200; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }

$class = new ReflectionClass( FlightLog\App::class );
$app = $class->newInstanceWithoutConstructor();
$invoke = static function( $name, ...$args ) use ( $class, $app ) {
    $method = $class->getMethod( $name );
    $method->setAccessible( true );
    return $method->invokeArgs( $app, $args );
};
$expect = static function( $actual, $expected ) {
    if ( $actual !== $expected ) {
        throw new RuntimeException( var_export( [ $actual, $expected ], true ) );
    }
};

// Old name-only caches must acquire countries, including ICAO aliases.
$invoke( 'prime_reference_names', [ 'vie', 'LOWW', 'FRA', 'ZZZ' ], [] );
$expect( $country_test_requests, 1 );
$expect( $invoke( 'airport_country', 'vie' ), 'Austria' );
$expect( $invoke( 'airport_country', 'LOWW' ), 'Austria' );
$expect( $invoke( 'airport_country', 'FRA' ), 'Germany' );
$expect( $invoke( 'airport_country', 'ZZZ' ), 'Unknown' );
// Unknown codes are cached too, so they do not cause repeated downloads.
$invoke( 'prime_reference_names', [ 'VIE', 'LOWW', 'FRA', 'ZZZ' ], [] );
$expect( $country_test_requests, 1 );
echo "Airport country cache and lookup checks passed.\n";
