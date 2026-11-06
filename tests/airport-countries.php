<?php
// Standalone checks: php tests/airport-countries.php.
require_once __DIR__ . '/../vendor/autoload.php';

$country_test_cache = [ 'airports' => [ 'FRA' => 'FRA - Wrong airport' ], 'airport_countries' => [ 'FRA' => 'AR' ], 'airlines' => [ 'OS' => 'Austrian Airlines' ] ];
$country_test_requests = 0;
$reference_test_overrides = [];
function get_current_user_id() { return 42; }
function get_user_meta( $id, $key, $single ) { global $reference_test_overrides; return $reference_test_overrides; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
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

$reference_test_overrides = FlightLog\ReferenceOverrides::validate( [
    'airports' => [ [ 'code' => 'zzz', 'name' => 'Custom airport', 'country' => 'at' ], [ 'code' => 'FRA', 'name' => 'Corrected Frankfurt' ] ],
    'airlines' => [ [ 'code' => 'ZZ', 'name' => 'Custom airline', 'icao' => 'zzz' ] ],
] );
$expect( $invoke( 'airport_name', 'zzz' ), 'ZZZ - Custom airport' );
$expect( $invoke( 'airport_country', 'ZZZ' ), 'Austria' );
$expect( $invoke( 'airport_name', 'FRA' ), 'FRA - Corrected Frankfurt' );
$expect( $invoke( 'airport_country', 'FRA' ), 'Germany' );
$expect( $invoke( 'airline_name', 'ZZ12' ), 'Custom airline' );
$expect( $country_test_cache['airports']['ZZZ'], 'ZZZ' );
$reference_test_overrides = FlightLog\ReferenceOverrides::validate( [ 'airports' => [ [ 'code' => 'ZZZ', 'name' => '' ] ] ] );
$expect( $invoke( 'airport_name', 'ZZZ' ), 'ZZZ' );
$expect( $invoke( 'airline_name', 'ZZ12' ), 'ZZ' );
foreach ( [ [ 'code' => 'BAD!!', 'name' => 'Bad' ], [ 'code' => 'VIE', 'country' => 'AUT' ], [ 'code' => 'VIE', 'name' => [] ] ] as $bad ) {
    try {
        FlightLog\ReferenceOverrides::validate( [ 'airports' => [ $bad ] ] );
        throw new RuntimeException( 'Invalid override accepted.' );
    } catch ( InvalidArgumentException $expected ) {}
}
echo "Reference override validation, lookup, country fallback, removal and cache isolation checks passed.\n";

$tables = FlightLog\ReferenceOverrides::table_rows(
    [ 'airports' => [ 'VIE' => 'VIE - Vienna', 'ZZZ' => 'ZZZ', 'FRA' => 'FRA - Frankfurt' ], 'airport_countries' => [ 'VIE' => 'AT' ], 'airlines' => [ 'OS' => 'Austrian Airlines', 'ZZ' => 'ZZ' ] ],
    [ 'airports' => [ 'VIE' => [ 'name' => 'My Vienna' ] ], 'airlines' => [] ],
    [ 'airports' => [ 'NEW' ], 'airlines' => [ 'XY' ] ]
);
$expect( array_column( $tables['airports']['missing'], 'code' ), [ 'FRA', 'NEW', 'VIE', 'ZZZ' ] );
$expect( $tables['airports']['missing'][2]['reference_name'], 'Vienna' );
$expect( $tables['airports']['missing'][2]['name'], 'My Vienna' );
$expect( array_column( $tables['airlines']['known'], 'code' ), [ 'OS' ] );
$expect( array_column( $tables['airlines']['missing'], 'code' ), [ 'XY', 'ZZ' ] );
echo "Settings table grouping and uncached flight code checks passed.\n";

$reference_test_overrides = FlightLog\ReferenceOverrides::validate( [
    'aircraft' => [ [ 'code' => 'Custom aircraft', 'body_type' => 'Regional jet' ] ],
] );
$expect( $invoke( 'body_type', 'Custom aircraft', 'custom aircraft' ), 'Regional jet' );
$expect( $invoke( 'seat_info', '12D', $invoke( 'body_type', 'Custom aircraft', 'Custom aircraft' ) )['position'], 'Window' );
$expect( $app->reference_body_type( 'Custom aircraft' ), 'Unknown' );
$tables = FlightLog\ReferenceOverrides::table_rows(
    [ 'aircraft' => [ 'CUSTOM AIRCRAFT' => 'Unknown', 'AIRBUS A320' => 'Narrowbody' ] ],
    $reference_test_overrides,
    []
);
$expect( $tables['aircraft']['missing'][0]['body_type'], 'Regional jet' );
$expect( $tables['aircraft']['known'][0]['reference_body_type'], 'Narrowbody' );
foreach ( [
    [ [ 'code' => 'Test', 'body_type' => 'Invalid' ] ],
    [ [ 'code' => '', 'body_type' => 'Widebody' ] ],
    [ [ 'code' => 'Test', 'body_type' => 'Widebody' ], [ 'code' => 'test', 'body_type' => 'Propeller' ] ],
] as $bad ) {
    try {
        FlightLog\ReferenceOverrides::validate( [ 'aircraft' => $bad ] );
        throw new RuntimeException( 'Invalid aircraft override accepted.' );
    } catch ( InvalidArgumentException $e ) {
        // Expected invalid or duplicate aircraft entry.
    }
}
$reference_test_overrides = FlightLog\ReferenceOverrides::validate( [ 'aircraft' => [ [ 'code' => 'Custom aircraft', 'body_type' => '' ] ] ] );
$expect( $reference_test_overrides['aircraft'], [] );
$expect( $invoke( 'body_type', 'Custom aircraft', 'Custom aircraft' ), 'Unknown' );
echo "Aircraft body override, seat estimation, validation and removal checks passed.\n";

$aircraft_values = FlightLog\ReferenceOverrides::validate( [ 'aircraft' => [ [ 'code' => 'Embraer ERJ-190LR', 'body_type' => 'Regional jet' ] ] ] );
$tables = FlightLog\ReferenceOverrides::table_rows(
    [ 'aircraft' => [ 'EMBRAER ERJ-190LR' => 'Unknown' ], 'aircraft_names' => [ 'EMBRAER ERJ-190LR' => 'Embraer ERJ-190LR' ] ],
    $aircraft_values,
    [ 'aircraft' => [ 'EMBRAER ERJ-190LR' ] ]
);
$expect( count( $tables['aircraft']['missing'] ), 1 );
$expect( $tables['aircraft']['missing'][0]['display_code'], 'Embraer ERJ-190LR' );
echo "Aircraft display casing and override row deduplication checks passed.\n";
