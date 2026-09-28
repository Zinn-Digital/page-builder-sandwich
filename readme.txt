=== Page Builder Sandwich ===
Contributors: zinndigital
Plugin URI: https://zinndigital.com/wordpress-plugins/page-builder-sandwich
Author: Neil Lock — CEO, Zinn Digital® Ltd
Author URI: https://zinndigital.com
Tags: page builder, blocks, footprint, clean html
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 6.4.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The foundation release of the rebuilt Page Builder Sandwich: clean, footprint-free front-end output and one sample content block.

== Description ==

This is the first release of Page Builder Sandwich rebuilt from the ground up. It contains the foundations the page-building features are built on, and nothing that pretends to be more than that.

= What this release does =

* **Footprint-free front end.** Everything the plugin prints on your pages uses neutral class names that start with a short prefix (`zd` unless you change it). There are no HTML comments, no generator tags, and the plugin's name does not appear in the page source.
* **Neutral asset paths.** Front-end styles are copied to `wp-content/uploads/<prefix>-assets/` under a content-hash file name, so page source does not point at the plugin's folder. If that folder cannot be written, the styles are printed inline instead.
* **A sample content block** (a heading and a paragraph, plain or accented) and a **sample language switcher block** that lists your languages when a compatible translation plugin provides them.
* **A language switcher element.** With Tranzly active, it shows your site's languages as a list, pills, buttons, a dropdown or language codes, each linking to this page's translation, styled with your own colours, spacing and size. It is keyboard and screen-reader friendly, and flags are optional.
* **Settings and About screen** under the Page Builder Sandwich menu: set the class prefix and see the beta-update status for licensed installations.

The admin screens stay clearly branded; only what your visitors see is neutral.

= Bundled icon sets =

The icon picker offers these free icon sets, shipped with the plugin (editor only; a chosen icon is placed on the page as inline SVG): Font Awesome Free (icons: CC BY 4.0), Lucide (ISC), Material Symbols (Apache License 2.0) and Phosphor (MIT). Each set's licence is included in `assets/icons/licenses/`. The sets are rebuilt from their published packages, pinned by version and checksum, with `php wp/bin/pbs-icons-build.php` in the plugin's source repository.

= Workflow and agency tools =

* **Import and export** (free): move templates, patterns, the site design and the plugin's settings to another site as one file, or with `wp pbs export` / `wp pbs import`.
* **Roles and client mode** (Pro): choose what each role can do in the builder, and lock a role to editing text and images only.
* **Maintenance and pre-launch mode** (Pro): show visitors a page you designed, with the right answer for search engines.
* **Find and replace across all pages** (Pro): text, links or colours, with a preview first and one-click undo.
* **White label, client review and multisite** (Agency): rename the builder for client sites, let clients comment on a page before it goes live, and share templates across a network.

= Build from source =

The admin screen and editor scripts are built from the human-readable sources in `src/` with `@wordpress/scripts`:

`npm ci && npm run build`

== External services ==

The plugin bundles the Freemius SDK, which handles licences and updates for the Pro edition and, only if you agree, product usage data.

Nothing is sent until you opt in on the screen shown after activation, or activate a licence. When you do, the SDK sends your site's URL, WordPress, PHP and plugin versions, language, and the administrator's name and email address to Freemius, and checks it periodically for licence status and updates. Before connecting it checks that the service is reachable by requesting `https://api.freemius.com/v1/ping.json`, which sends nothing about your site. You can opt out at any time from the plugin's Account page.

* Service: https://freemius.com
* Terms: https://freemius.com/terms/
* Privacy policy: https://freemius.com/privacy/

Google Fonts are hosted on your own site. Only when an administrator adds a font (through the plugin's font endpoint in wp-admin, or `wp pbsw fonts download`), the plugin downloads that font family's stylesheet from `https://fonts.googleapis.com` and its font files from `https://fonts.gstatic.com`, sending nothing about your site or visitors beyond the request itself. The files are stored in your uploads folder and your visitors load them from your site; the plugin never contacts Google when a page is viewed.

* Service: https://fonts.google.com
* Terms: https://developers.google.com/terms
* Privacy policy: https://policies.google.com/privacy

Background videos from YouTube or Vimeo (Pro). Only when an author chooses a YouTube or Vimeo video as a section's background, a visitor's browser loads that video's player — from YouTube's privacy-enhanced domain `https://www.youtube-nocookie.com` or from `https://player.vimeo.com` with "do not track" set — and only once the visitor scrolls to that section and their device does not ask for reduced motion. Until then the page shows the poster image stored on your own site and contacts neither service. The request sends what any embedded video sends (the visitor's IP address and browser details, and the video's ID); the plugin sends nothing else.

* YouTube terms: https://www.youtube.com/t/terms
* YouTube (Google) privacy policy: https://policies.google.com/privacy
* Vimeo terms: https://vimeo.com/terms
* Vimeo privacy policy: https://vimeo.com/privacy

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

Adobe Fonts (Pro, off unless you connect it). If you enter an Adobe Fonts kit id under Appearance → Site design → Custom fonts, the plugin requests `https://typekit.com/api/v1/json/kits/<kit id>/published` once when you save it, to read the kit's font names (the request carries only the kit id). From then on your pages, and the editor, load the kit's stylesheet from `https://use.typekit.net/<kit id>.css`, and your visitors' browsers download the fonts from Adobe. Remove the kit id to stop it.

* Service: https://fonts.adobe.com
* Terms: https://www.adobe.com/legal/terms.html
* Privacy policy: https://www.adobe.com/privacy/policy.html

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
