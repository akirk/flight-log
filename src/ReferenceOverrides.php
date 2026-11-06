<?php
namespace FlightLog;

class ReferenceOverrides {
    private const META_KEY = '_flight_log_reference_overrides';

    public static function settings(): array {
        $saved = get_user_meta( get_current_user_id(), self::META_KEY, true );
        return is_array( $saved ) ? array_merge( [ 'airports' => [], 'airlines' => [] ], $saved ) : [ 'airports' => [], 'airlines' => [] ];
    }

    public static function table_rows( array $cache, array $values, array $used ): array {
        $groups = [ 'airports' => [ 'missing' => [], 'known' => [] ], 'airlines' => [ 'missing' => [], 'known' => [] ] ];
        foreach ( $groups as $type => $unused ) {
            $overrides = [];
            foreach ( $values[ $type ] as $code => $entry ) {
                $code = $entry['code'] ?? (string) $code;
                $overrides[ $code ] = $entry;
            }
            $codes = array_unique( array_merge( array_keys( $cache[ $type ] ?? [] ), array_keys( $overrides ), $used[ $type ] ?? [] ) );
            sort( $codes );
            foreach ( $codes as $code ) {
                if ( '' === (string) $code ) {
                    continue;
                }
                $name = $cache[ $type ][ $code ] ?? '';
                $name = 'airports' === $type ? preg_replace( '/^' . preg_quote( $code, '/' ) . ' - /', '', $name ) : $name;
                $country = $cache['airport_countries'][ $code ] ?? '';
                $entry = $overrides[ $code ] ?? [];
                $missing = '' === $name || $name === (string) $code || ( 'airports' === $type && '' === $country );
                // Keep saved corrections visible alongside unresolved entries.
                $group = $missing || $entry ? 'missing' : 'known';
                $groups[ $type ][ $group ][] = array_merge( [ 'code' => (string) $code ], $entry, [
                    'reference_name' => $name === (string) $code ? '' : $name,
                    'reference_country' => $country,
                ] );
            }
        }
        return $groups;
    }

    public static function validate( array $values ): array {
        $result = [ 'airports' => [], 'airlines' => [] ];
        foreach ( $result as $type => $unused ) {
            if ( ! isset( $values[ $type ] ) || ! is_array( $values[ $type ] ) ) {
                continue;
            }
            foreach ( $values[ $type ] as $row ) {
                if ( ! is_array( $row ) ) {
                    throw new \InvalidArgumentException( 'Invalid reference entry.' );
                }
                $entry = [];
                foreach ( [ 'code', 'name', 'country', 'icao' ] as $field ) {
                    if ( isset( $row[ $field ] ) && ! is_string( $row[ $field ] ) ) {
                        throw new \InvalidArgumentException( 'Invalid reference field.' );
                    }
                    $entry[ $field ] = sanitize_text_field( $row[ $field ] ?? '' );
                }
                $code = strtoupper( trim( $entry['code'] ) );
                $fields = [ 'name' => $entry['name'] ];
                $extra = 'airports' === $type ? 'country' : 'icao';
                $fields[ $extra ] = strtoupper( trim( $entry[ $extra ] ) );
                if ( ! array_filter( $fields, 'strlen' ) ) {
                    continue;
                }
                if ( ! preg_match( 'airports' === $type ? '/^[A-Z]{3,4}$/' : '/^[A-Z0-9]{2}$/', $code ) ) {
                    throw new \InvalidArgumentException( 'Use a 3-letter IATA or 4-letter ICAO airport code, or a 2-character IATA airline code.' );
                }
                if ( $fields[ $extra ] && ! preg_match( 'country' === $extra ? '/^[A-Z]{2}$/' : '/^[A-Z]{3}$/', $fields[ $extra ] ) ) {
                    throw new \InvalidArgumentException( 'Use a 2-letter country code or a 3-letter airline ICAO code.' );
                }
                if ( isset( $result[ $type ][ $code ] ) ) {
                    throw new \InvalidArgumentException( 'Each code can only have one override.' );
                }
                $result[ $type ][ $code ] = $fields;
            }
            ksort( $result[ $type ] );
        }
        return $result;
    }

    public static function handle_submission(): array {
        $values = self::settings();
        $error = '';
        if ( isset( $_POST['flight_log_save_references'] ) ) {
            if ( ! is_user_logged_in() ) {
                wp_die( 'Please log in to save settings.', '', [ 'response' => 403 ] );
            }
            check_admin_referer( 'flight_log_save_references' );
            $submitted = isset( $_POST['references'] ) && is_array( $_POST['references'] ) ? map_deep( wp_unslash( $_POST['references'] ), 'sanitize_text_field' ) : [];
            try {
                $values = self::validate( $submitted );
                $saved = update_user_meta( get_current_user_id(), self::META_KEY, $values );
                if ( false === $saved && self::settings() !== $values ) {
                    throw new \InvalidArgumentException( 'Could not save overrides. Please try again.' );
                }
                wp_safe_redirect( add_query_arg( 'saved', '1', home_url( '/flight-log/reference-data/' ) ) );
                exit;
            } catch ( \InvalidArgumentException $exception ) {
                $error = $exception->getMessage();
                // Keep submitted rows visible so validation errors do not discard edits.
                $values = [ 'airports' => [], 'airlines' => [] ];
                foreach ( $values as $type => $unused ) {
                    foreach ( (array) ( $submitted[ $type ] ?? [] ) as $row ) {
                        if ( is_array( $row ) ) {
                            $values[ $type ][] = array_filter( $row, 'is_string' );
                        }
                    }
                }
            }
        }
        return [ 'values' => $values, 'error' => $error ];
    }
}
