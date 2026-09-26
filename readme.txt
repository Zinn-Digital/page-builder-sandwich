=== Page Builder Sandwich ===
Contributors: zinndigital
Plugin URI: https://zinndigital.com/wordpress-plugins/page-builder-sandwich
Author: Neil Lock — CEO, Zinn Digital® Ltd
Author URI: https://zinndigital.com
Tags: page builder, blocks, footprint, clean html
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 6.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The foundation release of the rebuilt Page Builder Sandwich: clean, footprint-free front-end output and one sample content block.

== Description ==

This is the first release of Page Builder Sandwich rebuilt from the ground up. It contains the foundations the page-building features are built on, and nothing that pretends to be more than that.

= What this release does =

* **Footprint-free front end.** Everything the plugin prints on your pages uses neutral class names that start with a short prefix (`zd` unless you change it). There are no HTML comments, no generator tags, and the plugin's name does not appear in the page source.
* **Neutral asset paths.** Front-end styles are copied to `wp-content/uploads/<prefix>-assets/` under a content-hash file name, so page source does not point at the plugin's folder. If that folder cannot be written, the styles are printed inline instead.
* **A sample content block** (a heading and a paragraph, plain or accented) and a **sample language switcher block** that lists your languages when a compatible translation plugin provides them.
* **Settings and About screen** under the Page Builder Sandwich menu: set the class prefix and see the beta-update status for licensed installations.

The admin screens stay clearly branded; only what your visitors see is neutral.

= Build from source =

The admin screen and editor scripts are built from the human-readable sources in `src/` with `@wordpress/scripts`:

`npm ci && npm run build`

== External services ==

The plugin bundles the Freemius SDK, which handles licences and updates for the Pro edition and, only if you agree, product usage data.

Nothing is sent until you opt in on the screen shown after activation, or activate a licence. When you do, the SDK sends your site's URL, WordPress, PHP and plugin versions, language, and the administrator's name and email address to Freemius, and checks it periodically for licence status and updates. Before connecting it checks that the service is reachable by requesting `https://api.freemius.com/v1/ping.json`, which sends nothing about your site. You can opt out at any time from the plugin's Account page.

* Service: https://freemius.com
* Terms: https://freemius.com/terms/
* Privacy policy: https://freemius.com/privacy/

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

= 6.0.1 =
* Shared AI core: bring your own key for OpenAI, Anthropic, Gemini, Mistral, DeepSeek, OpenRouter or any OpenAI-compatible service; live model lists; signed recommended-models list; setup wizard with a test button; encrypted keys; clear error messages. Pro: usage and cost log, monthly limits, per-role permissions.

= 6.0.0 =
* Rebuilt foundation: settings and About screen, footprint-free front-end output layer, sample content and language-switcher blocks.
