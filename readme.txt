=== Astro Widget (Bloom Proxy) ===
Contributors: you
Tags: astrology, bloom, shortcode, api
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later

== Description ==
A shortcode + REST proxy for securely embedding Bloom AstroAPI horoscopes into WordPress.  
Usage: place `[astro_widget]` shortcode in any page.

== Installation ==
1. Upload the plugin to the `/wp-content/plugins/` directory or use the WordPress admin plugin uploader.
2. Add your Bloom API key to `wp-config.php`:
   `define('BLOOM_API_KEY', 'YOUR_KEY');`
3. Activate the plugin.
4. Insert `[astro_widget]` shortcode into a page.

== Changelog ==
= 0.1.0 =
* Initial release