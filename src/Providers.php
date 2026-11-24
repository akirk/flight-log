<?php
namespace FlightLog;

class Providers {
    private const META_KEY = '_flight_log_link_providers';

    public static function choices(): array {
        return [
            'flightnr' => [ 'none' => 'No link', 'flightradar24' => 'Flightradar24', 'flightaware' => 'FlightAware' ],
            'regnr' => [ 'none' => 'No link', 'airfleets' => 'Airfleets', 'flightradar24' => 'Flightradar24', 'flightaware' => 'FlightAware' ],
        ];
    }

    public static function settings(): array {
        $saved = get_user_meta( get_current_user_id(), self::META_KEY, true );
        return self::validate( is_array( $saved ) ? $saved : [] );
    }

    public static function validate( array $values ): array {
        $settings = [ 'flightnr' => 'flightradar24', 'regnr' => 'airfleets' ];
        foreach ( self::choices() as $key => $choices ) {
            if ( isset( $values[ $key ] ) && is_string( $values[ $key ] ) && isset( $choices[ $values[ $key ] ] ) ) {
                $settings[ $key ] = $values[ $key ];
            }
        }
        return $settings;
    }

    public static function handle_submission(): void {
        if ( ! isset( $_POST['providers'] ) || ! is_array( $_POST['providers'] ) ) {
            return;
        }
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Please log in to save settings.', 'flight-log' ), '', [ 'response' => 403 ] );
        }
        check_admin_referer( 'flight_log_save_providers' );
        $values = map_deep( wp_unslash( $_POST['providers'] ), 'sanitize_text_field' );
        $settings = self::validate( $values );
        $result = update_user_meta( get_current_user_id(), self::META_KEY, $settings );
        if ( false === $result && self::settings() !== $settings ) {
            wp_die( esc_html__( 'Could not save provider settings. Please try again.', 'flight-log' ) );
        }
        wp_safe_redirect( add_query_arg( 'saved', '1', home_url( '/flight-log/settings/' ) ) );
        exit;
    }

    public static function links( array $flight ): array {
        $links = array_fill_keys( [ 'flightnr', 'regnr' ], [ 'url' => '', 'provider' => '' ] );
        foreach ( self::settings() as $key => $provider ) {
            $identifier = strtoupper( preg_replace( '/\s+/', '', (string) ( $flight[ $key ] ?? '' ) ) );
            if ( '' === $identifier || 'none' === $provider ) {
                continue;
            }
            if ( 'flightnr' === $key ) {
                $identifier = preg_replace( '/^([A-Z0-9]{2}|[A-Z]{3})0+(?=\d)/', '$1', $identifier );
            }
            if ( 'flightradar24' === $provider ) {
                $url = 'https://www.flightradar24.com/data/' . ( 'flightnr' === $key ? 'flights/' : 'aircraft/' ) . rawurlencode( strtolower( $identifier ) );
            } elseif ( 'airfleets' === $provider ) {
                $url = 'https://www.airfleets.net/recherche/?key=' . rawurlencode( strtolower( $identifier ) );
            } else {
                if ( 'flightnr' === $key && preg_match( '/^([A-Z0-9]{2})(\d.*)$/', $identifier, $parts ) ) {
                    $codes = self::airline_codes();
                    foreach ( ReferenceOverrides::settings()['airlines'] as $code => $override ) {
                        if ( ! empty( $override['icao'] ) ) {
                            $codes[ $code ] = $override['icao'];
                        }
                    }
                    if ( empty( $codes[ $parts[1] ] ) ) {
                        continue;
                    }
                    $identifier = $codes[ $parts[1] ] . $parts[2];
                }
                $url = 'https://www.flightaware.com/live/flight/' . rawurlencode( str_replace( '-', '', $identifier ) );
            }
            $links[ $key ] = [ 'url' => $url, 'provider' => self::choices()[ $key ][ $provider ] ];
        }
        return $links;
    }

    private static function airline_codes(): array {
        static $codes;
        if ( isset( $codes ) ) {
            return $codes;
        }
        $cached = get_transient( 'flight_log_airline_icao_codes' );
        if ( is_array( $cached ) ) {
            $codes = $cached;
            return $codes;
        }
        // The same OpenFlights reference dataset used for airline names.
        $response = wp_remote_get( 'https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat', [ 'timeout' => 10 ] ); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Server-side reference data.
        $codes = [];
        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            foreach ( preg_split( '/\R/', wp_remote_retrieve_body( $response ) ) as $line ) {
                $row = str_getcsv( $line, ',', '"', '\\' );
                if ( preg_match( '/^[A-Z0-9]{2}$/', $row[3] ?? '' ) && preg_match( '/^[A-Z]{3}$/', $row[4] ?? '' ) ) {
                    if ( ! isset( $codes[ $row[3] ] ) || 'Y' === ( $row[7] ?? '' ) ) {
                        $codes[ $row[3] ] = $row[4];
                    }
                }
            }
        }
        set_transient( 'flight_log_airline_icao_codes', $codes, $codes ? WEEK_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
        return $codes;
    }
}
