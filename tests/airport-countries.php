<?php
// Standalone checks: php tests/airport-countries.php.
require_once __DIR__ . '/../vendor/autoload.php';

$country_test_cache = [ 'airports' => [ 'FRA' => 'FRA - Wrong airport' ], 'airport_countries' => [ 'FRA' => 'AR' ], 'airlines' => [ 'OS' => 'Austrian Airlines' ] ];
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
    return [ 'body' => "ident,name,iso_country,iata_code,icao_code,gps_code,local_code\nAR-1,Local collision,AR,,,,FRA\nMX-1,GPS collision,MX,,,VIE,\nID-4,Local only,AR,,,,LOCAL\nID-5,GPS only,MX,,,GPS,\nIDENT,Identifier only,US,,,,\nID-2,NAME,US,,,,\nID-3,ICAO collision,US,,FRA,,\nLOWW,Vienna,AT,VIE,LOWW,LOWW,\nEDDF,Frankfurt,DE,FRA,EDDF,EDDF,\n" ];
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

// Old incorrect mappings must be invalidated while airline entries survive.
$cache = $invoke( 'reference_names' );
$expect( $cache['airports'], [] );
$expect( $cache['airport_countries'], [] );
$expect( $cache['airlines'], [ 'OS' => 'Austrian Airlines' ] );
$invoke( 'prime_reference_names', [ 'vie', 'LOWW', 'FRA', 'ZZZ', 'IDENT', 'NAME', 'LOCAL', 'GPS' ], [] );
$expect( $country_test_requests, 1 );
$expect( $invoke( 'airport_country', 'vie' ), 'Austria' );
$expect( $invoke( 'airport_country', 'LOWW' ), 'Austria' );
$expect( $invoke( 'airport_country', 'FRA' ), 'Germany' );
$expect( $invoke( 'airport_country', 'ZZZ' ), 'Unknown' );
$expect( $invoke( 'airport_country', 'IDENT' ), 'Unknown' );
$expect( $invoke( 'airport_country', 'NAME' ), 'Unknown' );
$expect( $invoke( 'airport_country', 'LOCAL' ), 'Unknown' );
$expect( $invoke( 'airport_country', 'GPS' ), 'Unknown' );
$expect( $invoke( 'airport_name', 'FRA' ), 'FRA - Frankfurt' );
$expect( $invoke( 'airport_name', 'IDENT' ), 'IDENT' );
$expect( $country_test_cache['airport_lookup_version'], 2 );
// Unknown codes are cached too, so they do not cause repeated downloads.
$invoke( 'prime_reference_names', [ 'VIE', 'LOWW', 'FRA', 'ZZZ' ], [] );
$expect( $country_test_requests, 1 );
echo "Airport country cache and lookup checks passed.\n";
