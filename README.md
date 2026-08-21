# Flight Log

A private flight log and statistics app for WordPress, powered by [WpApp](https://github.com/akirk/wp-app).

Flight Log adds a standalone `/flight-log/` app for logging flights, browsing aircraft and route history, and reviewing summary statistics from WordPress.

## Try it in WordPress Playground

[Launch Flight Log in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/flight-log/refs/heads/main/blueprint.json) · [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/flight-log/refs/heads/main/demo.json)

## Features

- Standalone WordPress app at `/flight-log/`.
- Custom `tracked_flight` post type with REST-enabled flight metadata.
- Taxonomies for airlines, airports, routes, aircraft types, manufacturers, years, seat positions, and related flight dimensions.
- Flight entry and editing UI with dashboard summaries, searchable table, and quick filters.

## Local setup

Install dependencies:

```bash
composer install
```

Activate the plugin in WordPress, then visit:

```text
/flight-log/
```

