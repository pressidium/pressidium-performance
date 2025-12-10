== Pressidium Performance ==
Author URI: https://pressidium.com/
Plugin URI: https://pressidium.com/open-source/performance-plugin/
Contributors: pressidium, overengineer
Tags: performance, optimizations, image optimization, convert webp, convert avif
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.1
Stable Tag: 1.0.0
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

= Can I undo the changes made by the plugin? =

Yes. When you uninstall the plugin, all changes it made will be automatically reverted. To ensure your original images are restored, make sure the “Keep original files” option is enabled under the “Image optimization” tab.

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

= 1.0.0: Nov 22, 2025 =

* Initial version
