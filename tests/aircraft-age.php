<?php
// Standalone checks: php tests/aircraft-age.php.
require_once __DIR__ . '/../vendor/autoload.php';

$class = new ReflectionClass( FlightLog\App::class );
$app = $class->newInstanceWithoutConstructor();
$bucket = $class->getMethod( 'aircraft_age_bucket' );
$bucket->setAccessible( true );
foreach ( [
    '' => 'Unknown',
    '0m' => 'Under 1 year',
    '11m' => 'Under 1 year',
    '1y' => '1–4 years',
    '4y 11m' => '1–4 years',
    '5y' => '5–9 years',
    '9y 11m' => '5–9 years',
    '10y' => '10–14 years',
    '14y 11m' => '10–14 years',
    '15y' => '15–19 years',
    '19y 11m' => '15–19 years',
    '20y' => '20–24 years',
    '24y 11m' => '20–24 years',
    '25y' => '25–29 years',
    '29y 11m' => '25–29 years',
    '30y' => '30–34 years',
    '34y 11m' => '30–34 years',
    '35y' => '35–39 years',
    '39y 11m' => '35–39 years',
    '40y' => '40+ years',
    '65y 6m' => '40+ years',
] as $age => $expected ) {
    $actual = $bucket->invoke( $app, $age );
    if ( $actual !== $expected ) {
        throw new RuntimeException( "Age {$age}: expected {$expected}, got {$actual}" );
    }
}
echo "Aircraft age bucket boundary checks passed.\n";
