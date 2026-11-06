<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
\FlightLog\Providers::handle_submission();
$flight_log_provider_settings = \FlightLog\Providers::settings();
?><!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_the_title( 'Settings — Flight Log' ); ?></title>
    <?php
    wp_app_enqueue_style(
        'flight-log-index',
        plugins_url( 'assets/css/index.css', dirname( __DIR__ ) . '/flight-log.php' ),
        [],
        (string) filemtime( dirname( __DIR__ ) . '/assets/css/index.css' ),
        'flight-log'
    );
    wp_app_head();
    ?>
</head>
<body>
<?php wp_app_body_open(); ?>
<main class="app-shell">
    <header class="page-header">
        <div>
            <p class="eyebrow">Flight log</p>
            <h1>Settings</h1>
        </div>
        <a class="button secondary" href="<?php echo esc_url( home_url( '/flight-log/' ) ); ?>">Back to flights</a>
    </header>
        <?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice. ?>
            <p class="notice" role="status">Settings saved.</p>
        <?php endif; ?>
    <form method="post" class="settings-form" aria-labelledby="provider-settings-title">
        <div class="panel settings-panel">
        <h2 id="provider-settings-title">Provider links</h2>
        <p>Choose where flight numbers and aircraft registrations link. Links open in a new tab.</p>
        <?php wp_nonce_field( 'flight_log_save_providers' ); ?>
        <div class="settings-fields">
            <?php foreach ( \FlightLog\Providers::choices() as $flight_log_key => $flight_log_choices ) : ?>
                <div class="field">
                    <label for="provider-<?php echo esc_attr( $flight_log_key ); ?>"><?php echo esc_html( 'flightnr' === $flight_log_key ? 'Flight numbers' : 'Aircraft registrations' ); ?></label>
                    <select id="provider-<?php echo esc_attr( $flight_log_key ); ?>" name="providers[<?php echo esc_attr( $flight_log_key ); ?>]">
                        <?php foreach ( $flight_log_choices as $flight_log_id => $flight_log_label ) : ?>
                            <option value="<?php echo esc_attr( $flight_log_id ); ?>" <?php selected( $flight_log_provider_settings[ $flight_log_key ], $flight_log_id ); ?>><?php echo esc_html( $flight_log_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
        <button type="submit" class="button">Save settings</button>
    </form>
</main>
<?php wp_app_body_close(); ?>
</body>
</html>
