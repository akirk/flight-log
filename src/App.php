<?php

namespace FlightLog;

use DateTime;
use WpApp\BaseApp;
use WpApp\WpApp;

class App extends BaseApp {
    public const POST_TYPE = 'tracked_flight';
    public const NONCE_ACTION = 'flight_log_save_flight';
    public const NONCE_NAME = 'flight_log_nonce';
    private const REFERENCE_NAMES_OPTION = 'flight_log_reference_names';
    private const AIRPORTS_CSV_URL = 'https://davidmegginson.github.io/ourairports-data/airports.csv';
    private const AIRLINES_CSV_URL = 'https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat';

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
        'flight_log_airline'       => [ 'Airlines', 'Airline' ],
        'flight_log_airport'       => [ 'Airports', 'Airport' ],
        'flight_log_route'         => [ 'Routes', 'Route' ],
        'flight_log_aircraft_type' => [ 'Aircraft Types', 'Aircraft Type' ],
        'flight_log_manufacturer'  => [ 'Manufacturers', 'Manufacturer' ],
        'flight_log_body_type'     => [ 'Body Types', 'Body Type' ],
        'flight_log_year'          => [ 'Years', 'Year' ],
        'flight_log_seat_position' => [ 'Seat Positions', 'Seat Position' ],
        'flight_log_seat_side'     => [ 'Seat Sides', 'Seat Side' ],
    ];

    private static $instance;

    public function __construct() {
        self::$instance = $this;

        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            'app_name' => 'Flight Log',
            'my_apps'  => 'Flight Log',
        ] );

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'flight-log import-legacy', [ $this, 'cli_import_legacy' ] );
        }
    }

    public static function get_instance(): ?self {
        return self::$instance;
    }

    protected function get_url_path(): string {
        return 'flight-log';
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
                'name'          => __( 'Flights', 'flight-log' ),
                'singular_name' => __( 'Flight', 'flight-log' ),
                'add_new_item'  => __( 'Add New Flight', 'flight-log' ),
                'edit_item'     => __( 'Edit Flight', 'flight-log' ),
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
                    'name'          => __( $labels[0], 'flight-log' ),
                    'singular_name' => __( $labels[1], 'flight-log' ),
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

    public function register_rest_routes(): void {
        register_rest_route( 'flight-log/v1', '/import-legacy', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_import_legacy' ],
            'permission_callback' => static function() {
                return current_user_can( 'edit_posts' );
            },
            'args'                => [
                'rows'            => [
                    'required' => true,
                    'type'     => 'array',
                ],
                'update_existing' => [
                    'required' => false,
                    'type'     => 'boolean',
                ],
            ],
        ] );
        register_rest_route( 'flight-log/v1', '/reference-names', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_reference_names' ],
            'permission_callback' => static function() {
                return current_user_can( 'edit_posts' );
            },
            'args'                => [
                'airport_codes' => [
                    'required' => false,
                    'type'     => 'array',
                ],
                'airline_codes' => [
                    'required' => false,
                    'type'     => 'array',
                ],
            ],
        ] );
    }

    public function rest_import_legacy( \WP_REST_Request $request ) {
        $rows = $request->get_param( 'rows' );
        if ( ! is_array( $rows ) ) {
            return new \WP_Error( 'flight_log_invalid_import_rows', 'Import rows must be an array.', [ 'status' => 400 ] );
        }

        $rows = $this->normalize_import_rows( $rows );
        if ( is_wp_error( $rows ) ) {
            $rows->add_data( [ 'status' => 400 ] );
            return $rows;
        }

        return rest_ensure_response( $this->import_legacy_rows( $rows, (bool) $request->get_param( 'update_existing' ) ) );
    }

    public function rest_reference_names( \WP_REST_Request $request ) {
        $result = $this->prime_reference_names(
            (array) $request->get_param( 'airport_codes' ),
            (array) $request->get_param( 'airline_codes' )
        );

        if ( is_wp_error( $result ) ) {
            $result->add_data( [ 'status' => 502 ] );
            return $result;
        }

        return rest_ensure_response( $result );
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
            'flash'             => $this->get_flash_message(),
        ];

        if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return $state;
        }

        $action = sanitize_key( wp_unslash( $_POST['action'] ?? '' ) );
        if ( ! in_array( $action, [ 'add_flight', 'edit_flight', 'import_flights' ], true ) ) {
            return $state;
        }

        if ( 'import_flights' === $action ) {
            return $this->handle_import_submission( $state );
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
        $this->prime_reference_names_for_rows( [ $values ] );
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

    private function get_flash_message(): ?string {
        if ( isset( $_GET['updated'] ) ) {
            return 'Flight updated.';
        }
        if ( isset( $_GET['added'] ) ) {
            return 'Flight added.';
        }
        if ( isset( $_GET['imported'] ) ) {
            return sprintf(
                'Import complete: %d created, %d updated, %d skipped.',
                absint( $_GET['created'] ?? 0 ),
                absint( $_GET['updated_count'] ?? 0 ),
                absint( $_GET['skipped'] ?? 0 )
            );
        }

        return null;
    }

    private function handle_import_submission( array $state ): array {
        $state['show_form'] = true;

        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            $state['errors'][] = 'Security check failed. Please try again.';
            return $state;
        }

        $input = (string) wp_unslash( $_POST['legacy_import_json'] ?? '' );
        $rows = $this->parse_import_rows( $input );
        if ( is_wp_error( $rows ) ) {
            $state['errors'][] = $rows->get_error_message();
            return $state;
        }

        $result = $this->import_legacy_rows( $rows, ! empty( $_POST['update_existing'] ) );
        if ( ! empty( $result['errors'] ) ) {
            $state['flash'] = sprintf(
                'Import partially complete: %d created, %d updated, %d skipped.',
                $result['created'],
                $result['updated'],
                $result['skipped']
            );
            $state['errors'] = array_merge( $state['errors'], $result['errors'] );
            return $state;
        }

        wp_safe_redirect( add_query_arg( [
            'imported'      => '1',
            'created'       => $result['created'],
            'updated_count' => $result['updated'],
            'skipped'       => $result['skipped'],
        ], remove_query_arg( [ 'updated', 'added', 'imported', 'created', 'updated_count', 'skipped' ] ) ) );
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

            $this->count( $summary['airports'], $flight['from_airport'] );
            $this->count( $summary['airports'], $flight['to_airport'] );
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

        if ( is_wp_error( $rows ) ) {
            \WP_CLI::error( $rows->get_error_message() );
        }

        $result = $this->import_legacy_rows( $rows, $update_existing, $dry_run );
        foreach ( $result['errors'] as $error ) {
            \WP_CLI::warning( $error );
        }

        $prefix = $dry_run ? 'Dry run: ' : '';
        \WP_CLI::success( "{$prefix}{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped." );
    }

    private function read_import_rows_from_stdin() {
        return $this->parse_import_rows( stream_get_contents( STDIN ) );
    }

    private function parse_import_rows( string $input ) {
        if ( '' === trim( $input ) ) {
            return new \WP_Error( 'flight_log_no_import_data', 'No import data received.' );
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
                return new \WP_Error( 'flight_log_invalid_import_json', 'Invalid JSON on input line ' . ( $line_number + 1 ) . '.' );
            }

            $rows[] = $row;
        }

        return $this->normalize_import_rows( $rows );
    }

    private function normalize_import_rows( array $rows ) {
        if ( $this->is_phpmyadmin_json_export( $rows ) ) {
            $rows = $this->extract_rows_from_phpmyadmin_json_export( $rows );
            if ( is_wp_error( $rows ) ) {
                return $rows;
            }
        }

        if ( isset( $rows['date'], $rows['flightnr'] ) ) {
            $rows = [ $rows ];
        }

        foreach ( $rows as $index => $row ) {
            if ( ! is_array( $row ) ) {
                return new \WP_Error( 'flight_log_invalid_import_row', 'Import row ' . ( $index + 1 ) . ' is not an object.' );
            }
            foreach ( [ 'date', 'flightnr', 'from', 'to' ] as $required_key ) {
                if ( ! array_key_exists( $required_key, $row ) || '' === (string) $row[ $required_key ] ) {
                    return new \WP_Error( 'flight_log_missing_import_field', 'Import row ' . ( $index + 1 ) . " is missing $required_key." );
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

    private function extract_rows_from_phpmyadmin_json_export( array $export ) {
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

        return new \WP_Error( 'flight_log_missing_phpmyadmin_table', 'Could not find the flights table data in the phpMyAdmin JSON export.' );
    }

    private function import_legacy_rows( array $rows, bool $update_existing = false, bool $dry_run = false ): array {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        if ( ! $dry_run ) {
            $this->prime_reference_names_for_rows( $rows );
        }

        foreach ( $rows as $index => $row ) {
            $values = $this->legacy_row_to_values( $row );
            if ( ! $this->parse_datetime_local( $values['date'] ) ) {
                $result['errors'][] = 'Import row ' . ( $index + 1 ) . ' has an invalid date.';
                $result['skipped']++;
                continue;
            }

            $post_id = $this->find_flight_post_id( $values['flightnr'], $values['date'] );

            if ( $post_id && ! $update_existing ) {
                $result['skipped']++;
                continue;
            }

            if ( ! $dry_run ) {
                $saved = $this->save_flight( $values, $post_id );
                if ( is_wp_error( $saved ) ) {
                    $result['errors'][] = $saved->get_error_message();
                    $result['skipped']++;
                    continue;
                }
            }

            $post_id ? $result['updated']++ : $result['created']++;
        }

        return $result;
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
        $from_airport = $this->airport_name( $values['from'] );
        $to_airport = $this->airport_name( $values['to'] );
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
            'from_airport'   => $from_airport,
            'to_airport'     => $to_airport,
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
            'flight_log_airline'       => [ $flight['airline'] ],
            'flight_log_airport'       => array_filter( [ $flight['from_airport'], $flight['to_airport'] ] ),
            'flight_log_route'         => [ $flight['route_key'] ],
            'flight_log_aircraft_type' => [ $flight['aircraft_type'] ],
            'flight_log_manufacturer'  => [ $flight['manufacturer'] ],
            'flight_log_body_type'     => [ $flight['body_type'] ],
            'flight_log_year'          => [ $flight['year'] ],
            'flight_log_seat_position' => [ $flight['seat_position'] ],
            'flight_log_seat_side'     => [ $flight['seat_side'] ],
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
        $value = trim( $value );
        if ( '' === $value ) {
            return null;
        }

        foreach ( [ 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' ] as $format ) {
            $date = DateTime::createFromFormat( $format, $value );
            $date_errors = DateTime::getLastErrors();
            if ( $date && ! ( $date_errors && ( $date_errors['warning_count'] || $date_errors['error_count'] ) ) ) {
                if ( in_array( $format, [ 'Y-m-d' ], true ) ) {
                    $date->setTime( 12, 0 );
                }
                return $date;
            }
        }

        $normalized = preg_replace( '/\s+/', ' ', strtolower( $value ) );
        $normalized = preg_replace( '/\b(tdy|tod)\b/', 'today', $normalized );
        $normalized = preg_replace( '/\b(tmr|tmrw|tom)\b/', 'tomorrow', $normalized );
        $normalized = preg_replace_callback( '/(^|\s)(\d{1,2})(\d{2})(?=$|\s)/', static function( array $matches ): string {
            $hour = (int) $matches[2];
            $minute = (int) $matches[3];
            if ( $hour > 23 || $minute > 59 ) {
                return $matches[0];
            }
            return $matches[1] . sprintf( '%02d:%02d', $hour, $minute );
        }, $normalized );
        $normalized = preg_replace_callback( '/(^|\s)(\d{1,2})(?=$|\s)/', static function( array $matches ): string {
            $hour = (int) $matches[2];
            if ( $hour > 23 ) {
                return $matches[0];
            }
            return $matches[1] . sprintf( '%02d:00', $hour );
        }, $normalized );
        $normalized = preg_replace( '/\b(\d{1,2})\.(\d{1,2})\.(\d{2})(?=\D|$)/', '$1.$2.20$3', $normalized );

        $timestamp = strtotime( $normalized );
        if ( false === $timestamp ) {
            return null;
        }

        $date = new DateTime();
        $date->setTimestamp( $timestamp );
        if ( ! preg_match( '/\d{1,2}:\d{2}/', $normalized ) ) {
            $date->setTime( 12, 0 );
        }

        return $date;
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

    private function prime_reference_names_for_rows( array $rows ) {
        $airport_codes = [];
        $airline_codes = [];

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            foreach ( [ 'from', 'to' ] as $key ) {
                if ( ! empty( $row[ $key ] ) ) {
                    $airport_codes[] = (string) $row[ $key ];
                }
            }
            if ( ! empty( $row['flightnr'] ) ) {
                $airline_codes[] = substr( (string) $row['flightnr'], 0, 2 );
            }
        }

        return $this->prime_reference_names( $airport_codes, $airline_codes );
    }

    private function prime_reference_names( array $airport_codes, array $airline_codes ) {
        $cache = $this->reference_names();
        $airport_codes = $this->missing_reference_codes( $airport_codes, $cache['airports'] );
        $airline_codes = $this->missing_reference_codes( $airline_codes, $cache['airlines'] );

        if ( $airport_codes ) {
            $airport_names = $this->download_airport_names( $airport_codes );
            if ( is_wp_error( $airport_names ) ) {
                return $airport_names;
            }
            foreach ( $airport_codes as $code ) {
                if ( ! isset( $airport_names[ $code ] ) ) {
                    $airport_names[ $code ] = $code;
                }
            }
            $cache['airports'] = array_merge( $cache['airports'], $airport_names );
        }

        if ( $airline_codes ) {
            $airline_names = $this->download_airline_names( $airline_codes );
            if ( is_wp_error( $airline_names ) ) {
                return $airline_names;
            }
            foreach ( $airline_codes as $code ) {
                if ( ! isset( $airline_names[ $code ] ) ) {
                    $airline_names[ $code ] = $this->fallback_airline_name( $code );
                }
            }
            $cache['airlines'] = array_merge( $cache['airlines'], $airline_names );
        }

        update_option( self::REFERENCE_NAMES_OPTION, $cache, false );

        return [
            'airports' => $this->reference_subset( $cache['airports'], $airport_codes ),
            'airlines' => $this->reference_subset( $cache['airlines'], $airline_codes ),
        ];
    }

    private function reference_names(): array {
        $cache = get_option( self::REFERENCE_NAMES_OPTION, [] );

        return [
            'airports' => isset( $cache['airports'] ) && is_array( $cache['airports'] ) ? $cache['airports'] : [],
            'airlines' => isset( $cache['airlines'] ) && is_array( $cache['airlines'] ) ? $cache['airlines'] : [],
        ];
    }

    private function missing_reference_codes( array $codes, array $known ): array {
        $codes = array_unique( array_filter( array_map( static function( $code ) {
            return strtoupper( trim( (string) $code ) );
        }, $codes ) ) );

        return array_values( array_filter( $codes, static function( string $code ) use ( $known ): bool {
            return ! isset( $known[ $code ] );
        } ) );
    }

    private function reference_subset( array $names, array $codes ): array {
        $subset = [];
        foreach ( $codes as $code ) {
            if ( isset( $names[ $code ] ) ) {
                $subset[ $code ] = $names[ $code ];
            }
        }
        return $subset;
    }

    private function download_airport_names( array $codes ) {
        $response = wp_remote_get( self::AIRPORTS_CSV_URL, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return new \WP_Error( 'flight_log_airports_download_failed', 'Could not download airport names.' );
        }

        $body = wp_remote_retrieve_body( $response );
        $lines = preg_split( '/\R/', $body );
        $header = str_getcsv( (string) array_shift( $lines ) );
        $columns = array_flip( $header );
        $names = [];
        $wanted = array_flip( $codes );

        foreach ( $lines as $line ) {
            if ( '' === trim( $line ) ) {
                continue;
            }

            $row = str_getcsv( $line );
            foreach ( [ 'iata_code', 'icao_code', 'ident', 'gps_code', 'local_code' ] as $column ) {
                $code = strtoupper( trim( (string) ( $row[ $columns[ $column ] ?? -1 ] ?? '' ) ) );
                if ( '' !== $code && isset( $wanted[ $code ] ) && ! isset( $names[ $code ] ) ) {
                    $name = (string) ( $row[ $columns['name'] ?? -1 ] ?? '' );
                    $names[ $code ] = trim( $code . ' - ' . $name );
                }
            }
        }

        return $names;
    }

    private function download_airline_names( array $codes ) {
        $response = wp_remote_get( self::AIRLINES_CSV_URL, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return new \WP_Error( 'flight_log_airlines_download_failed', 'Could not download airline names.' );
        }

        $names = [];
        $wanted = array_flip( $codes );
        foreach ( preg_split( '/\R/', wp_remote_retrieve_body( $response ) ) as $line ) {
            if ( '' === trim( $line ) ) {
                continue;
            }

            $row = str_getcsv( $line );
            $code = strtoupper( trim( (string) ( $row[3] ?? '' ) ) );
            if ( '' !== $code && isset( $wanted[ $code ] ) && ! isset( $names[ $code ] ) ) {
                $names[ $code ] = (string) ( $row[1] ?? $code );
            }
        }

        return $names;
    }

    private function airport_name( string $code ): string {
        $code = strtoupper( trim( $code ) );
        $cache = $this->reference_names();
        return $cache['airports'][ $code ] ?? ( $code ?: 'Unknown' );
    }

    private function airline_name( string $flightnr ): string {
        $code = strtoupper( substr( $flightnr, 0, 2 ) );
        $cache = $this->reference_names();
        if ( isset( $cache['airlines'][ $code ] ) ) {
            return $cache['airlines'][ $code ];
        }

        return $this->fallback_airline_name( $code );
    }

    private function fallback_airline_name( string $code ): string {
        $code = strtoupper( trim( $code ) );
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
        return '_flight_log_' . $key;
    }
}
