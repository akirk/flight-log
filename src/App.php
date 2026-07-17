<?php

namespace FlightTracker;

use DateTime;
use WpApp\BaseApp;
use WpApp\WpApp;

class App extends BaseApp {
    public const POST_TYPE = 'tracked_flight';
    public const NONCE_ACTION = 'flight_tracker_save_flight';
    public const NONCE_NAME = 'flight_tracker_nonce';

    private const META_KEYS = [
        'flightnr',
        'date',
        'from',
        'to',
        'route',
        'regnr',
        'aircraft',
        'first_flight',
        'msn',
        'seat',
        'remarks',
        'legacy_key',
    ];

    private const TAXONOMIES = [
        'flight_tracker_airline'       => [ 'Airlines', 'Airline' ],
        'flight_tracker_airport'       => [ 'Airports', 'Airport' ],
        'flight_tracker_route'         => [ 'Routes', 'Route' ],
        'flight_tracker_aircraft_type' => [ 'Aircraft Types', 'Aircraft Type' ],
        'flight_tracker_manufacturer'  => [ 'Manufacturers', 'Manufacturer' ],
        'flight_tracker_body_type'     => [ 'Body Types', 'Body Type' ],
        'flight_tracker_year'          => [ 'Years', 'Year' ],
        'flight_tracker_seat_position' => [ 'Seat Positions', 'Seat Position' ],
        'flight_tracker_seat_side'     => [ 'Seat Sides', 'Seat Side' ],
    ];

    private static $instance;

    public function __construct() {
        self::$instance = $this;

        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            'app_name' => 'Flight Tracker',
            'my_apps'  => 'Flight Tracker',
        ] );

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
        add_action( 'init', [ $this, 'register_meta' ] );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'flight-tracker import-legacy', [ $this, 'cli_import_legacy' ] );
        }
    }

    public static function get_instance(): ?self {
        return self::$instance;
    }

    protected function get_url_path(): string {
        return 'flight-tracker';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    protected function setup_database(): void {}

    protected function setup_routes(): void {}

    protected function setup_menu(): void {}

    public function register_post_types(): void {
        register_post_type( self::POST_TYPE, [
            'labels'            => [
                'name'          => __( 'Flights', 'flight-tracker' ),
                'singular_name' => __( 'Flight', 'flight-tracker' ),
                'add_new_item'  => __( 'Add New Flight', 'flight-tracker' ),
                'edit_item'     => __( 'Edit Flight', 'flight-tracker' ),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'supports'          => [ 'title', 'custom-fields' ],
            'capability_type'   => 'post',
            'menu_icon'         => 'dashicons-airplane',
            'taxonomies'        => array_keys( self::TAXONOMIES ),
        ] );
    }

    public function register_taxonomies(): void {
        foreach ( self::TAXONOMIES as $taxonomy => $labels ) {
            register_taxonomy( $taxonomy, self::POST_TYPE, [
                'labels'            => [
                    'name'          => __( $labels[0], 'flight-tracker' ),
                    'singular_name' => __( $labels[1], 'flight-tracker' ),
                ],
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
            ] );
        }
    }

    public function register_meta(): void {
        foreach ( self::META_KEYS as $key ) {
            register_post_meta( self::POST_TYPE, $this->meta_key( $key ), [
                'single'            => true,
                'type'              => 'msn' === $key ? 'integer' : 'string',
                'show_in_rest'      => true,
                'sanitize_callback' => [ $this, 'sanitize_meta_value' ],
                'auth_callback'     => static function() {
                    return current_user_can( 'edit_posts' );
                },
            ] );
        }
    }

    public function sanitize_meta_value( $value ) {
        return is_scalar( $value ) ? (string) $value : '';
    }

    public function activate(): void {
        $this->register_taxonomies();
        $this->register_post_types();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }

    public function handle_form_submission(): array {
        $state = [
            'values'            => $this->empty_form_values(),
            'errors'            => [],
            'show_form'         => false,
            'mode'              => 'add',
            'original_flightnr' => '',
            'original_date'     => '',
            'flash'             => isset( $_GET['updated'] ) ? 'Flight updated.' : ( isset( $_GET['added'] ) ? 'Flight added.' : null ),
        ];

        if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return $state;
        }

        $action = sanitize_key( wp_unslash( $_POST['action'] ?? '' ) );
        if ( ! in_array( $action, [ 'add_flight', 'edit_flight' ], true ) ) {
            return $state;
        }

        $state['show_form'] = true;
        $state['mode'] = 'edit_flight' === $action ? 'edit' : 'add';
        $state['original_flightnr'] = sanitize_text_field( wp_unslash( $_POST['original_flightnr'] ?? '' ) );
        $state['original_date'] = sanitize_text_field( wp_unslash( $_POST['original_date'] ?? '' ) );
        $state['values'] = $this->posted_form_values();
        $state['errors'] = $this->validate_form_values( $state['values'], $state['mode'], $state['original_flightnr'], $state['original_date'] );

        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            array_unshift( $state['errors'], 'Security check failed. Please try again.' );
        }

        if ( $state['errors'] ) {
            return $state;
        }

        $values = $this->normalize_submitted_values( $state['values'] );
        $post_id = 'edit' === $state['mode'] ? $this->find_flight_post_id( $state['original_flightnr'], $state['original_date'] ) : 0;

        if ( 'edit' === $state['mode'] && ! $post_id ) {
            $state['errors'][] = 'Could not find the flight to update.';
            return $state;
        }

        $saved = $this->save_flight( $values, $post_id );
        if ( is_wp_error( $saved ) ) {
            $state['errors'][] = $saved->get_error_message();
            return $state;
        }

        wp_safe_redirect( add_query_arg( 'edit' === $state['mode'] ? 'updated' : 'added', '1', remove_query_arg( [ 'updated', 'added' ] ) ) );
        exit;
    }

    public function get_dashboard_data(): array {
        $flights = $this->get_flights();
        $now = new DateTime();

        $summary = [
            'total'       => count( $flights ),
            'logged'      => 0,
            'planned'     => 0,
            'airports'    => [],
            'airlines'    => [],
            'routes'      => [],
            'aircraft'    => [],
            'types'       => [],
            'body_types'  => [],
            'years'       => [],
            'seats'       => [],
            'seat_sides'  => [],
            'seat_pos'    => [],
        ];

        foreach ( $flights as &$flight ) {
            $flight['is_future'] = $flight['date_obj'] > $now;
            $flight['is_future'] ? $summary['planned']++ : $summary['logged']++;

            $this->count( $summary['airports'], $flight['from'] );
            $this->count( $summary['airports'], $flight['to'] );
            $this->count( $summary['airlines'], $flight['airline'] );
            $this->count( $summary['routes'], $flight['route_key'] );
            $this->count( $summary['aircraft'], $flight['regnr'] ?: $flight['aircraft'] ?: 'Unknown' );
            $this->count( $summary['types'], $flight['aircraft_type'] );
            $this->count( $summary['body_types'], $flight['body_type'] );
            $this->count( $summary['years'], $flight['year'] );
            if ( $flight['seat'] ) {
                $this->count( $summary['seats'], $flight['seat'] );
                $this->count( $summary['seat_sides'], $flight['seat_side'] );
                $this->count( $summary['seat_pos'], $flight['seat_position'] );
            }
        }
        unset( $flight );

        foreach ( $summary as $key => $value ) {
            if ( is_array( $value ) ) {
                arsort( $summary[ $key ] );
            }
        }

        return [
            'flights' => $flights,
            'summary' => $summary,
            'form'    => $this->handle_form_submission(),
        ];
    }

    public function empty_form_values(): array {
        return [
            'date'         => '',
            'flightnr'     => '',
            'from'         => '',
            'to'           => '',
            'route'        => '',
            'regnr'        => '',
            'aircraft'     => '',
            'seat'         => '',
            'first_flight' => '',
            'msn'          => '',
            'remarks'      => '',
        ];
    }

    public function flight_key( string $flightnr, string $date ): string {
        return rtrim( strtr( base64_encode( $flightnr . "\0" . $date ), '+/', '-_' ), '=' );
    }

    public function cli_import_legacy( array $args, array $assoc_args ): void {
        $dry_run = ! empty( $assoc_args['dry-run'] );
        $update_existing = ! empty( $assoc_args['update-existing'] );
        $rows = $this->read_import_rows_from_stdin();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ( $rows as $row ) {
            $values = $this->legacy_row_to_values( $row );
            $post_id = $this->find_flight_post_id( $values['flightnr'], $values['date'] );

            if ( $post_id && ! $update_existing ) {
                $skipped++;
                continue;
            }

            if ( ! $dry_run ) {
                $saved = $this->save_flight( $values, $post_id );
                if ( is_wp_error( $saved ) ) {
                    \WP_CLI::warning( $saved->get_error_message() );
                    $skipped++;
                    continue;
                }
            }

            $post_id ? $updated++ : $created++;
        }

        $prefix = $dry_run ? 'Dry run: ' : '';
        \WP_CLI::success( "{$prefix}{$created} created, {$updated} updated, {$skipped} skipped." );
    }

    private function read_import_rows_from_stdin(): array {
        $input = stream_get_contents( STDIN );
        if ( '' === trim( $input ) ) {
            \WP_CLI::error( 'No import data received on STDIN.' );
        }

        $decoded = json_decode( $input, true );
        if ( is_array( $decoded ) ) {
            return $this->normalize_import_rows( $decoded );
        }

        $rows = [];
        foreach ( preg_split( '/\R/', trim( $input ) ) as $line_number => $line ) {
            if ( '' === trim( $line ) ) {
                continue;
            }

            $row = json_decode( $line, true );
            if ( ! is_array( $row ) ) {
                \WP_CLI::error( 'Invalid JSON on input line ' . ( $line_number + 1 ) . '.' );
            }

            $rows[] = $row;
        }

        return $this->normalize_import_rows( $rows );
    }

    private function normalize_import_rows( array $rows ): array {
        if ( $this->is_phpmyadmin_json_export( $rows ) ) {
            $rows = $this->extract_rows_from_phpmyadmin_json_export( $rows );
        }

        if ( isset( $rows['date'], $rows['flightnr'] ) ) {
            $rows = [ $rows ];
        }

        foreach ( $rows as $index => $row ) {
            if ( ! is_array( $row ) ) {
                \WP_CLI::error( 'Import row ' . ( $index + 1 ) . ' is not an object.' );
            }
            foreach ( [ 'date', 'flightnr', 'from', 'to' ] as $required_key ) {
                if ( ! array_key_exists( $required_key, $row ) || '' === (string) $row[ $required_key ] ) {
                    \WP_CLI::error( 'Import row ' . ( $index + 1 ) . " is missing $required_key." );
                }
            }
        }

        return $rows;
    }

    private function is_phpmyadmin_json_export( array $rows ): bool {
        return isset( $rows[0] )
            && is_array( $rows[0] )
            && isset( $rows[0]['type'] )
            && 'header' === $rows[0]['type'];
    }

    private function extract_rows_from_phpmyadmin_json_export( array $export ): array {
        foreach ( $export as $entry ) {
            if (
                is_array( $entry )
                && ( $entry['type'] ?? '' ) === 'table'
                && ( $entry['name'] ?? '' ) === 'flights'
                && isset( $entry['data'] )
                && is_array( $entry['data'] )
            ) {
                return $entry['data'];
            }
        }

        \WP_CLI::error( 'Could not find the flights table data in the phpMyAdmin JSON export.' );
    }

    private function get_flights(): array {
        $posts = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => [ 'private', 'publish', 'draft' ],
            'posts_per_page' => -1,
            'meta_key'       => $this->meta_key( 'date' ),
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );

        return array_map( [ $this, 'post_to_flight' ], $posts );
    }

    private function post_to_flight( \WP_Post $post ): array {
        $values = $this->empty_form_values();
        foreach ( array_keys( $values ) as $key ) {
            $values[ $key ] = (string) get_post_meta( $post->ID, $this->meta_key( $key ), true );
        }

        return $this->decorate_flight( $values, $post->ID );
    }

    private function decorate_flight( array $values, int $post_id = 0 ): array {
        $date = new DateTime( $values['date'] ?: 'now' );
        $aircraft_parts = preg_split( '/\s+/', trim( $values['aircraft'] ) );
        $manufacturer = $aircraft_parts[0] ?? 'Unknown';
        $aircraft_type = $this->aircraft_type( $values['aircraft'] );
        $body_type = $this->body_type( $aircraft_type, $values['aircraft'] );
        $seat = $this->seat_info( $values['seat'], $body_type );
        $airline = $this->airline_name( $values['flightnr'] );
        $route = $values['route'] ?: $values['from'] . '-' . $values['to'];

        return array_merge( $values, [
            'id'             => $post_id,
            'key'            => $this->flight_key( $values['flightnr'], $values['date'] ),
            'date_display'   => $date->format( 'Y-m-d H:i' ),
            'date_local'     => str_replace( ' ', 'T', substr( $values['date'], 0, 19 ) ),
            'date_obj'       => $date,
            'year'           => $date->format( 'Y' ),
            'route_key'      => $values['from'] . '-' . $values['to'],
            'route_display'  => $route,
            'airline'        => $airline,
            'manufacturer'   => $manufacturer ?: 'Unknown',
            'aircraft_type'  => $aircraft_type,
            'body_type'      => $body_type,
            'seat_position'  => $seat['position'],
            'seat_side'      => $seat['side'],
            'age'            => $this->aircraft_age( $values['first_flight'], $date ),
        ] );
    }

    private function posted_form_values(): array {
        $values = $this->empty_form_values();
        foreach ( $values as $key => $default ) {
            $raw = wp_unslash( $_POST[ $key ] ?? '' );
            $values[ $key ] = 'remarks' === $key ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
        }

        foreach ( [ 'flightnr', 'from', 'to', 'route', 'regnr', 'seat' ] as $key ) {
            $values[ $key ] = strtoupper( $values[ $key ] );
        }

        return $values;
    }

    private function validate_form_values( array $values, string $mode, string $original_flightnr, string $original_date ): array {
        $errors = [];
        if ( '' === $values['date'] || ! $this->parse_datetime_local( $values['date'] ) ) {
            $errors[] = 'Date and time are required.';
        }
        if ( '' === $values['flightnr'] ) {
            $errors[] = 'Flight number is required.';
        }
        if ( '' === $values['from'] ) {
            $errors[] = 'From airport is required.';
        }
        if ( '' === $values['to'] ) {
            $errors[] = 'To airport is required.';
        }
        if ( 'edit' === $mode && ( '' === $original_flightnr || '' === $original_date ) ) {
            $errors[] = 'Original flight key is missing.';
        }

        foreach ( [ 'flightnr' => 8, 'from' => 10, 'to' => 10, 'route' => 20, 'regnr' => 8, 'aircraft' => 100, 'seat' => 3 ] as $key => $max ) {
            if ( strlen( $values[ $key ] ) > $max ) {
                $errors[] = ucfirst( str_replace( '_', ' ', $key ) ) . " must be $max characters or fewer.";
            }
        }

        if ( '' !== $values['first_flight'] ) {
            $first_flight = DateTime::createFromFormat( 'Y-m-d', $values['first_flight'] );
            $date_errors = DateTime::getLastErrors();
            if ( ! $first_flight || ( $date_errors && ( $date_errors['warning_count'] || $date_errors['error_count'] ) ) ) {
                $errors[] = 'First flight must be a valid date.';
            }
        }

        if ( '' !== $values['msn'] && ! ctype_digit( $values['msn'] ) ) {
            $errors[] = 'MSN must be a whole number.';
        }

        return $errors;
    }

    private function normalize_submitted_values( array $values ): array {
        $date = $this->parse_datetime_local( $values['date'] );
        $values['date'] = $date ? $date->format( 'Y-m-d H:i:s' ) : '';
        return $values;
    }

    private function save_flight( array $values, int $post_id = 0 ) {
        $decorated = $this->decorate_flight( $values, $post_id );
        $postarr = [
            'post_type'   => self::POST_TYPE,
            'post_status' => 'private',
            'post_title'  => $this->format_title( $decorated ),
            'post_date'   => $decorated['date'],
        ];

        if ( $post_id ) {
            $postarr['ID'] = $post_id;
            $saved = wp_update_post( wp_slash( $postarr ), true );
        } else {
            $saved = wp_insert_post( wp_slash( $postarr ), true );
        }

        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        foreach ( $this->empty_form_values() as $key => $default ) {
            update_post_meta( $saved, $this->meta_key( $key ), $values[ $key ] ?? '' );
        }
        update_post_meta( $saved, $this->meta_key( 'legacy_key' ), $decorated['key'] );
        $this->assign_terms( $saved, $decorated );

        return $saved;
    }

    private function assign_terms( int $post_id, array $flight ): void {
        $terms = [
            'flight_tracker_airline'       => [ $flight['airline'] ],
            'flight_tracker_airport'       => array_filter( [ $flight['from'], $flight['to'] ] ),
            'flight_tracker_route'         => [ $flight['route_key'] ],
            'flight_tracker_aircraft_type' => [ $flight['aircraft_type'] ],
            'flight_tracker_manufacturer'  => [ $flight['manufacturer'] ],
            'flight_tracker_body_type'     => [ $flight['body_type'] ],
            'flight_tracker_year'          => [ $flight['year'] ],
            'flight_tracker_seat_position' => [ $flight['seat_position'] ],
            'flight_tracker_seat_side'     => [ $flight['seat_side'] ],
        ];

        foreach ( $terms as $taxonomy => $names ) {
            wp_set_object_terms( $post_id, array_values( array_unique( array_filter( $names ) ) ), $taxonomy, false );
        }
    }

    private function legacy_row_to_values( array $row ): array {
        return [
            'date'         => (string) $row['date'],
            'flightnr'     => (string) $row['flightnr'],
            'from'         => (string) $row['from'],
            'to'           => (string) $row['to'],
            'route'        => (string) ( $row['route'] ?? '' ),
            'regnr'        => (string) ( $row['regnr'] ?? '' ),
            'aircraft'     => (string) ( $row['aircraft'] ?? '' ),
            'seat'         => (string) ( $row['seat'] ?? '' ),
            'first_flight' => (string) ( $row['first_flight'] ?? '' ),
            'msn'          => (string) ( $row['msn'] ?? '' ),
            'remarks'      => (string) ( $row['remarks'] ?? '' ),
        ];
    }

    private function find_flight_post_id( string $flightnr, string $date ): int {
        $posts = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => [ 'private', 'publish', 'draft' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => $this->meta_key( 'legacy_key' ),
                    'value' => $this->flight_key( $flightnr, $date ),
                ],
            ],
        ] );

        return $posts ? (int) $posts[0] : 0;
    }

    private function parse_datetime_local( string $value ): ?DateTime {
        $format = strlen( $value ) > 16 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
        if ( false === strpos( $value, 'T' ) ) {
            $format = 'Y-m-d H:i:s';
        }
        $date = DateTime::createFromFormat( $format, $value );
        $date_errors = DateTime::getLastErrors();

        return $date && ! ( $date_errors && ( $date_errors['warning_count'] || $date_errors['error_count'] ) ) ? $date : null;
    }

    private function format_title( array $flight ): string {
        return trim( $flight['date'] . ' ' . $flight['flightnr'] . ' ' . $flight['from'] . '-' . $flight['to'] );
    }

    private function aircraft_type( string $aircraft ): string {
        $aircraft = trim( $aircraft );
        if ( '' === $aircraft ) {
            return 'Unknown';
        }

        $parts = preg_split( '/\s+/', $aircraft );
        $manufacturer = ucfirst( strtolower( $parts[0] ?? '' ) );
        $model = $parts[1] ?? '';

        if ( '' === $model ) {
            return $manufacturer ?: 'Unknown';
        }

        if ( false !== strpos( $model, '-' ) ) {
            $model_parts = explode( '-', $model );
            $model = $model_parts[0];
            if ( in_array( strtolower( $manufacturer ), [ 'airbus', 'boeing' ], true ) && ! empty( $model_parts[1] ) ) {
                $model .= '-' . substr( $model_parts[1], 0, in_array( $model, [ '787', 'A350' ], true ) ? 2 : 1 ) . ( 'Airbus' === $manufacturer && preg_match( '/N|NX$/', $parts[1] ) ? ' Neo' : '00' );
            }
        }

        return trim( $manufacturer . ' ' . $model );
    }

    private function body_type( string $type, string $aircraft ): string {
        $value = strtoupper( $type . ' ' . $aircraft );
        if ( preg_match( '/\b(A300|A310|A330|A340|A350|A380|B747|747|B767|767|B777|777|B787|787|DC-10|MD-11|L-1011)\b/', $value ) ) {
            return 'Widebody';
        }
        if ( preg_match( '/\b(DASH|DH8|DHC|ATR|SAAB|BN-2|ISLANDER|BEECH|TWIN OTTER|CESSNA|PIPER|FOKKER\s*50|F50)\b/', $value ) ) {
            return 'Propeller';
        }
        if ( preg_match( '/\b(E170|E175|E190|E195|EMB-145|CRJ|CANADAIR|FOKKER)\b/', $value ) ) {
            return 'Regional jet';
        }
        if ( preg_match( '/\b(A220|A318|A319|A320|A321|B707|707|B717|717|B727|727|B737|737|B757|757|DC-9|MD-8|MD-9)\b/', $value ) ) {
            return 'Narrowbody';
        }
        return 'Unknown';
    }

    private function seat_info( string $seat, string $body_type ): array {
        $seat = strtoupper( trim( $seat ) );
        if ( '' === $seat || ! preg_match( '/^(\d+)([A-Z])$/', $seat, $matches ) ) {
            return [ 'position' => 'Unknown', 'side' => 'Unknown' ];
        }

        $letter = $matches[2];
        if ( 'Widebody' === $body_type ) {
            return [
                'position' => in_array( $letter, [ 'A', 'K', 'L' ], true ) ? 'Window' : ( in_array( $letter, [ 'C', 'D', 'G', 'H' ], true ) ? 'Aisle' : 'Middle' ),
                'side'     => in_array( $letter, [ 'A', 'B', 'C' ], true ) ? 'Left' : ( in_array( $letter, [ 'D', 'E', 'F', 'G' ], true ) ? 'Center' : 'Right' ),
            ];
        }

        if ( in_array( $body_type, [ 'Regional jet', 'Propeller' ], true ) ) {
            return [
                'position' => in_array( $letter, [ 'A', 'D' ], true ) ? 'Window' : ( in_array( $letter, [ 'B', 'C' ], true ) ? 'Aisle' : 'Unknown' ),
                'side'     => in_array( $letter, [ 'A', 'B' ], true ) ? 'Left' : ( in_array( $letter, [ 'C', 'D' ], true ) ? 'Right' : 'Unknown' ),
            ];
        }

        return [
            'position' => in_array( $letter, [ 'A', 'F' ], true ) ? 'Window' : ( in_array( $letter, [ 'C', 'D' ], true ) ? 'Aisle' : ( in_array( $letter, [ 'B', 'E' ], true ) ? 'Middle' : 'Unknown' ) ),
            'side'     => in_array( $letter, [ 'A', 'B', 'C' ], true ) ? 'Left' : ( in_array( $letter, [ 'D', 'E', 'F' ], true ) ? 'Right' : 'Unknown' ),
        ];
    }

    private function aircraft_age( string $first_flight, DateTime $flight_date ): string {
        if ( '' === $first_flight ) {
            return '';
        }

        $first = DateTime::createFromFormat( 'Y-m-d', $first_flight );
        if ( ! $first ) {
            return '';
        }

        $diff = $flight_date->diff( $first );
        $years = (int) $diff->format( '%y' );
        $months = (int) $diff->format( '%m' );
        if ( $years && $months ) {
            return "{$years}y {$months}m";
        }
        return $years ? "{$years}y" : "{$months}m";
    }

    private function airline_name( string $flightnr ): string {
        $code = strtoupper( substr( $flightnr, 0, 2 ) );
        $airlines = [
            'OS' => 'Austrian Airlines',
            'BA' => 'British Airways',
            'AY' => 'Finnair',
            'AC' => 'Air Canada',
            'AF' => 'Air France',
            'U2' => 'EasyJet',
            'EI' => 'Aer Lingus',
            'KL' => 'KLM',
            'FR' => 'Ryanair',
            'LH' => 'Lufthansa',
            'LX' => 'Swiss',
            'QF' => 'Qantas',
            'JQ' => 'Jetstar',
            'UA' => 'United Airlines',
            'DE' => 'Condor',
            'TP' => 'TAP Portugal',
            'EW' => 'Eurowings',
            'TG' => 'Thai Airways',
            'W6' => 'Wizz Air',
            'BR' => 'EVA Air',
            'SN' => 'Brussels Airlines',
            'IB' => 'Iberia',
        ];

        return $airlines[ $code ] ?? ( $code ?: 'Unknown' );
    }

    private function count( array &$counts, string $key ): void {
        $key = '' === trim( $key ) ? 'Unknown' : $key;
        $counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
    }

    private function meta_key( string $key ): string {
        return '_flight_tracker_' . $key;
    }
}
