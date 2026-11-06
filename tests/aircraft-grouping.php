<?php
// Standalone checks: php tests/aircraft-grouping.php.
require_once __DIR__ . '/../vendor/autoload.php';
$class = new ReflectionClass( FlightLog\App::class );
$app = $class->newInstanceWithoutConstructor();
$type = $class->getMethod( 'aircraft_type' );
$family = $class->getMethod( 'aircraft_family' );
$type->setAccessible( true );
$family->setAccessible( true );
foreach ( [
    [ 'Embraer ERJ-190LR', 'Embraer E190', 'Embraer E-Jet' ],
    [ 'Embraer ERJ-190AR', 'Embraer E190', 'Embraer E-Jet' ],
    [ 'Embraer ERJ-195LR', 'Embraer E195', 'Embraer E-Jet' ],
    [ 'Embraer ERJ-175', 'Embraer E175', 'Embraer E-Jet' ],
    [ 'Embraer EMB-145LR', 'Embraer ERJ 145', 'Embraer ERJ' ],
    [ 'Embraer E195-E2', 'Embraer E195-E2', 'Embraer E-Jet E2' ],
    [ 'Airbus A320-214', 'Airbus A320-200', 'Airbus A320' ],
    [ 'Boeing 737-8AS', 'Boeing 737-800', 'Boeing 737' ],
] as [ $input, $expected_type, $expected_family ] ) {
    $actual = $type->invoke( $app, $input );
    if ( $actual !== $expected_type || $family->invoke( $app, $actual ) !== $expected_family ) {
        throw new RuntimeException( 'Incorrect grouping for ' . $input );
    }
}
echo "Aircraft type and family grouping checks passed.\n";
