<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
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
    wp_app_enqueue_script(
        'flight-log-providers',
        plugins_url( 'assets/js/providers.js', dirname( __DIR__ ) . '/flight-log.php' ),
        [],
        (string) filemtime( dirname( __DIR__ ) . '/assets/js/providers.js' ),
        true,
        'flight-log'
    );
    wp_app_enqueue_script(
        'flight-log-settings',
        plugins_url( 'assets/js/settings.js', dirname( __DIR__ ) . '/flight-log.php' ),
        [ 'flight-log-providers' ],
        (string) filemtime( dirname( __DIR__ ) . '/assets/js/settings.js' ),
        true,
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
    <section class="panel settings-panel" aria-labelledby="provider-settings-title">
        <h2 id="provider-settings-title">Provider links</h2>
        <p>Choose where flight numbers and aircraft registrations link. Links open in a new tab.</p>
        <div class="settings-fields">
            <div class="field"><label for="provider-flightnr">Flight numbers</label><select id="provider-flightnr"></select></div>
            <div class="field"><label for="provider-regnr">Aircraft registrations</label><select id="provider-regnr"></select></div>
        </div>
        <p id="provider-settings-status" role="status">Settings are saved automatically in this browser.</p>
    </section>
</main>
<?php wp_app_body_close(); ?>
</body>
</html>
