# Flight Tracker

A WordPress app powered by [WpApp](https://github.com/akirk/wp-app).

## Legacy import

The one-time importer reads JSON from STDIN and does not connect to the legacy database itself.

It accepts:

- phpMyAdmin JSON exports containing the `flights` table.
- A JSON array of flight rows.
- Newline-delimited JSON flight rows.

With a phpMyAdmin JSON export:

```bash
wp --url=alex.kirk.at flight-tracker import-legacy --dry-run < flights.json
wp --url=alex.kirk.at flight-tracker import-legacy < flights.json
```

Or by piping rows from MySQL:

```bash
mysql --user=LEGACY_DB_USER --password --host=localhost --batch --raw --skip-column-names alex \
  -e "SELECT JSON_OBJECT('flightnr', flightnr, 'date', DATE_FORMAT(date, '%Y-%m-%d %H:%i:%s'), 'from', \`from\`, 'to', \`to\`, 'route', route, 'regnr', regnr, 'aircraft', aircraft, 'first_flight', first_flight, 'msn', msn, 'seat', seat, 'remarks', remarks) FROM flights ORDER BY date ASC" \
  | wp --url=alex.kirk.at flight-tracker import-legacy
```

Add `--dry-run` to the WP-CLI command to preview counts without creating posts.
