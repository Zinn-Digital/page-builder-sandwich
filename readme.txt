=== Page Builder Sandwich ===
Contributors: zinndigital
Plugin URI: https://zinndigital.com/wordpress-plugins/page-builder-sandwich
Author: Neil Lock — CEO, Zinn Digital® Ltd
Author URI: https://zinndigital.com
Tags: page builder, blocks, footprint, clean html
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 6.37.17
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build pages from 47 blocks, section by section, in the block editor or Sandwich Studio, with clean front-end HTML and no builder wrappers.

== Description ==

= What it does =

* **44 free blocks:** layout, content (accordion, tabs, steps, modal), marketing (call to action, testimonial, a Form block for Contact Form 7, WPForms or Fluent Forms), media (map, booking calendar, QR code) and site blocks, including a navigation menu with an adjustable mobile menu.
* **Pro navigation:** mega menus, menu icons and badges, and a header that sticks, shrinks or hides on scroll.
* **Sandwich Studio**, a full-screen editor with a command palette and an accessibility checker.
* **Site design:** global styles synced with `theme.json`.
* **Footprint-free front end:** short neutral class names (`zd` by default), no generator tags, only the styles each page uses.
* **Older pages** convert to blocks in the background, with a backup and undo per page.
* **Templates & kits:** website kits, section patterns and a Pro cloud library.

= Workflow and agency tools =

* **Free:** import and export of templates, patterns, site design and settings (also `wp pbsw export` / `wp pbsw import`).
* **Pro:** roles and client mode, a maintenance page, and find and replace across all pages with undo.
* **Agency:** white label, client review and templates shared across a multisite network.

= AI inside the builder =

On your own AI provider account, set under Settings → AI providers.

* **Free:** write and rewrite text, a section from a sentence, SEO titles and descriptions, and image alt text.
* **Pro:** an assistant that edits the page with real blocks (one click undoes it), a website from a description, designs from a screenshot or link, images, a CSS helper and accessibility fixes.

= SEO and performance =

* **Free:** Yoast SEO, Rank Math, SEOPress and All in One SEO analyse the page as visitors see it.
* **Pro:** a performance score with one-click image fixes, schema markup without duplicates, and site health and clean-up.

== External services ==

= AI apps you connect (MCP sign-in) =

Only when an AI app such as Claude or ChatGPT starts connecting to the site's MCP server does the site fetch that app's public OAuth client metadata from the address the app gives, for example `https://claude.ai/oauth/mcp-oauth-client-metadata` or `https://chatgpt.com/oauth/client.json`. No site content is sent. Anthropic's and OpenAI's terms and privacy policies are listed under AI features below.

= Zinn Digital® kit and cloud library =

Off by default. Nothing is contacted until an administrator presses "Switch on the kit library" under Templates & kits, and it can be switched off again. Once on, opening the screen or importing a kit requests `https://api.zinndigital.com/v1/pbs-library/kits/` with the site's language and the plugin name; the site address, version and content are not sent. Pro, only when a design is saved, inserted or managed: `/v1/pbs-library/sessions`, `/v1/pbs-library/team-sessions`, `/v1/pbs-library/items/`, `/v1/pbs-library/images/`, `/v1/pbs-library/account`, `/v1/pbs-library/teams/` (same host), sending the design, its resized images, its name and the site address; the licence key is never sent. With your own R2/S3 bucket nothing is sent to Zinn Digital®. Terms: https://zinndigital.com/legal/terms · Privacy policy: https://zinndigital.com/legal/privacy

The plugin bundles the Freemius SDK, which handles licences and updates for the Pro edition and, only if you agree, product usage data.

Nothing is sent until you opt in on the screen shown after activation, or activate a licence. When you do, the SDK sends your site's URL, WordPress, PHP and plugin versions, language, and the administrator's name and email address to Freemius, and checks it periodically for licence status and updates. Before connecting it checks that the service is reachable by requesting `https://api.freemius.com/v1/ping.json`, which sends nothing about your site. You can opt out at any time from the plugin's Account page.

* Service: https://freemius.com
* Terms: https://freemius.com/terms/
* Privacy policy: https://freemius.com/privacy/

Google Fonts are hosted on your own site. Only when an administrator adds a font (in wp-admin or with `wp pbsw fonts download`) does the plugin download its stylesheet from `https://fonts.googleapis.com` and its files from `https://fonts.gstatic.com`, sending nothing about your site or visitors. Visitors load the fonts from your site; Google is never contacted when a page is viewed.

* Service: https://fonts.google.com
* Terms: https://developers.google.com/terms
* Privacy policy: https://policies.google.com/privacy

Background videos from YouTube or Vimeo, set on a section in Page Builder Sandwich 5.x and still shown by this version. Only for a YouTube or Vimeo background, once a visitor scrolls to it without asking for reduced motion, the browser loads the player from `https://www.youtube-nocookie.com` or `https://player.vimeo.com` ("do not track" on); until then the page shows a local poster. The player receives what any embed does (IP address, browser details, video ID); nothing else is sent.

* YouTube terms: https://www.youtube.com/t/terms
* YouTube (Google) privacy policy: https://policies.google.com/privacy
* Vimeo terms: https://vimeo.com/terms
* Vimeo privacy policy: https://vimeo.com/privacy

Map block. The map comes from OpenStreetMap (no key or account). The page shows only a notice, a "Show the map" button and a link; when a visitor presses the button, their browser loads `https://www.openstreetmap.org/export/embed.html` with the author's coordinates, sending what any embedded map does (IP address, browser details).

* Service: https://www.openstreetmap.org
* Terms: https://osmfoundation.org/wiki/Terms_of_Use
* Privacy policy: https://osmfoundation.org/wiki/Privacy_Policy

Maps saved with Page Builder Sandwich 5.x. New maps use OpenStreetMap as above. A page that still holds a map element saved by version 5.x shows it with Google's keyless map embed: when a visitor scrolls near the map, their browser loads `https://maps.google.com/maps` with the map's saved location, zoom and the page language, sending what any embedded map does (IP address, browser details and the address of the page). Pages without such a map never contact Google.

* Service: https://www.google.com/maps
* Terms: https://policies.google.com/terms
* Google Maps terms: https://www.google.com/intl/en/help/terms_maps/
* Privacy policy: https://policies.google.com/privacy

Booking calendar block (Calendly or Cal.com). The page shows a notice, a button and a link; only when a visitor presses the button does their browser load the author's booking page from `https://calendly.com` or `https://cal.com`, and they book directly with that service.

* Calendly terms: https://calendly.com/legal/customer-terms-conditions
* Calendly privacy policy: https://calendly.com/legal/privacy-notice
* Cal.com terms: https://cal.com/terms
* Cal.com privacy policy: https://cal.com/privacy

WhatsApp chat button and contact buttons. These are plain links to `https://wa.me/` with the number the author entered (and an optional message). Nothing is loaded from WhatsApp until a visitor presses one; WhatsApp then opens in the visitor's app or browser.

* WhatsApp terms: https://www.whatsapp.com/legal/terms-of-service
* WhatsApp privacy policy: https://www.whatsapp.com/legal/privacy-policy

Code and QR code blocks use no external service: highlight.js 11.12.0 (BSD-3-Clause) and qrcode-generator 2.0.4 (MIT) are bundled and served from your own site.

Importing an export file made by this plugin on another site. An image the file links to on another site is copied into your media library by WordPress's own `media_sideload_image()`: your site downloads that one image from the address in the file, which is usually the site the export came from. Nothing else is sent, and an import that links no outside images makes no request.

AI features use the AI provider you choose, with your own API key. Nothing is sent to any AI provider until you add a key under Settings → AI providers and use an AI feature. When you do, the text the feature needs (for example, the content being written or translated) and your key are sent to that one provider, and to no one else. Keys are stored encrypted in your database and are never sent to Zinn Digital®. The "Save and test" button sends one short test request to the provider.

* OpenAI: https://api.openai.com/v1 (terms: https://openai.com/policies/services-agreement/, privacy policy: https://openai.com/policies/privacy-policy/)
* Anthropic (Claude): https://api.anthropic.com/v1 (terms: https://www.anthropic.com/legal/commercial-terms, privacy policy: https://www.anthropic.com/legal/privacy)
* Google Gemini: https://generativelanguage.googleapis.com/v1beta (terms: https://ai.google.dev/gemini-api/terms, privacy policy: https://policies.google.com/privacy)
* Mistral AI: https://api.mistral.ai/v1 (terms: https://legal.mistral.ai/terms/commercial-terms-of-service/, privacy policy: https://legal.mistral.ai/terms/privacy-policy/)
* DeepSeek: https://api.deepseek.com (terms: https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html, privacy policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)
* OpenRouter: https://openrouter.ai/api/v1 (terms: https://openrouter.ai/terms, privacy policy: https://openrouter.ai/privacy)
* A service you run yourself (any OpenAI-compatible address you enter): only that address is contacted.

Images and pages sent to AI (only when you use these features). Writing alt text sends that image (resized) to your AI provider.

Recommended models list (off until ticked). The daily check under Settings → AI providers requests the signed list from https://api.zinndigital.com/v1/ai-model-catalogue, sending no key, site address or content. Connecting to Zinn® credits does not turn it on (in Pro it does).

* Service: https://zinndigital.com
* Terms: https://zinndigital.com/legal/terms
* Privacy policy: https://zinndigital.com/legal/privacy

= Help and support from Zinn Digital® (off until you use it) =

The plugin's Get help screen can send a support request to Zinn Digital®, the plugin's developer. Nothing is sent until you connect the site or send a request yourself.

* Connecting the site (Get help → Connect) calls `https://api.zinndigital.com/v1/plugin-support/connections` with the email address and name you type, the plugin's name and version, this site's address and title, the WordPress and PHP versions and your language. The answer is a connection token, stored encrypted in your database. The screen checks it with `/v1/plugin-support/connection`; Disconnect deletes it there and here.
* Sending a request calls `https://api.zinndigital.com/v1/plugin-support/tickets` with what you type (subject, message, your name), the plugin's name, your language, and your licence's plan and ids. Only if you tick "Include site details" does it add the site details the screen shows you before sending (site address, WordPress, PHP, theme and plugin versions, a few server settings and the last lines of the PHP error log).

== External services (Pro edition) ==

These services are used only by the Pro edition. Its code is not part of the free plugin from WordPress.org, so the free plugin never contacts them.

= Zinn Digital® hosting-customer discount (only on sites Zinn Digital® hosts) =

On a WordPress site hosted by Zinn Digital®, the plugin's screen shows administrators a card offering hosting customers a personal discount code for the Pro edition. Only when an administrator presses the card's button does the site send one request to Zinn Digital® at `https://api.zinndigital.com/v1/wp/pro-discount/<site id>`, containing the plugin's slug, the word `issue` and the administrator's WordPress language, signed with the site's own key. The card reuses only the host and site id of the address the platform writes into wp-config.php (ending in `/v1/wp/plugin-update/<site id>`) and sends nothing there itself. No other site shows the card. The answer is the customer's code and a Freemius checkout link, to which the browser is then sent. Terms: https://zinndigital.com/legal/terms · Privacy policy: https://zinndigital.com/legal/privacy

Figma import (Pro): only when an administrator connects their own Figma personal access token (stored encrypted) and imports a frame, the site calls https://api.figma.com/v1 (me, files/<file>/nodes, files/<file>/images, images/<file>) with that token and downloads the frame's images into the media library from the addresses Figma's API returns (Figma hosts them, for example on `s3.us-west-2.amazonaws.com`). Nothing is sent to Zinn Digital®.

* Service: https://www.figma.com
* Terms: https://www.figma.com/legal/tos/
* Privacy policy: https://www.figma.com/legal/privacy/

Crypto prices block (Pro). Your site, never your visitors, asks CoinGecko's public API (no key) for the coins and currency the block shows: `https://api.coingecko.com/api/v3/simple/price` and `https://api.coingecko.com/api/v3/coins/<coin>/market_chart`. Answers are kept on your site and refreshed in the background while pages with the block are visited; nothing about visitors is sent.

* Service: https://www.coingecko.com
* Terms: https://www.coingecko.com/en/terms
* Privacy policy: https://www.coingecko.com/en/privacy

Reviews block (Pro, only if you set it up under Appearance → Site design → Reviews).

* Google Places API (New): Google's terms do not allow its reviews to be stored, so when a visitor views a page with a Reviews block that shows Google, the visitor's browser asks your site for them and your site requests `https://places.googleapis.com/v1/places/<your place ID>` with your own API key (sent in a header). Each such page view is one request on your key, billed by Google. What is sent: the place ID, your key and the site language; nothing about the visitor. The plugin stores only the place ID. Terms: https://cloud.google.com/maps-platform/terms and https://cloud.google.com/maps-platform/terms/maps-service-terms, privacy policy: https://policies.google.com/privacy

Age gate and responsible gambling notice (Pro). Both use the visitor's country when your host or CDN provides it in the request (for example Cloudflare's `CF-IPCountry` header); the plugin never looks a country up and sends nothing anywhere to learn it. The age gate remembers the visitor's answer in a cookie on your own site. The notice can link to a national help service (for example GambleAware in the UK), chosen by the visitor's country and your site's language; those are links only, opened by the visitor.

Adobe Fonts (Pro, off unless you connect it). If you enter an Adobe Fonts kit id under Appearance → Site design → Custom fonts, the plugin requests `https://typekit.com/api/v1/json/kits/<kit id>/published` once when you save it, to read the kit's font names (the request carries only the kit id). From then on your pages, and the editor, load the kit's stylesheet from `https://use.typekit.net/<kit id>.css`, and your visitors' browsers download the fonts from Adobe. Remove the kit id to stop it.

* Service: https://fonts.adobe.com
* Terms: https://www.adobe.com/legal/terms.html
* Privacy policy: https://www.adobe.com/privacy/policy.html

YouTube and Vimeo videos in the video playlist and video gallery blocks (Pro). A page shows each video as a poster image stored on your own site and a play button; nothing is requested from YouTube or Vimeo until a visitor presses play. Then that one video's player is loaded from YouTube's privacy-enhanced domain `https://www.youtube-nocookie.com`, or from `https://player.vimeo.com` with "do not track" set, sending what any embedded video sends (the visitor's IP address and browser details, and the video's ID). The poster images are fetched once, when an author saves a post that uses these blocks (not when a page is viewed): from `https://i.ytimg.com` for YouTube, and for Vimeo the thumbnail address is read from `https://vimeo.com/api/oembed.json` and the image downloaded from `https://i.vimeocdn.com`. Those requests carry only the video's ID. A video given a poster from the media library is never fetched.

Terms and privacy policies: as for background videos above.

Newsletter signups (Pro, only if you set one up). A Newsletter signup block, or a newsletter form made with an older version, sends the email address a visitor types into it — and nothing else about them — from your server to the service you chose for that form, only when the visitor ticks the consent box and presses the button: Mailchimp (`https://<data centre>.api.mailchimp.com/3.0/`, with the API key you saved) or AWeber (`https://www.aweber.com/scripts/addlead.pl`, which also receives your site's address as the page to return the visitor to). MailPoet runs inside your own site and sends nothing elsewhere.

* Mailchimp: terms https://mailchimp.com/legal/terms/, privacy policy https://www.intuit.com/privacy/statement/
* AWeber: terms https://www.aweber.com/service-agreement.htm, privacy policy https://www.aweber.com/privacy.htm

Share buttons (Pro). The Share bar and Click to share quote blocks are plain links that open the chosen network's own share page (Facebook, X, LinkedIn, WhatsApp, Telegram, Reddit, Pinterest, Bluesky or Threads) with the page's address and title filled in. Nothing is loaded from those networks when a page is viewed; the network is contacted only if a visitor clicks the link, and then by the visitor's browser, under that network's terms and privacy policy.

Forms (Pro). A submission goes only where that form's settings say, sent from your server: your site's mail, your database, an https webhook you enter (the fields as JSON), or one mailing list. For a list, the visitor's email (and name) is sent with the API key you saved, stored encrypted and never sent to Zinn Digital®, to Mailchimp (`https://<dc>.api.mailchimp.com`), Brevo (`https://api.brevo.com`), MailerLite (`https://connect.mailerlite.com`), Kit (`https://api.kit.com`) or ActiveCampaign (`https://<account>.api-us1.com`). Terms and privacy: https://mailchimp.com/legal/, https://www.brevo.com/legal/, https://www.mailerlite.com/legal/, https://kit.com/terms, https://www.activecampaign.com/legal/.

Conversion tracking (Pro, only with an ID entered). Visitors' browsers load Google Analytics 4 or Tag Manager (`https://www.googletagmanager.com`) and the Meta Pixel (`https://connect.facebook.net`), sending page views and your conversions; with the cookie banner on, only after the visitor agrees. Terms and privacy: https://policies.google.com/privacy, https://www.facebook.com/privacy/policy/.

Designs from a screenshot or a website address: a screenshot is sent to your AI provider; a website address is fetched once from your site and only its text outline (headings, paragraphs, list items, button labels; never images or code) is sent to your AI provider.

== Installation ==

1. Upload the `page-builder-sandwich-by-zinn-digital` folder to the plugins folder, or install the zip from Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Open **Page Builder Sandwich** in the admin menu to set the class prefix.

== Frequently Asked Questions ==

= Why is the class prefix `zd`? =

It is short and says nothing about which plugin produced the markup. You can change it to any 1 to 8 lowercase letters or digits that start with a letter.

= Where do the front-end styles come from? =

From a copy in your uploads folder, written when the plugin is activated, updated, or its prefix is changed.

= Can an AI assistant build pages with it? =

Yes. The plugin adds WordPress abilities and its own MCP server at `/wp-json/pbsw/v1/mcp` (WordPress 6.9 or later): pages, sections, every block, block-level edits, kits and site design, with the same permission checks as the screens. Pro adds bulk page building and AI page writing with your own AI key. Create an application password under Users, Profile, then see Settings, AI agents (MCP). REST: `/wp-json/wp-abilities/v1/abilities`.

= Can I use an AI model on localhost or my own network? =

Private and local addresses are refused by default; to allow one, add `define( 'PBSW_ALLOW_PRIVATE_AI_URL', true );` to `wp-config.php`.

= Which icon sets are bundled? =

Font Awesome Free (CC BY 4.0), Lucide (ISC), Material Symbols (Apache 2.0) and Phosphor (MIT), as inline SVG; licences in `assets/icons/licenses/`.

= Where is the source code for the built scripts? =

The admin and editor scripts are built from the readable sources in `src/` with `@wordpress/scripts`; `package.json` and `package-lock.json` ship in the plugin folder. Run there:

`npm ci && npm run build`

The same source and build tools are public at https://github.com/Zinn-Digital/page-builder-sandwich

== Changelog ==

= 6.37.17 =
* Dependency security update (Renovate).

= 6.37.16 =
* Release build fixes: a form plugin's script moved off a block template is escaped where it is printed, and the WordPress coding standards pass on every file.

= 6.37.15 =
* An AI provider key saved by an older version is re-sealed from the admin screens only, never during a visitor's request; small admin screen fixes.

= 6.37.14 =
* WordPress.org review fixes and a shared admin kit update (diagnostics and promotions panels).

= 6.37.13 =
* WordPress.org review fixes: page styles and unfiltered HTML are only saved for users allowed to post them, old pages are converted in the background as the user who asked and wait for an administrator when they need one, stored AI keys are sealed with a random key for each site, the WordPress.org version no longer carries the MCP sign-in or any Pro code, and the Tajik and Somali translations are corrected.

= 6.37.12 =
* WP-CLI commands now use the plugin's four-letter prefix: `wp pbsw export`, `wp pbsw import`, `wp pbsw migrate` (Pro: `wp pbsw replace`, `wp pbsw importers`).
* Dependency security update (Renovate).

= 6.37.11 =
* Dependency security update (Renovate).

= 6.37.10 =
* Dependency security update (Renovate).

= 6.37.9 =
* Dependency security update (Renovate).

= 6.37.8 =
* Dependency security update (Renovate).

The full history of every release is in changelog.txt, in the plugin's folder.
