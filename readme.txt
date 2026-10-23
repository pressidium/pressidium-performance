== Pressidium Performance ==
Author URI: https://pressidium.com/
Plugin URI: https://pressidium.com/open-source/performance-plugin/
Contributors: pressidium, overengineer
Tags: performance, optimizations, image optimization, convert webp, convert avif
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable Tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Speed up your WordPress site, improve Core Web Vitals and enhance user experience with one-click image optimization, CSS & JavaScript minification.

== Description ==

The Pressidium Performance plugin is designed to supercharge your site’s speed and enhance your visitors’ experience by reducing load times and improving your site’s performance scores. It optimizes JavaScript and CSS by minifying and merging files, and compresses images in your Media Library using modern formats like WebP and AVIF to accelerate media loading without sacrificing quality.

= 🪄 Optimize Your Images =

Speed up your site by compressing Media Library images in the background.

* Convert them to modern formats like **WebP or AVIF**
* Automatically **optimize on upload** or **bulk-optimize your entire library**
* **Control quality** by choosing between smaller file sizes or sharper images
* Optionally **keep original files** for peace of mind (automatically restored if you remove the plugin)

= ✂️ Minify Your Scripts and Stylesheets =

Improve your site’s performance by **reducing the size of your JavaScript and CSS files**, ensuring faster load times.

= 🗃️ Concatenate JavaScript and CSS Files =

Reduce HTTP requests and speed up loading across your entire site, by **combining multiple JavaScript and CSS files into a single bundle**.

**Smart, hash-based concatenation**, for faster delivery without breaking your site’s layout.

= 🕒 Lightweight Smart Background Operations =

* **All processes run seamlessly in the background** to minimize site impact, conserve server resources and prevent performance bottlenecks or timeouts.
* **Monitor** every optimization task running behind the scenes. **Pause, resume or cancel tasks** instantly.

= Source code =

The plugin source code and issue tracker are available on GitHub: https://github.com/pressidium/pressidium-performance

= About Pressidium =

This is a free and open source WordPress plugin developed by Pressidium®. Pressidium offers managed hosting for WordPress optimized for performance, security, and scalability, powered by Pressidium EDGE, a globally distributed platform engineered for nonstop performance, fault tolerance, and mission-critical reliability.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option — WordPress will handle the file transfer, and you won’t need to leave your web browser.

1. Log in to your WordPress dashboard
2. Navigate to the “Plugins” menu
3. Search for “Pressidium Performance”
4. Click “Install Now” and WordPress will take it from there
5. Activate the plugin through the “Plugins” menu on WordPress

= Manual installation =

1. Upload the entire `pressidium-performance` folder to the `wp-content/plugins/` directory
2. Activate the plugin through the “Plugins” menu on WordPress

= After activation =

1. Go to the plugin settings page (Settings -> Performance)
2. Select the optimizations you want to enable
3. Click “Save” to save your changes

== Frequently Asked Questions ==

= Is this plugin free? =

Yes! This plugin is 100% free and open source.

= How does this plugin improve my website’s performance? =

The plugin improves your site’s loading speed by optimizing images (WebP/AVIF), reducing file sizes, and combining multiple scripts and stylesheets to limit HTTP requests. These optimizations work together to deliver a faster, more responsive site that both users and search engines love.

= Will this plugin improve my Core Web Vitals scores? =

Yes, in most cases. By reducing file sizes, optimizing images and using modern formats (WebP/AVIF), the plugin helps improve key Core Web Vitals metrics such as Largest Contentful Paint (LCP) and First Input Delay (FID). However, it is important to note that there are many factors that can affect your Core Web Vitals scores. This plugin is just one of the tools you can use to improve your site’s performance.

= Do I need to have coding skills to use this plugin? =

Not at all. The plugin is built for everyone, whether you’re just getting started with your first site or you’re a seasoned developer. You can enable optimizations with simple toggles and preset options. Advanced users can hook into WordPress filters for deeper control.

= Does this plugin optimize my images automatically, and what formats are supported? =

Yes. The plugin can automatically compress and convert your images to WebP or AVIF when you upload them to your Media Library. You can also choose to bulk-optimize all your existing images.

= How does this plugin minify my JavaScript and CSS files? =

The plugin automatically removes unnecessary characters such as whitespace, line breaks, and comments from your JavaScript and CSS files. This reduces their size without affecting functionality, allowing browsers to load them faster.

= What is file concatenation, and how does it help speed up my site? =

Concatenation means merging multiple JavaScript or CSS files into a single bundle. This reduces the number of HTTP requests your site makes, which helps browsers load your pages faster, especially on mobile networks or slower connections.

= What are the server requirements for this plugin? =

This plugin requires PHP 8.1 or higher, and WordPress 6.7 or higher to run. The image optimization feature requires GD and/or Imagick to be installed on your server.

= Does this plugin provide caching options? =

No, this plugin doesn’t include any caching options. However, we strongly recommend pairing it with a caching solution from your hosting provider or a dedicated caching plugin.

= Can I exclude specific files from being minified or concatenated? =

Yes. You can exclude specific files from being minified or concatenated by adding their paths under the “Script exclusions” or “Stylesheet exclusions” sections.

= Can I exclude specific images from being optimized? =

Yes. You can exclude specific images from being optimized by adding their paths under the “Exclusions” section of the “Image optimization” tab.

= Is the image optimization lossless? =

You can pick between lossless (no quality loss) or lossy (smaller files with some compression) optimization for your images. Just open the “Image optimization” tab and set your preferred quality level. Remember: Lower quality means smaller file size, higher quality means sharper images.

= Can I revert my optimized images back to the originals? =

Yes, as long as the "Keep original files" option was enabled under the "Image optimization" tab when the images were optimized. Navigate to **wp-admin → Performance → Image optimization** and click the **"Revert optimized images"** button. The plugin will restore the original files and metadata in the background. Please note that if the original files were not retained, the revert process will skip those images since there is nothing to restore.

= Can I undo the changes made by the plugin? =

Yes. Minification and concatenation changes are automatically reverted when you uninstall the plugin. However, optimized images will **not** be reverted automatically upon uninstall, they will remain as-is. If you wish to restore your original images before removing the plugin, navigate to **wp-admin → Performance → Image optimization** and click the **"Revert optimized images"** button. This will restore the original files and metadata in the background. Make sure the "Keep original files" option was enabled under the "Image optimization" tab, otherwise the original files will no longer be available to revert to.

= Can I export/import the plugin settings? =

Yes, you can export/import the plugin settings. On wp-admin, go to Settings -> Performance, and use the “Export Settings” and “Import Settings” buttons.

= Do optimizations run in the background? =

Yes. Optimizations run in the background and do not require any manual action. You can enable or disable optimizations anytime from the plugin settings page. If your Media Library contains many images, the process may take a while. You can track its progress directly on the plugin settings page.

= Can I cancel an optimization process? =

Yes. You can cancel an optimization process by clicking the “Cancel” button under the “Background processes” tab on the plugin settings page. Please note that any changes made up to that point won’t be reverted.

= Why are my changes not getting saved? =

Make sure you have clicked the “Save” button on the plugin settings page.

= Where can I report any bugs and/or request additional features? =

If you have spotted any bugs, or would like to request additional features from the plugin, please [file an issue](https://github.com/pressidium/pressidium-performance/issues/).

== Screenshots ==

1. A clean overview to see how all your optimizations are performing at a glance.
2. Speed up your site by compressing Media Library images in the background.
3. Automatically optimize on upload or bulk-optimize your entire library
4. Convert images to modern formats like WebP or AVIF.
5. Minify your scripts and stylesheets.
6. Reduce HTTP requests by combining multiple JavaScript and CSS files into a single bundle.
7. Monitor every optimization task running behind the scenes.

== Changelog ==

= 1.2.0: Oct 23, 2026 =

* Integrate with Pressidium EDGE
* Introduce a "Clear minified files" button to delete all minified files and their records
* Introduce a "Clear concatenated files" button to delete all concatenated files and their records
* Introduce an "Only minify assets from this site" option to leave third-party scripts and stylesheets untouched when minifying (enabled by default)
* Introduce an "Only concatenate assets from this site" option to leave third-party scripts and stylesheets untouched when concatenating (enabled by default)
* Improve background process lock handling to hold locks for the entire duration of a run
* Expose the `pressidium_performance_concatenation_allowed_hosts` filter to allow concatenating assets from additional hosts
* Expose the `pressidium_performance_concatenation_build_timeout` filter to adjust the number of seconds before a concatenated bundle build is considered stuck
* Clear the Elementor cache and the Pressidium cache when image optimization completes
* Exclude files that fail to be processed from concatenated bundles instead of dropping the entire bundle, and keep serving them through their original tags
* Split concatenated stylesheets into segments at excluded stylesheets and `@import` rules to preserve the cascade order
* Minify each file as it is concatenated instead of minifying the entire bundle
* Run concatenated deferred scripts once the document has been parsed, in their original order, instead of at parse time
* Exclude scripts that fail to parse or are not valid UTF-8 from concatenated bundles instead of crashing the concatenation process
* Handle tags with an `integrity` attribute when minifying files, and improve how they are handled when concatenating files
* Include the content hash in minified filenames so browsers and caches always fetch the latest version
* Clean up minified files that are no longer referenced by any optimization record
* Stop inlining small images and fonts as base64 data URIs when minifying CSS
* Never match exclusion patterns that are invalid regular expressions
* Convert plugin own assets from PNG to WebP to reduce their size
* File minification is now disabled by default
* Fix an issue where JavaScript minification skipped regular scripts and minified non-JavaScript scripts instead
* Fix an issue where listing the items in the minification queue failed with a `TypeError`
* Fix an issue where a failed asset fetch could stall the minification queue
* Fix an issue where the body of an HTTP error response (e.g. a `404` page or a WAF challenge page) was minified or concatenated and served in place of the real asset
* Fix an issue where empty minified files were stored
* Fix an issue where local paths in `url()` and `@import` rules were not handled properly when minifying CSS
* Fix an issue where fragment-only `url()` references (e.g. `url(#blur)`) were rewritten as relative paths when minifying CSS
* Fix an issue where pruning comments while evaluating files for minification removed too much or too little code
* Fix an issue where URLs longer than 255 characters could not be stored properly in the optimizations table
* Fix an issue where database table schema changes were not applied to existing tables
* Fix an issue where newly introduced database tables were not created when upgrading the plugin
* Fix an issue where concatenation page mappings did not expire properly
* Fix an issue where partially written concatenated bundles could be served before they were complete
* Fix an issue where concurrent requests could build the same concatenated bundle at the same time
* Fix an issue where a late concatenation run could overwrite or delete a concatenated bundle that was already published
* Fix an issue where pages with the same stylesheets in a different order shared a concatenated bundle, breaking the cascade order of one of them
* Fix an issue where `style` tags and stylesheets that are not concatenated lost their place in the cascade when placed between concatenated stylesheets
* Fix an issue where top-level `var` and `function` declarations of concatenated scripts were not available as globals
* Fix an issue where non-ASCII characters could get corrupted in concatenated scripts
* Fix an issue where background processes and their state were left behind when the plugin was uninstalled
* Fix an issue where options and transients were left behind when the plugin was uninstalled
* Update “Tested up to” version to WordPress 7.1 to confirm compatibility

= 1.1.0: Apr 30, 2026 =

* Fix an issue where the Logs tab was failing to load entirely if the log file was too large
* Fix an issue where deleting an attachment that was scheduled for optimization could cause the process to get stuck
* Fix an issue where expired minified files were repeatedly refetched when their content was unchanged
* Fix an issue where optimizing images on sites with a large number of attachments in their Media Library could cause memory exhaustion errors
* Improve background process logging
* Introduce a new background process to revert optimized images back to their original state
* Introduce a new "Debug mode" option to log detailed information to a log file for troubleshooting (disabled by default)
* Log to server error logs instead of a custom log file by default to limit filesystem I/O
* Store in object cache the URLs of CSS and JavaScript files that should not be minified at all to prevent them from being repeatedly evaluated on every request
* Clean up expired minified files after a grace period to prevent files from accumulating on the filesystem

= 1.0.1: Jan 16, 2026 =

* Fix an issue where original images were overwritten when the source and target formats were the same
* Fix an issue where original images remained on the filesystem as orphaned files when optimized images were deleted from the Media Library
* Fix an issue where optimizing images resulted in double slashes in `srcset` attributes

= 1.0.0: Jan 9, 2026 =

* Initial version
