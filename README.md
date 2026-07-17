# Flight Log

A private flight log and statistics app for WordPress, powered by [WpApp](https://github.com/akirk/wp-app).

Flight Log adds a standalone `/flight-log/` app for logging flights, browsing aircraft and route history, and reviewing summary statistics from WordPress.

## Try it in WordPress Playground

[Launch Flight Log in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/flight-log/refs/heads/main/blueprint.json)

## Features

- Standalone WordPress app at `/flight-log/`.
- Custom `tracked_flight` post type with REST-enabled flight metadata.
- Taxonomies for airlines, airports, routes, aircraft types, manufacturers, years, seat positions, and related flight dimensions.
- Flight entry and editing UI with dashboard summaries, searchable table, and quick filters.
- WP-CLI legacy importer for one-time migration from older JSON exports.

## Local setup

Install dependencies:

```bash
composer install
```

Activate the plugin in WordPress, then visit:

```text
/flight-log/
```

## Legacy import

The one-time importer reads JSON from STDIN and does not connect to the legacy database itself.

It accepts:

- phpMyAdmin JSON exports containing the `flights` table.
- A JSON array of flight rows.
- Newline-delimited JSON flight rows.

With a phpMyAdmin JSON export:

```bash
wp --url=alex.kirk.at flight-log import-legacy --dry-run < flights.json
wp --url=alex.kirk.at flight-log import-legacy < flights.json
```

Or by piping rows from MySQL:

```bash
mysql --user=LEGACY_DB_USER --password --host=localhost --batch --raw --skip-column-names alex \
  -e "SELECT JSON_OBJECT('flightnr', flightnr, 'date', DATE_FORMAT(date, '%Y-%m-%d %H:%i:%s'), 'from', \`from\`, 'to', \`to\`, 'route', route, 'regnr', regnr, 'aircraft', aircraft, 'first_flight', first_flight, 'msn', msn, 'seat', seat, 'remarks', remarks) FROM flights ORDER BY date ASC" \
  | wp --url=alex.kirk.at flight-log import-legacy
```

Add `--dry-run` to the WP-CLI command to preview counts without creating posts.
