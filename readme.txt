=== Sales by State Report for Charitable ===
Contributors: BusinessBloomer
Donate link: https://salesbystate.com/
Tags: sales-report, sales-by-state, charitable, donations, analytics
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See a yearly breakdown of Charitable donations by state / county / province for a given country, filterable by donation status.

== Description ==

Sales by State Report for Charitable adds a report showing donations grouped by state, county or province, for a chosen year and a chosen set of donation statuses.

It appears under **Charitable → Donations by State**.

It answers the question territory planning actually asks: how much did each state donate in a given year, counting only the donations that matter.

This plugin is a Charitable extension. It requires [Charitable](https://wordpress.org/plugins/charitable/) to be installed and active.

Documentation: [salesbystate.com](https://salesbystate.com/)

= What the report shows =

* Donations for every state in the selected country
* A summary of that amount across all states
* Sortable columns and paginated results
* States with no donations, shown as zero rather than hidden

= Filters =

* **Country** — United States, Canada, and the United Kingdom. Defaults to Charitable's country setting.
* **Year** — a rolling list that starts ten years back and gains a year each January without dropping one. Defaults to the current year.
* **Donation status** — a checkbox list of Charitable donation statuses. Defaults to Paid (`charitable-completed`).

= How the figures are calculated =

The figure is the donated amount Charitable stores in its campaign donations table. Charitable core does not add tax, shipping, or IRS withholding at donation time, so there is no separate net vs gross column.

Optional add-ons such as Gift Aid or fee coverage can change what the donor is charged. Those extras are not stored on the core donation amount and are not included here.

Refunds are not modelled as separate records. A donation that has been refunded is controlled by the status filter.

= Performance =

Donations for a whole year are answered by one indexed query that returns one row per state. The response size does not grow with the number of donations.

= Data and privacy =

The plugin creates one custom database table holding, per donation: the donation ID, donation status, creation and paid dates, donor country and state codes, currency, and the donation, tax, shipping and net totals. It stores no names, addresses, email addresses or any other personal data.

Nothing is sent anywhere. The plugin makes no external HTTP requests, includes no third-party services, and collects no analytics or telemetry.

Deleting the plugin removes the table and its options.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/sales-by-state-report-for-charitable`, or install it through the Plugins screen.
2. Activate the plugin. Charitable must already be installed and active.
3. Go to **Charitable → Donations by State**.

On a site that already has donations, those donations are read into the report table once. This starts on its own when you open the report. If it has not finished, a progress bar shows how far along it is.

== Frequently Asked Questions ==

= The report shows zeros but I have donations. =

Your existing donations are still being read into the report table. Open the report and the progress bar will show how far along it is. It continues on its own; you can leave the page.

If only Paid is selected, tick any other statuses that should count.

= Which address does it group by? =

The donor address on the donation. Charitable does not have a shipping address.

= Are refunds deducted? =

The status filter decides whether a donation counts. Refunded donations are excluded unless you tick Refunded.

= Which date does the year filter use? =

The date the donation was paid (Paid and Refunded), falling back to the date it was created.

= Can I change the default donation status? =

Yes, with the `sbsch_default_statuses` filter.

= Where can I get support? =

Use the [WordPress.org support forum](https://wordpress.org/support/plugin/sales-by-state-report-for-charitable/) for this plugin.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
