# Flight Log

- Contributors: akirk
- Tags: travel, flights, aviation, logbook, statistics
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 1.0.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Log the flights you take and see where you have been: routes, airlines, aircraft and airports, summarized in a private app on your own site.

## Description

Flight Log turns your WordPress into a personal flight logbook. It adds a standalone app at `/flight-log/` — a single, self-contained page that is not part of your public site and requires you to be logged in — where you record every flight you take and get an overview of your flying in return.

Adding a flight takes one short form: date and time, flight number, departure and arrival airport, and optionally seat, route, registration, aircraft, first flight date, MSN and free-text remarks. The date field is forgiving and accepts several everyday formats, so you can type `today 9:30` as readily as a full timestamp. Flights dated in the future are counted separately as planned, so the log doubles as a list of upcoming trips.

From those entries the app derives everything else. Airport and airline codes are resolved into names, aircraft strings are split into manufacturer, type and body type, seat designators are split into row position and side, and each flight is filed under its year and route. Aircraft age is calculated from the first flight date where you supplied one.

The dashboard shows totals for logged flights, planned flights, distinct airports, routes and aircraft, plus ranked breakdowns by airline, airport, route, aircraft type, body type and year. Every one of those breakdowns is clickable and filters the flight table below it, and there is a free-text search across the whole log. Flights can be edited or deleted from the same form.

Everything is stored with plain WordPress building blocks: a `tracked_flight` custom post type with REST-enabled post meta, and taxonomies for airlines, airports, routes, aircraft types, manufacturers, body types, years, seat positions and seat sides. No custom database tables are created, so removing the plugin leaves your database as it was.

Airport and airline names are looked up on demand from two public open-data reference files — the OurAirports airport dataset and the OpenFlights airline dataset. Only the codes you actually use are looked up, the results are cached in a single option, and the lookup happens on the server; nothing on the page is loaded from a remote host.

If the WordPress Abilities API is available, Flight Log also registers four abilities — get a summary, search flights, save a flight and delete a flight — so an AI assistant on your site can answer questions about your flying and add entries for you.

Flight Log is built on [WpApp](https://github.com/akirk/wp-app), a small framework for standalone apps inside WordPress.

### Features

- Standalone WordPress app at `/flight-log/`, login required and kept out of your public site.
- Custom `tracked_flight` post type with REST-enabled flight metadata.
- Taxonomies for airlines, airports, routes, aircraft types, manufacturers, body types, years, seat positions and seat sides.
- Flight entry and editing UI with dashboard summaries, a searchable table and quick filters.
- Automatic airport and airline name resolution from public open-data reference files, cached locally.
- Abilities API integration so an AI assistant can query and update the log.

### Try it in WordPress Playground

[Launch Flight Log in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/flight-log/refs/heads/main/blueprint.json) · [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/flight-log/refs/heads/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/flight-log/refs/heads/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## Installation

1. Upload the `flight-log` directory to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Visit `/flight-log/` on your site.

To work on the plugin from a git checkout, install the dependencies first:

```bash
composer install
```

## Frequently Asked Questions

### Is my flight log public?
No. The app requires you to be logged in, and the `tracked_flight` post type is not public, so entries do not appear on your site or in its feeds.

### Does this plugin create custom database tables?
No. Flights are stored as a custom post type with post meta, and their attributes as taxonomy terms. Removing the plugin leaves your database as slim as before.

### Which date formats does the date field accept?
Several everyday ones, including `today 9:30`, `17.7.26 930` and `2026-07-17 09:30`.

### Where do the airport and airline names come from?
From two public open-data reference files: the OurAirports airport dataset and the OpenFlights airline dataset. Only the codes present in your log are looked up, the request is made from your server, and the resulting names are cached in a WordPress option.

### Can I use an AI assistant with my flight log?
Yes, if the WordPress Abilities API is available. Flight Log registers abilities to summarize the log, search flights, and add or delete a flight.

## Screenshots

1. The Flight Log dashboard: summary statistics, quick filters and the searchable flight table.

## Changelog

### 1.0.0
- Initial release.
