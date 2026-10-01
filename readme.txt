=== Page Builder Sandwich ===
Contributors: zinndigital
Plugin URI: https://zinndigital.com/wordpress-plugins/page-builder-sandwich
Author: Neil Lock — CEO, Zinn Digital® Ltd
Author URI: https://zinndigital.com
Tags: page builder, blocks, footprint, clean html
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 6.22.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build pages from 48 blocks, section by section, in the block editor or Sandwich Studio, with clean front-end HTML and no builder wrappers.

== Description ==

= What it does =

* **48 free blocks:** layout, content (accordion, tabs, steps, modal, tooltip and more), marketing (call to action, testimonial, a Form block for Contact Form 7, WPForms or Fluent Forms), media (map, booking calendar, QR code) and site blocks (table of contents, related posts, sitemap).
* **Sandwich Studio**, a full-screen page editor with a command palette, and an **accessibility checker**.
* **Site design:** global colours, fonts and styles, synced with your theme's `theme.json`.
* **Footprint-free front end:** neutral class names with a short prefix (`zd` unless you change it), no HTML comments or generator tags, and each page loads only the styles of the blocks it uses, from your uploads folder.
* **Pages made with earlier versions** are converted to blocks in the background after the update, with a backup and undo per page.
* **Language switcher element** for sites that use Tranzly.
* **Templates & kits:** one-click website kits, section patterns and a Pro cloud library.

= Bundled icon sets =

The icon picker offers these free icon sets, shipped with the plugin (editor only; a chosen icon is placed on the page as inline SVG): Font Awesome Free (icons: CC BY 4.0), Lucide (ISC), Material Symbols (Apache License 2.0) and Phosphor (MIT). Each set's licence is included in `assets/icons/licenses/`. The sets are rebuilt from their published packages, pinned by version and checksum, with `php wp/bin/pbs-icons-build.php` in the plugin's source repository.

= Workflow and agency tools =

* **Import and export** (free): move templates, patterns, the site design and the plugin's settings to another site as one file, or with `wp pbs export` / `wp pbs import`.
* **Roles and client mode** (Pro): choose what each role can do in the builder, and lock a role to editing text and images only.
* **Maintenance and pre-launch mode** (Pro): show visitors a page you designed, with the right answer for search engines.
* **Find and replace across all pages** (Pro): text, links or colours, with a preview first and one-click undo.
* **White label, client review and multisite** (Agency): rename the builder for client sites, let clients comment on a page before it goes live, and share templates across a network.

= Bundled libraries =

The 3D model viewer block (Pro) uses model-viewer 4.3.1 (Apache License 2.0), shipped with the plugin in `assets/pro__premium_only/model-viewer/` together with its licence and the licences of the libraries it includes (three.js, lit, gainmap-js). It is loaded from your own site, and only on pages that show a 3D model.

= Build from source =

The admin screen and editor scripts are built from the human-readable sources in `src/` with `@wordpress/scripts`:

`npm ci && npm run build`

== External services ==

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

Background videos from YouTube or Vimeo (Pro). Only when an author picks a YouTube or Vimeo background, and only once a visitor scrolls to that section on a device that does not ask for reduced motion, the visitor's browser loads the player from `https://www.youtube-nocookie.com` or `https://player.vimeo.com` ("do not track" on). Until then the page shows the poster stored on your site. The player receives what any embed does (IP address, browser details, the video's ID); the plugin sends nothing else.

* YouTube terms: https://www.youtube.com/t/terms
* YouTube (Google) privacy policy: https://policies.google.com/privacy
* Vimeo terms: https://vimeo.com/terms
* Vimeo privacy policy: https://vimeo.com/privacy

Map block. The map comes from OpenStreetMap (no key or account). The page shows only a notice, a "Show the map" button and a link; when a visitor presses the button, their browser loads `https://www.openstreetmap.org/export/embed.html` with the author's coordinates, sending what any embedded map does (IP address, browser details).

* Service: https://www.openstreetmap.org
* Terms: https://osmfoundation.org/wiki/Terms_of_Use
* Privacy policy: https://osmfoundation.org/wiki/Privacy_Policy

Booking calendar block (Calendly or Cal.com). The page shows a notice, a button and a link; only when a visitor presses the button does their browser load the author's booking page from `https://calendly.com` or `https://cal.com`, and they book directly with that service.

* Calendly terms: https://calendly.com/legal/customer-terms-of-use
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
* Trustpilot: once a day, in the background, your site requests `https://api.trustpilot.com/v1/business-units/<your business unit ID>` and its reviews with your own API key (sent in a header), and keeps them on your site, so page views never call Trustpilot. Terms: https://legal.trustpilot.com/for-businesses/business-terms, privacy policy: https://legal.trustpilot.com/for-reviewers/end-user-privacy-terms

Age gate and responsible gambling notice (Pro). Both use the visitor's country when your host or CDN provides it in the request (for example Cloudflare's `CF-IPCountry` header); the plugin never looks a country up and sends nothing anywhere to learn it. The age gate remembers the visitor's answer in a cookie on your own site. The notice can link to a national help service (for example BeGambleAware in the UK); those are links only, opened by the visitor.

Short links (Pro). Short links (`/go/<name>/`) are handled by your own site: a visit is counted and sent on to the address you entered. No link service is used.

AI features use the AI provider you choose, with your own API key. Nothing is sent to any AI provider until you add a key under Settings → AI providers and use an AI feature. When you do, the text the feature needs (for example, the content being written or translated) and your key are sent to that one provider, and to no one else. Keys are stored encrypted in your database and are never sent to Zinn Digital®. The "Save and test" button sends one short test request to the provider.

* OpenAI: https://api.openai.com/v1 (terms: https://openai.com/policies/services-agreement/, privacy policy: https://openai.com/policies/privacy-policy/)
* Anthropic (Claude): https://api.anthropic.com/v1 (terms: https://www.anthropic.com/legal/commercial-terms, privacy policy: https://www.anthropic.com/legal/privacy)
* Google Gemini: https://generativelanguage.googleapis.com/v1beta (terms: https://ai.google.dev/gemini-api/terms, privacy policy: https://policies.google.com/privacy)
* Mistral AI: https://api.mistral.ai/v1 (terms: https://legal.mistral.ai/terms/commercial-terms-of-service/, privacy policy: https://legal.mistral.ai/terms/privacy-policy/)
* DeepSeek: https://api.deepseek.com (terms: https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html, privacy policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)
* OpenRouter: https://openrouter.ai/api/v1 (terms: https://openrouter.ai/terms, privacy policy: https://openrouter.ai/privacy)
* A service you run yourself (any OpenAI-compatible address you enter): only that address is contacted.

Recommended models list (off unless you turn it on). If you turn on the daily check for a newer recommended models list under Settings → AI providers, the plugin requests https://api.zinndigital.com/v1/ai-model-catalogue once a day. The request is a plain download: it carries no key, no site address and nothing about your content, and the list is signed so a changed copy is ignored.

* Service: https://zinndigital.com
* Terms: https://zinndigital.com/legal/terms
* Privacy policy: https://zinndigital.com/legal/privacy

= Help and support from Zinn Digital® (off until you use it) =

The plugin's Get help screen can send a support request to Zinn Digital®, the plugin's developer. Nothing is sent until you connect the site or send a request yourself.

* Connecting the site (Get help → Connect) calls `https://api.zinndigital.com/v1/plugin-support/connections` with the email address and name you type, the plugin's name and version, this site's address and title, the WordPress and PHP versions and your language. The answer is a connection token, stored encrypted in your database. The screen checks it with `/v1/plugin-support/connection`; Disconnect deletes it there and here.
* Sending a request calls `https://api.zinndigital.com/v1/plugin-support/tickets` with what you type (subject, message, your name), the plugin's name, your language, and your licence's plan and ids. Only if you tick "Include site details" does it add the site details the screen shows you before sending (site address, WordPress, PHP, theme and plugin versions, a few server settings and the last lines of the PHP error log). Any login you choose to add is sent over HTTPS, stored encrypted by Zinn Digital®, and deleted 30 days after the request is closed.
* Temporary support access, only if you choose it with a request: the plugin creates a WordPress user on your own site with a support role that cannot install, edit or delete plugins or themes, manage users, update WordPress or export content, sends its login with the request, and deletes the user when the time you picked (1, 3 or 7 days) runs out, or sooner if you remove it on the Get help screen.

Adobe Fonts (Pro, off unless you connect it). If you enter an Adobe Fonts kit id under Appearance → Site design → Custom fonts, the plugin requests `https://typekit.com/api/v1/json/kits/<kit id>/published` once when you save it, to read the kit's font names (the request carries only the kit id). From then on your pages, and the editor, load the kit's stylesheet from `https://use.typekit.net/<kit id>.css`, and your visitors' browsers download the fonts from Adobe. Remove the kit id to stop it.

* Service: https://fonts.adobe.com
* Terms: https://www.adobe.com/legal/terms.html
* Privacy policy: https://www.adobe.com/privacy/policy.html

YouTube and Vimeo videos in the video playlist and video gallery blocks (Pro). A page shows each video as a poster image stored on your own site and a play button; nothing is requested from YouTube or Vimeo until a visitor presses play. Then that one video's player is loaded from YouTube's privacy-enhanced domain `https://www.youtube-nocookie.com`, or from `https://player.vimeo.com` with "do not track" set, sending what any embedded video sends (the visitor's IP address and browser details, and the video's ID). The poster images are fetched once, when an author saves a post that uses these blocks (not when a page is viewed): from `https://i.ytimg.com` for YouTube, and for Vimeo the thumbnail address is read from `https://vimeo.com/api/oembed.json` and the image downloaded from `https://i.vimeocdn.com`. Those requests carry only the video's ID. A video given a poster from the media library is never fetched.

* YouTube terms: https://www.youtube.com/t/terms
* YouTube (Google) privacy policy: https://policies.google.com/privacy
* Vimeo terms: https://vimeo.com/terms
* Vimeo privacy policy: https://vimeo.com/privacy

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

== Changelog ==

= 6.22.0 =
* Pro: forms with email, save, webhook and mailing-list actions (Mailchimp, Brevo, MailerLite, Kit, ActiveCampaign); popups, slide-ins and bars; entrance, scroll and hover motion; conversion tracking (GA4, Tag Manager, Meta Pixel); A/B tests; a cookie consent banner that blocks tracking until consent.

= 6.20.0 =
* Team libraries (Pro): deleting a team now asks in an accessible confirmation dialog instead of a browser pop-up.

= 6.19.0 =
* Templates & kits: 28 website kits (20 free) with one-click import, 43 translated section patterns and Save as pattern; Pro cloud library with plan storage, team sharing and bring-your-own R2/S3 storage; fixes a PHP warning on pages with styled core blocks.

= 6.18.3 =
* Support: a reply address that had to be altered to be valid is refused rather than sent as a different address; temporary support access is limited to 3 active logins and to 1, 3 or 7 days.

= 6.18.2 =
* WordPress.org: the directory build lists the account that owns the listing among its contributors.

= 6.18.1 =
* Sandwich Studio: when the block editor is switched off for a page (for example by Classic Editor), Studio now says so and how to allow it, instead of "not allowed".

= 6.18.0 =
* WordPress.org review: the admin menu sits below Settings; the licensing SDK shows the plugin's own icon instead of downloading one before you opt in; the readme describes the blocks this version has.

= 6.17.1 =
* Security: the Reviews block only fetches its live reviews from this site's own reviews route (same origin), never from any other address written into the page.
* Tweak: the editor's effects preview rewrites selectors with plain text replacement instead of building regular expressions.

= 6.17.0 =
* Legacy licences: an unlimited legacy licence is Agency everywhere (Tranzly multisite network layer; PBS white label, client review, network); no more error on the plugin screen for a licence within 30 days of its end.

= 6.16.5 =
* Security: js-yaml 5.4.2 in the build toolchain (GHSA-r3ph-w7gj-g6xm, markdownlint-cli dev dependency); no runtime change.

= 6.16.4 =
* Readme: the WordPress.org edition keeps its disclosure of the hosting-customer discount request, and says plainly that the card never contacts the platform's update address.

= 6.16.3 =
* Fix: Sandwich Studio's command palette no longer closes with an error when a search matches a block whose icon is a Dashicon (for example typing "quote").

= 6.16.2 =
* Maintenance: the readme fits the WordPress.org directory's description limit, and the source (including the shared admin screens) is formatted and linted to WordPress's JavaScript standard. No change in behaviour.

= 6.16.0 =
* On a site hosted by Zinn Digital®, the plugin screen offers hosting customers a personal discount code for their first payment of the Pro edition. Nothing is shown on other sites, and nothing is fetched until you press the button.

= 6.15.0 =
* The AI settings can now choose a model for site search (embeddings), and the shared AI layer gains the embeddings API that Zinn® Chat uses. Nothing else changes.

= 6.14.0 =
* Security: the Short links list is no longer readable through the REST API by visitors who are not signed in; only people who can edit posts can list or read short links.

= 6.13.1 =
* Maintenance: security-scanner review notes in the effects and reviews scripts. No change in behaviour.

= 6.13.0 =
* The 3D model viewer library moved out of the vendor folder, so the free download no longer carries Pro files.

= 6.12.0 =
* Smaller download: the editable translation sources (.po) are no longer shipped; WordPress only ever loads the compiled .mo and .l10n.php files, which are unchanged.

= 6.11.0 =
* New (Pro): marketing, media, data and site blocks, including sliders, galleries, pricing tables, countdowns, reviews, business hours, a store locator and a navigation menu, each with a design panel, accessible markup and right-to-left layouts.
* New (Pro): a reviews block. Trustpilot reviews are refreshed once a day; Google reviews are fetched live for each visitor, because Google's terms do not allow them to be stored (each page view with the block is one Google API call on your key).
* Translation-ready lists: list blocks keep icons, links and ids out of translation, so only the words are translated.

= 6.10.0 =
* New: 35 free blocks for content, media, marketing, site and data, each with a design panel, accessible markup and right-to-left layouts.
* New: an accessibility checker in the editor and Sandwich Studio.
* New: a Form block that shows a form from Contact Form 7, WPForms or Fluent Forms, styled to match your site.
* Improved: widgets and shortcodes placed in pages get real settings panels.
* Translation-ready lists: every list block keeps its icons, links and network names out of translation, so only the words are translated.

= 6.9.0 =
* Admin screens: the colour-scheme menu shows a tick next to the option that is in use.

= 6.8.0 =
* Admin screens: in dark mode the colour-scheme and help buttons in the header are visible without hovering over them.

= 6.7.0 =
* Admin screens: screen readers see one page structure (the admin shell no longer adds a second main area inside WordPress's own).
* Admin screens: dark mode themes the settings panels and links, so every label and description is readable.

= 6.6.0 =
* New admin screens: overview, setup wizard, plans and licence, add-ons, and help and support from inside the plugin.

= 6.5.0 =
* Site design: rows of options line up, the box is wider and the entries are spaced (owner design review).
* Site design: colours made with color-mix(), such as Twenty Twenty-Five's, can be saved.
* Arabic: the corner-radius scale is named correctly.

= 6.4.2 =
* Security hardening from the nightly scan: the SVG sanitizer reads attributes without building a pattern from their names.

= 6.4.1 =
* Maintenance: the design system's editor scripts pass the WordPress coding standards' JavaScript checks (no change to what the editor or your pages do).

= 6.4.0 =
* New: design system. A Style tab on every block, including WordPress's own blocks: layout (flex and grid), spacing, size, typography, colours and backgrounds, borders, shadows and position, set per device and for hover. Styles are saved as settings and printed as one stylesheet per page, never as inline styles.
* New: Section and Container blocks with flexbox and CSS grid, and a visual grid editor on the canvas (drag column and row lines, add or remove tracks, drag a block's corner to span cells).
* New: three breakpoints (desktop, tablet, mobile) with editable widths, previewed live in the editor; Pro adds your own breakpoints.
* New: Site design (Appearance → Site design, and a sidebar in the editor and Sandwich Studio): site colours and fonts shared with your theme's settings, so the builder and the Site Editor always match.
* New (Pro): global classes and CSS variables, spacing, radius and shadow scales, custom CSS per block and per page, dark mode for your website with a Dark mode switch block, a brand kit the AI features use, and custom fonts (upload, or connect Adobe Fonts).
* New: icon library with Font Awesome Free, Lucide, Material Symbols and Phosphor, searchable, plus your own SVG uploads (cleaned on the server). Icons are placed as inline SVG; no icon font is ever loaded.
* New (Pro): visual effects: gradients, glass, masks, blend modes, filters, shape dividers, background video (YouTube and Vimeo load only when scrolled to) and slideshow backgrounds, all still under reduced-motion settings.
* New: block framework for the design blocks (block categories, one registry, per-block stylesheets loaded only where used) and the Alert block (info, success, warning, error; optional title and icon; dismissible).
* Fix: block stylesheets and scripts keep working after the site's address changes.

= 6.3.1 =
* Fix: the Pro build no longer ships a stray right-to-left stylesheet for the client review layer, which stopped the free and Pro packages from building.

= 6.3.0 =
* AI: the shared AI core can now read images (for designing from a screenshot) and make images with OpenAI or Google Gemini. Choose the image model under Settings → AI providers.

= 6.2.0 =
* New: import and export of templates, patterns, the site design and settings (with WP-CLI commands).
* New (Pro): role manager and client mode, maintenance and pre-launch mode, find and replace across all pages with undo.
* New (Agency): white label, client review comments, multisite network defaults and shared templates.

= 6.1.3 =
* A language switcher element: the site's languages from Tranzly (when it is active) as a list, pills, buttons, a dropdown or language codes, with your own colours, spacing and size; accessible, with optional flags.

= 6.1.2 =
* AI: the shared AI core gives the plugin one hook point on every request it sends (used by the brand kit in the design system).

= 6.1.1 =
* The licensing SDK's notices, opt-in, licence and pricing screens now appear in the site's language (every SDK string routed through the plugin's own translations).

= 6.1.0 =
* Pages are stored as standard WordPress blocks, so they stay readable if the plugin is ever switched off, and work in the normal block editor too.
* Pages built with older versions are upgraded automatically in the background after updating, with a backup of every page you can restore (Settings > Legacy content, or `wp pbs migrate`). Older pages keep their look, their effects and their newsletter forms.
* Sandwich Studio: a full-screen visual editor with layers, drag and drop, undo history, a command palette (Ctrl/Cmd+K), autosave with crash recovery, revisions, copy and paste of styles between sites, and a safe mode for finding plugin conflicts.
* Faster pages: styles and scripts load only on pages that use them, one cached stylesheet per page, no jQuery, images reserve their space, the main image loads first, Google Fonts are hosted on your own site, and LiteSpeed Cache, WP Rocket, W3 Total Cache and Zinn® Cache are cleared when you publish.
* Add-ons written for the old developer API keep working.
* Security: every action checks the user's permission for the exact page it changes.

= 6.0.2 =
* AI core: when a provider's plan excludes a model, say so (instead of 'rate limited') and let Save and test fall through to a model the plan includes.

= 6.0.1 =
* Shared AI core: bring your own key for OpenAI, Anthropic, Gemini, Mistral, DeepSeek, OpenRouter or any OpenAI-compatible service; live model lists; signed recommended-models list; setup wizard with a test button; encrypted keys; clear error messages. Pro: usage and cost log, monthly limits, per-role permissions.

= 6.0.0 =
* Rebuilt foundation: settings and About screen, footprint-free front-end output layer, sample content and language-switcher blocks.
