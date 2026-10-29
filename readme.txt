=== SystemPAK Black Friday 1p a Box ===
Contributors: systempak
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later

Black Friday “1p a Box” promotion for SystemPAK WooCommerce.

== Description ==

* £0.01 promotional price on flagged products (overrides Tier Pricing Table while active)
* Max 4 boxes per promotional product
* £10.95 cart fee per promotional box (alongside existing Flexible Shipping / regional / pallet charges)
* UK shipping destination only
* Coupon and BOGO exclusion on promotional lines
* Scheduled start/end (Europe/London by default)
* Visibility: Off / Admin testing only (default) / Live

== Documentation ==

In WP Admin go to **WooCommerce → Black Friday 1p → How to use** for the full guide (assigning products, testing, go-live, VAT, troubleshooting). Also linked as **How to use** on the Plugins screen.

== Setup ==

1. Activate the plugin.
2. Leave Visibility on **Admin testing only** until signed off.
3. Assign products via the **Black Friday 1p Offer** product category only.
4. Create a page containing `[spbf_collection]` and select it under WooCommerce → Black Friday 1p.
5. Confirm VAT treatment of the 1p price and £10.95 fee with the client.
6. Switch Visibility to **Live** for the campaign window.

== Shortcodes ==

* `[spbf_collection]` — promotional product grid + rules copy

== Important ==

Do not set Visibility to Live until staging tests (spec Tests 1–15) have passed.
