=== Page Builder Sandwich ===
Contributors: zinndigital
Plugin URI: https://zinndigital.com/wordpress-plugins/page-builder-sandwich
Author: Neil Lock — CEO, Zinn Digital® Ltd
Author URI: https://zinndigital.com
Tags: page builder, blocks, footprint, clean html
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 6.36.30
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build pages from 49 blocks, section by section, in the block editor or Sandwich Studio, with clean front-end HTML and no builder wrappers.

== Description ==

= What it does =

* **49 free blocks:** layout, content (accordion, tabs, steps, modal and more), marketing (call to action, testimonial, a Form block for Contact Form 7, WPForms or Fluent Forms), media (map, booking calendar, QR code) and site blocks, including a navigation menu with a fully adjustable mobile menu.
* **Pro navigation:** mega menus holding any blocks, menu item icons, badges and descriptions, and a site header that sticks, shrinks or hides while scrolling.
* **Sandwich Studio**, a full-screen editor with a command palette and an accessibility checker.
* **Site design:** global styles synced with `theme.json`.
* **Footprint-free front end:** short neutral class names (`zd` by default), no generator tags, only the styles each page uses.
* **Older pages** are converted to blocks in the background, with a backup and undo per page.
* **Templates & kits:** website kits, section patterns and a Pro cloud library.

= Bundled icon sets =

Font Awesome Free (icons: CC BY 4.0), Lucide (ISC), Material Symbols (Apache License 2.0) and Phosphor (MIT), placed as inline SVG; licences in `assets/icons/licenses/`.

= Workflow and agency tools =

* **Free:** import and export of templates, patterns, site design and settings (`wp pbs export --user=<admin>` / `wp pbs import --user=<admin>`).
* **Pro:** roles and client mode, a maintenance or pre-launch page, and find and replace across all pages with undo.
* **Agency:** white label, client review and templates shared across a multisite network.

= AI inside the builder =

On your own AI provider account, set under Settings → AI providers.

* **Free:** write and rewrite any text, a designed section from a sentence, SEO titles and descriptions, and alt text for images.
* **Pro:** an assistant that edits the page with real blocks (one click undoes it), a website from a description, custom blocks, designs from a screenshot or link, images, a CSS helper and accessibility fixes.

= SEO and performance =

* **Free:** Yoast SEO, Rank Math, SEOPress and All in One SEO analyse the page as visitors see it, dynamic text included.
* **Pro:** a performance score with one-click image fixes, schema markup without duplicates, and site health and clean-up.

= Bundled libraries =

The 3D model viewer block (Pro) uses model-viewer 4.3.1 (Apache License 2.0) with its included libraries' licences (three.js, lit, gainmap-js) in `assets/pro__premium_only/model-viewer/`, loaded only on pages that show a 3D model.

= Build from source =

The admin screen and editor scripts are built from the human-readable sources in `src/` with `@wordpress/scripts`:

`npm ci && npm run build`

== External services ==

= AI apps you connect (MCP sign-in) =

Only when an AI app such as Claude or ChatGPT starts connecting to the site's MCP server does the site fetch that app's public OAuth client metadata from the address the app gives, for example `https://claude.ai/oauth/mcp-oauth-client-metadata` or `https://chatgpt.com/oauth/client.json`. No site content is sent. Anthropic's and OpenAI's terms and privacy policies are listed under AI features below.

= Zinn Digital® kit and cloud library =

Only when an administrator opens Templates & kits, the site requests `https://api.zinndigital.com/v1/pbs-library/kits/` with its language. Pro, only when a design is saved, inserted or managed: `/v1/pbs-library/sessions`, `/v1/pbs-library/team-sessions`, `/v1/pbs-library/items/`, `/v1/pbs-library/images/`, `/v1/pbs-library/account`, `/v1/pbs-library/teams/` (same host), sending the design, its resized images, its name and the site address; the licence key is never sent. With your own R2/S3 bucket nothing is sent to Zinn Digital®. Terms: https://zinndigital.com/legal/terms · Privacy policy: https://zinndigital.com/legal/privacy

= Zinn Digital® hosting-customer discount (only on sites Zinn Digital® hosts) =

On a WordPress site hosted by Zinn Digital®, the plugin's screen shows administrators a card offering hosting customers a personal discount code for the Pro edition. Only when an administrator presses the card's button does the site send one request to Zinn Digital® at `https://api.zinndigital.com/v1/wp/pro-discount/<site id>`, containing the plugin's slug, the word `issue` and the administrator's WordPress language, signed with the site's own key. The card reuses only the host and site id of the address the platform writes into wp-config.php (ending in `/v1/wp/plugin-update/<site id>`) and sends nothing there itself. No other site shows the card. The answer is the customer's code and a Freemius checkout link, to which the browser is then sent. Terms: https://zinndigital.com/legal/terms · Privacy policy: https://zinndigital.com/legal/privacy

The plugin bundles the Freemius SDK, which handles licences and updates for the Pro edition and, only if you agree, product usage data.

Nothing is sent until you opt in on the screen shown after activation, or activate a licence. When you do, the SDK sends your site's URL, WordPress, PHP and plugin versions, language, and the administrator's name and email address to Freemius, and checks it periodically for licence status and updates. Before connecting it checks that the service is reachable by requesting `https://api.freemius.com/v1/ping.json`, which sends nothing about your site. You can opt out at any time from the plugin's Account page.

* Service: https://freemius.com
* Terms: https://freemius.com/terms/
* Privacy policy: https://freemius.com/privacy/

Google Fonts are hosted on your own site. Only when an administrator adds a font (in wp-admin or with `wp pbsw fonts download`) does the plugin download its stylesheet from `https://fonts.googleapis.com` and its files from `https://fonts.gstatic.com`, sending nothing about your site or visitors. Visitors load the fonts from your site; Google is never contacted when a page is viewed.

* Service: https://fonts.google.com
* Terms: https://developers.google.com/terms
* Privacy policy: https://policies.google.com/privacy

Figma import (Pro): only when an administrator connects their own Figma personal access token (stored encrypted) and imports a frame, the site calls https://api.figma.com/v1 (me, files/<file>/nodes, files/<file>/images, images/<file>) with that token and downloads the frame's images into the media library. Nothing is sent to Zinn Digital®.

* Service: https://www.figma.com
* Terms: https://www.figma.com/legal/tos/
* Privacy policy: https://www.figma.com/legal/privacy/

Background videos from YouTube or Vimeo (Pro). Only for a YouTube or Vimeo background, once a visitor scrolls to it without asking for reduced motion, the browser loads the player from `https://www.youtube-nocookie.com` or `https://player.vimeo.com` ("do not track" on); until then the page shows a local poster. The player receives what any embed does (IP address, browser details, video ID); nothing else is sent.

* YouTube terms: https://www.youtube.com/t/terms
* YouTube (Google) privacy policy: https://policies.google.com/privacy
* Vimeo terms: https://vimeo.com/terms
* Vimeo privacy policy: https://vimeo.com/privacy

Map block. The map comes from OpenStreetMap (no key or account). The page shows only a notice, a "Show the map" button and a link; when a visitor presses the button, their browser loads `https://www.openstreetmap.org/export/embed.html` with the author's coordinates, sending what any embedded map does (IP address, browser details).

* Service: https://www.openstreetmap.org
* Terms: https://osmfoundation.org/wiki/Terms_of_Use
* Privacy policy: https://osmfoundation.org/wiki/Privacy_Policy

Booking calendar block (Calendly or Cal.com). The page shows a notice, a button and a link; only when a visitor presses the button does their browser load the author's booking page from `https://calendly.com` or `https://cal.com`, and they book directly with that service.

* Calendly terms: https://calendly.com/legal/customer-terms-conditions
* Calendly privacy policy: https://calendly.com/legal/privacy-notice
* Cal.com terms: https://cal.com/terms
* Cal.com privacy policy: https://cal.com/privacy

WhatsApp chat button and contact buttons. These are plain links to `https://wa.me/` with the number the author entered (and an optional message). Nothing is loaded from WhatsApp until a visitor presses one; WhatsApp then opens in the visitor's app or browser.

* WhatsApp terms: https://www.whatsapp.com/legal/terms-of-service
* WhatsApp privacy policy: https://www.whatsapp.com/legal/privacy-policy

Code and QR code blocks use no external service: highlight.js 11.12.0 (BSD-3-Clause) and qrcode-generator 2.0.4 (MIT) are bundled and served from your own site.

Crypto prices block (Pro). Your site, never your visitors, asks CoinGecko's public API (no key) for the coins and currency the block shows: `https://api.coingecko.com/api/v3/simple/price` and `https://api.coingecko.com/api/v3/coins/<coin>/market_chart`. Answers are kept on your site and refreshed in the background while pages with the block are visited; nothing about visitors is sent.

* Service: https://www.coingecko.com
* Terms: https://www.coingecko.com/en/terms
* Privacy policy: https://www.coingecko.com/en/privacy

Reviews block (Pro, only if you set it up under Appearance → Site design → Reviews).

* Google Places API (New): Google's terms do not allow its reviews to be stored, so when a visitor views a page with a Reviews block that shows Google, the visitor's browser asks your site for them and your site requests `https://places.googleapis.com/v1/places/<your place ID>` with your own API key (sent in a header). Each such page view is one request on your key, billed by Google. What is sent: the place ID, your key and the site language; nothing about the visitor. The plugin stores only the place ID. Terms: https://cloud.google.com/maps-platform/terms and https://cloud.google.com/maps-platform/terms/maps-service-terms, privacy policy: https://policies.google.com/privacy
* Trustpilot: once a day, in the background, your site requests `https://api.trustpilot.com/v1/business-units/<your business unit ID>` and its reviews with your own API key (sent in a header), and keeps them on your site, so page views never call Trustpilot. Terms: https://corporate.trustpilot.com/legal/for-businesses/terms-of-use-and-sale-for-businesses, privacy policy: https://legal.trustpilot.com/for-reviewers/end-user-privacy-terms

Age gate and responsible gambling notice (Pro). Both use the visitor's country when your host or CDN provides it in the request (for example Cloudflare's `CF-IPCountry` header); the plugin never looks a country up and sends nothing anywhere to learn it. The age gate remembers the visitor's answer in a cookie on your own site. The notice can link to a national help service (for example GambleAware in the UK), chosen by the visitor's country and your site's language; those are links only, opened by the visitor.

AI features use the AI provider you choose, with your own API key. Nothing is sent to any AI provider until you add a key under Settings → AI providers and use an AI feature. When you do, the text the feature needs (for example, the content being written or translated) and your key are sent to that one provider, and to no one else. Keys are stored encrypted in your database and are never sent to Zinn Digital®. The "Save and test" button sends one short test request to the provider.

* OpenAI: https://api.openai.com/v1 (terms: https://openai.com/policies/services-agreement/, privacy policy: https://openai.com/policies/privacy-policy/)
* Anthropic (Claude): https://api.anthropic.com/v1 (terms: https://www.anthropic.com/legal/commercial-terms, privacy policy: https://www.anthropic.com/legal/privacy)
* Google Gemini: https://generativelanguage.googleapis.com/v1beta (terms: https://ai.google.dev/gemini-api/terms, privacy policy: https://policies.google.com/privacy)
* Mistral AI: https://api.mistral.ai/v1 (terms: https://legal.mistral.ai/terms/commercial-terms-of-service/, privacy policy: https://legal.mistral.ai/terms/privacy-policy/)
* DeepSeek: https://api.deepseek.com (terms: https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html, privacy policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)
* OpenRouter: https://openrouter.ai/api/v1 (terms: https://openrouter.ai/terms, privacy policy: https://openrouter.ai/privacy)
* A service you run yourself (any OpenAI-compatible address you enter): only that address is contacted.

Images and pages sent to AI (only when you use these features). Writing alt text sends that image (resized) to your AI provider; designing from a screenshot sends the screenshot. Designing from a website address (Pro) fetches that address once from your site and sends only its text outline (headings, paragraphs, list items, button labels; never images or code) to your AI provider.

Recommended models list (off until ticked). The daily check under Settings → AI providers requests the signed list from https://api.zinndigital.com/v1/ai-model-catalogue, sending no key, site address or content. Connecting to Zinn® credits does not turn it on (in Pro it does).

* Service: https://zinndigital.com
* Terms: https://zinndigital.com/legal/terms
* Privacy policy: https://zinndigital.com/legal/privacy

= Help and support from Zinn Digital® (off until you use it) =

The plugin's Get help screen can send a support request to Zinn Digital®, the plugin's developer. Nothing is sent until you connect the site or send a request yourself.

* Connecting the site (Get help → Connect) calls `https://api.zinndigital.com/v1/plugin-support/connections` with the email address and name you type, the plugin's name and version, this site's address and title, the WordPress and PHP versions and your language. The answer is a connection token, stored encrypted in your database. The screen checks it with `/v1/plugin-support/connection`; Disconnect deletes it there and here.
* Sending a request calls `https://api.zinndigital.com/v1/plugin-support/tickets` with what you type (subject, message, your name), the plugin's name, your language, and your licence's plan and ids. Only if you tick "Include site details" does it add the site details the screen shows you before sending (site address, WordPress, PHP, theme and plugin versions, a few server settings and the last lines of the PHP error log).

Adobe Fonts (Pro, off unless you connect it). If you enter an Adobe Fonts kit id under Appearance → Site design → Custom fonts, the plugin requests `https://typekit.com/api/v1/json/kits/<kit id>/published` once when you save it, to read the kit's font names (the request carries only the kit id). From then on your pages, and the editor, load the kit's stylesheet from `https://use.typekit.net/<kit id>.css`, and your visitors' browsers download the fonts from Adobe. Remove the kit id to stop it.

* Service: https://fonts.adobe.com
* Terms: https://www.adobe.com/legal/terms.html
* Privacy policy: https://www.adobe.com/privacy/policy.html

YouTube and Vimeo videos in the video playlist and video gallery blocks (Pro). A page shows each video as a poster image stored on your own site and a play button; nothing is requested from YouTube or Vimeo until a visitor presses play. Then that one video's player is loaded from YouTube's privacy-enhanced domain `https://www.youtube-nocookie.com`, or from `https://player.vimeo.com` with "do not track" set, sending what any embedded video sends (the visitor's IP address and browser details, and the video's ID). The poster images are fetched once, when an author saves a post that uses these blocks (not when a page is viewed): from `https://i.ytimg.com` for YouTube, and for Vimeo the thumbnail address is read from `https://vimeo.com/api/oembed.json` and the image downloaded from `https://i.vimeocdn.com`. Those requests carry only the video's ID. A video given a poster from the media library is never fetched.

Terms and privacy policies: as for background videos above.

Newsletter signups (Pro, only if you set one up). A Newsletter signup block, or a newsletter form made with an older version, sends the email address a visitor types into it — and nothing else about them — from your server to the service you chose for that form, only when the visitor ticks the consent box and presses the button: Mailchimp (`https://<data centre>.api.mailchimp.com/3.0/`, with the API key you saved) or AWeber (`https://www.aweber.com/scripts/addlead.pl`). MailPoet runs inside your own site and sends nothing elsewhere.

* Mailchimp: terms https://mailchimp.com/legal/terms/, privacy policy https://www.intuit.com/privacy/statement/
* AWeber: terms https://www.aweber.com/service-agreement.htm, privacy policy https://www.aweber.com/privacy.htm

Share buttons (Pro). The Share bar and Click to share quote blocks are plain links that open the chosen network's own share page (Facebook, X, LinkedIn, WhatsApp, Telegram, Reddit, Pinterest, Bluesky or Threads) with the page's address and title filled in. Nothing is loaded from those networks when a page is viewed; the network is contacted only if a visitor clicks the link, and then by the visitor's browser, under that network's terms and privacy policy.

Forms (Pro). A submission goes only where that form's settings say, sent from your server: your site's mail, your database, an https webhook you enter (the fields as JSON), or one mailing list. For a list, the visitor's email (and name) is sent with the API key you saved, stored encrypted and never sent to Zinn Digital®, to Mailchimp (`https://<dc>.api.mailchimp.com`), Brevo (`https://api.brevo.com`), MailerLite (`https://connect.mailerlite.com`), Kit (`https://api.kit.com`) or ActiveCampaign (`https://<account>.api-us1.com`). Terms and privacy: https://mailchimp.com/legal/, https://www.brevo.com/legal/, https://www.mailerlite.com/legal/, https://kit.com/terms, https://www.activecampaign.com/legal/.

Conversion tracking (Pro, only with an ID entered). Visitors' browsers load Google Analytics 4 or Tag Manager (`https://www.googletagmanager.com`) and the Meta Pixel (`https://connect.facebook.net`), sending page views and your conversions; with the cookie banner on, only after the visitor agrees. Terms and privacy: https://policies.google.com/privacy, https://www.facebook.com/privacy/policy/.

== Installation ==

1. Upload the `page-builder-sandwich` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Open **Page Builder Sandwich** in the admin menu to set the class prefix.

== Frequently Asked Questions ==

= Why is the class prefix `zd`? =

It is short and says nothing about which plugin produced the markup. You can change it to any 1 to 8 lowercase letters or digits that start with a letter.

= Where do the front-end styles come from? =

From a copy in your uploads folder, written when the plugin is activated, updated, or its prefix is changed.

= Can an AI assistant build pages with it? =

Yes. The plugin adds WordPress abilities and its own MCP server at `/wp-json/pbs/v1/mcp` (WordPress 6.9 or later): pages, sections, every block, block-level edits, kits and site design, with the same permission checks as the screens. Pro adds bulk page building and AI page writing with your own AI key. Create an application password under Users, Profile, then see Settings, AI agents (MCP). REST: `/wp-json/wp-abilities/v1/abilities`.

== Changelog ==

= 6.36.30 =
* Security: the kit importer checks your permissions again itself, and only updates a page it imported before if you are allowed to edit that page.

= 6.36.29 =
* Security: importing a website kit now needs the administrator capability (manage_options) to set the site's front page and the menu capability (edit_theme_options) to create a menu; editors can still import the pages. Safe mode's one-time must-use plugin install now needs the capability to install plugins.

= 6.36.28 =
* Rebuild only: the package no longer carries development files

= 6.36.27 =
* Fix: content made with versions 4 and 5 keeps its exact look after the update, on single posts and on the pages that list them.

= 6.36.26 =
* New: if the Studio's page preview is blocked by a security setting or does not load within 30 seconds, the Studio now says why and offers a Retry button. Your work is never lost.
* New: the Language switcher element's flags can be a rectangle, rounded rectangle, square or circle, and small, medium or large (with Tranzly 3.27.26 or later).

= 6.36.25 =
* New: a Zinn® Ads card among the Zinn® services on this plugin's own screens — Google Ads management that asks before it spends, with a click-fraud shield free on Zinn® hosting. It links to the page in your language and is never shown on a footprint-free site.

= 6.36.24 =
* New: shades and opacity for palette colours. Pick a palette colour in any colour setting, then make it lighter, darker or see-through; it is mixed in OKLCH so the hue stays true, it still follows the palette colour, and browsers without colour mixing get the plain colour.
* New: conditions in the Style tab. Style an element only when printing, in a dark colour scheme, with reduced motion or on a device with a mouse; developers add their own media, container and supports conditions with the `pbsw_style_at_rules` filter. New Advanced setting "Query container" for container conditions.
* New (Pro): categories for global classes, variables and spacing, radius and shadow scales, with filters in Site design and in the editor's class and preset pickers.
* New (Pro): import and export your design system (Site design › Import and export): one JSON file with categories, classes, variables, scales and custom colours, checked before import, merge or replace, with a preview of what changes.
* New (Pro): a mobile toggle bar designer for the navigation menu: drag the Menu button, logo, site title, a search box, links and texts into the bar a folded menu shows.
* New (Pro): per-row sticky site header: let a top bar scroll away while the logo and menu stay.
* Fix: breadcrumbs stay on one line (earlier steps are shortened), so a web font loading late can no longer move the page.

= 6.36.23 =
* Fix: front-end scripts and stylesheets keep the same address across updates (only a version number after it changes), and a file a previous version used is kept for one more release, so a page a cache saved before an update never points at a missing script. This stops an update from looking like broken pages to update checkers, and keeps the mobile menu working on cached pages.
* Fix: after an update made without opening wp-admin (WP-CLI, a host's updater), the front-end files are published on the first page view, instead of loading from the plugin folder until someone visits the dashboard.
* Fix: sites whose cached pages still pointed at the navigation menu and site header files 6.36.22 replaced get those files back, and on every update that changes the front-end files the page caches the plugin can reach are emptied (WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, SiteGround Optimizer, the Cloudflare plugin, Zinn® Cache). Hosts can hook `pbs_assets_changed` to purge a CDN.

The full history of every release is in changelog.txt, in the plugin's folder.
