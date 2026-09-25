=== Page Builder Sandwich ===
Contributors: zinndigital
Plugin URI: https://zinndigital.com/wordpress-plugins/page-builder-sandwich
Author: Neil Lock — CEO, Zinn Digital® Ltd
Author URI: https://zinndigital.com
Tags: page builder, blocks, footprint, clean html
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 6.0.0
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

= 6.0.0 =
* Rebuilt foundation: settings and About screen, footprint-free front-end output layer, sample content and language-switcher blocks.
