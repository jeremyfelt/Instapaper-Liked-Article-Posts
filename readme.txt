=== Instapaper Liked Article Posts ===

Contributors: jeremyfelt
Donate link: http://www.jeremyfelt.com/wordpress/plugins/instapaper-liked-article-posts/
Tags: instapaper, custom-post-type, rss, feed, automatic, liked
Requires at least: 3.2.1
Tested up to: 3.3
Stable tag: 0.3

Checks your Instapaper 'Liked' article RSS feed and creates new posts. Another step towards owning your data.

== Description ==

Instapaper Liked Article Posts checks your Instapaper 'Liked' article RSS feed on a regular basis and creates new posts with that data under a new custom post type (or any other post type you choose). Another step in owning your data.

By using this plugin, you can automatically share articles that you find interesting with readers of your blog or archive them for your own purposes. The reason I built this plugin is specifically for data ownership. I wanted a place to store the data I was creating through the awesome Instapaper service.

Settings are available for:

* Instapaer RSS Feed
    * The RSS Feed for your 'Liked' articles through Instapaper
* Max Items To Fetch
    * How many items to fetch at a time.
* Post Type
    * By default, a custom post type for Instapaper items is added, but you have the ability to choose any post type for new items to publish under.
* Post Status
    * If you don't want items to publish immediately, you can always select to save them as private or draft instead.
* RSS Fetch Frequency
    * By default we check for new items every hour. This can currently be set to daily or twice daily as well.
    
== Installation ==

1. Upload 'instapaper-liked-article-posts.php' to your plugin directory, usually 'wp-content/plugins/', or install automatically via your WordPress admin page.
1. Activate Instapaper Liked Article Posts in your plugin menu.
1. Configure using the Instapaper Posts menu under Settings in your admin page. (*See Screenshot*)

That's it! The only option you absolutely need to configure in step 3 is the RSS feed URL of your Instapaper Liked items. Everything else is taken care of for you.

== Frequently Asked Questions ==

= Why aren't there any FAQs? =

*  Because nobody has asked a question yet.

== Screenshots ==

1. What the new Instapaper custom post type will look like.
1. The settings screen for Instapaper Liked Article Posts

== Changelog ==
= 0.3 =

* Due to the SimplePie default feed_fetch cache lifetime of 12 hours, regular feed checks weren't happening. Cache for this feed is not set to 30 seconds so that it *really* checks for new items.

= 0.2 =

* All the major work.

= 0.1 =

* It's alive!

== Upgrade Notice ==
= 0.3 =

* See changelog, you'll probably like this upgrade - more regular fetches and such.

= 0.2 =

* No upgrades yet. Initial install.
