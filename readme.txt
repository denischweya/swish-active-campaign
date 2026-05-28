=== Swish Active Campaign ===
Contributors: denischweya
Tags: activecampaign, popup, lead magnet, woocommerce, email marketing, newsletter
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ActiveCampaign integration: lead-magnet popups (CPT + custom blocks) and a "Save Trip" floating button for WooCommerce products.

== Description ==

Swish Active Campaign is a custom WordPress plugin that gives a site two ActiveCampaign-powered features:

* **Lead-magnet popups** — manage popups as a Custom Post Type, edited with the block editor. Two custom blocks ship: a `Swish Popup` container with InnerBlocks (image, columns, heading, paragraph, plus the form), and a `Swish AC Form` child block. Each popup has its own targeting (URL patterns, post types, the homepage, exclude lists), trigger (time delay, scroll %, exit intent, click selector), frequency cap (cookie-based) and ActiveCampaign mapping (list dropdown + tag picker, both sourced live from the API).

* **Save Trip button** — a floating bookmark button on WooCommerce product pages. Clicking opens a popup that subscribes the visitor in ActiveCampaign and adds two tags: a base tag (e.g. "Saved Trip") and a dynamic per-product tag (e.g. `trip:hawaii-2026`). The button's screen position and show-trigger (immediate, after time delay, or after scroll %) are configurable.

All submissions go server-side through the WordPress REST API to ActiveCampaign v3, so the API key never reaches the browser. List names and tag names are cached for 5 minutes via transients.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` (or install the zip via **Plugins → Add New → Upload**).
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings → Swish AC** and fill in:
   * ActiveCampaign API URL (e.g. `https://youraccount.api-us1.com`)
   * ActiveCampaign API Key
   * Default list
4. Click **Test Connection**. This must return "Connected as …" before anything else will work.
5. *(Optional, for Save Trip)* set a list override, base tag, per-product tag pattern, popup copy, button position, and show-trigger.
6. *(For popups)* create posts under **Swish Popups → Add New**. Each popup's targeting/trigger/AC settings live in the block's right-hand sidebar.

== Frequently Asked Questions ==

= How do I preview a popup repeatedly without dismissing it? =

Append `?swish_preview=1` to the page URL — this bypasses the localStorage frequency cap so you can re-test as many times as needed. Append `?swish_debug=1` to also stream matching/trigger info to the browser console.

= Why isn't my popup showing? =

In order, check:
1. Is the popup post **Published** (not draft)?
2. Does the targeting actually match the URL? URL patterns are case-insensitive and use `*` as a wildcard. The leading slash matters: `/trips/*` matches `/trips/foo`, but `trips/*` does not.
3. Is the "Show to" filter excluding you (logged-in vs logged-out)?
4. Is a localStorage frequency-cap entry still set from a prior dismissal? Open DevTools → Application → Local Storage and delete any `swish_popup_*` keys.

When `WP_DEBUG` is enabled, the server logs to `debug.log` which popups matched and which were skipped (with reasons).

= Why does the form submission fail with "ActiveCampaign returned HTTP 4xx"? =

The error message includes the AC endpoint that failed — e.g. `... on /api/3/contactLists` means the list ID being used doesn't exist in your AC account. Open **Settings → Swish AC** and re-select the list from the dropdown.

= Can I customize the popup design? =

Yes. The Swish Popup block exposes:
* Width slider (320–900 px)
* Padding via per-side BoxControl (px/%/em/rem)
* Image with three layouts: image on top, two-column (image left), or image as background with opacity
* Focal point picker + image height (for cropped layouts)
* Accent color + success message
* Core color and other block-supports

The Swish AC Form child block exposes:
* Optional name field (with required toggle)
* Field-labels toggle (placeholders always render either way)
* Submit button label, alignment (Left / Center / Right / Full width)
* Submit button background, text color, border, border radius

= Where do I find which AC list is being used? =

In the popup block's **ActiveCampaign** panel — the List dropdown is sourced live from your AC account (each option shows `Name (#id)`). The same dropdown appears in the plugin Settings for the default list and the Save Trip override.

= Is the AC API key exposed to the browser? =

No. Form submissions hit `POST /wp-json/swish-ac/v1/submit`, which proxies to the AC v3 API server-side with the stored credentials.

== Changelog ==

= 0.1.0 - 2026-05-28 =
Initial release.

**Plugin core**

* Plugin scaffold, settings page, AC v3 REST client.
* "Test Connection" button hits `/users/me` to verify credentials.
* Single `POST /wp-json/swish-ac/v1/submit` endpoint handles both popup and Save Trip submissions.
* AC list and tag dropdowns sourced from the AC API via `/wp-json/swish-ac/v1/ac-lists` and `/wp-json/swish-ac/v1/ac-tags`, 5-minute transient caching with a manual refresh.

**Lead-magnet popups**

* `swish_popup` CPT, Gutenberg enforced via `use_block_editor_for_post_type` filter (priority 101 to override Classic Editor).
* `swish/popup` container block with InnerBlocks; allowed children: `core/columns`, `core/column`, `core/heading`, `core/paragraph`, `core/image`, `swish/ac-form`.
* `swish/ac-form` child block: optional name field, email field, submit button.
* Per-popup targeting (All / URL patterns / Post types / Exclude URLs / Homepage) with auth filter.
* Per-popup triggers: time delay, scroll %, exit intent, click selector.
* Frequency cap via localStorage (N-days-after-dismissal, hide-forever-after-submission).
* Image layouts: stack (image on top), two-column (image left), background-with-opacity.
* Focal point picker, image height slider, popup width slider.
* Per-side padding via core BoxControl.
* AC form: button alignment (left/center/right/full), label toggle with always-on placeholders, button background/text color, border width/color, border radius.
* Circular close button using the popup's accent color.

**Save Trip (WooCommerce)**

* Floating bookmark button on `is_product()` pages.
* Configurable screen position (6 corner/edge presets) and show-trigger (immediate / time delay / scroll %).
* Pre-fills email for logged-in users; tags contact with base tag + `trip:{slug}` dynamic tag.
* Circular close button.

**UX & DX**

* Submit spinners on both popup and Save Trip forms.
* `?swish_preview=1` bypasses the frequency cap for repeat testing.
* `?swish_debug=1` streams diagnostic info to the browser console.
* When `WP_DEBUG` is on, the server logs which popups matched the request and full AC error response bodies.
* AC error messages include the failing API path (e.g. `... on /api/3/contactLists`) for fast diagnosis.
* Block deprecations registered on both `swish/popup` and `swish/ac-form` for forward compatibility.
* Focus trap + ESC close + opener-focus-restore on both modals.

== Upgrade Notice ==

= 0.1.0 =
First release.
