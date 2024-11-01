=== Superfast Mailgun for the Newsletter plugin ===
Plugin Name: Superfast Mailgun for the Newsletter plugin
Plugin URI: https://howfrankdidit.com/superfast-newsletters-with-mailgun
Contributors: franciscus, freemius
Tags: newsletter,mailgun,add-on
Requires at least: 4.6
Tested up to: 6.6.1
Requires PHP: 7.3
Stable tag: 1.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

A faster Mailgun add-on for The Newsletter Plugin. Very useful if you have a big mailing list.

== Description ==
The Superfast Mailgun add-on for the <a href="https://wordpress.org/plugins/newsletter/">Newsletter</a> plugin allows you to take full advantage of the Mailgun “Batch Send” API. This makes it possible to send a newsletter to up to 1,000 addresses in a single API call. 

For 10,000 subscribers, that’s 10 API calls instead of 10,000 calls. Quite a difference. It will take a lot less time to send out your newsletter.

* Sends newsletters to up to 1,200 subscribers per hour.  

* Polls Mailgun for “bounces” and updates your mailing list accordingly.

* Support by email or through the forum on wordpress.org

Note:
Filters 'newsletter_message_html', 'newsletter_message_text', 'newsletter_message_subject' and 'newsletter_message' are called only once per email, and receive a null $user parameter. This is because Mailgun takes care of replacing user-specific tags in the email.

== Pro version ==
For mailing lists with more than 1,000 subscribers, get the <a href="http://howfrankdidit.com/superfast-newsletters-with-mailgun">Pro version</a>!

* Sends up to 120,000 emails / hour

* Integrated Open/Click statistics (Requires the Newsletter Statistics add-on)

* Selectable polling frequency

* Transactional emails (e.g. Welcome email, subscription confirmation) can use a different route, e.g. using a different domain

* Priority email support 
 
You can easily upgrade from within your WordPress dashboard.

== Installation ==
This add-on will only work if you have The Newsletter Plugin installed (version 6.3.3 or higher).

To use the Superfast Mailgun add-on, just install it like any other plugin. 

== Screenshots ==
1. General Settings
2. Advanced Settings
3. Polling Settings

== Changelog ==

= Version 1.2.5 =
* Verified compatibility with WP 6.3
* Removed support for built-in SMTP. Requires Newsletter 7.8.0 or higher.

= Version 1.2.4 =
* Use latest Freemius SDK

= Version 1.2.2 =
* Added support for filters 'newsletter_message_html', 'newsletter_message_text', 'newsletter_message_subject' and 'newsletter_message'

= Version 1.2.1 =
* Fix PHP 8.1 deprecation warnings
* Use latest Freemius SDK

= Version 1.2.0 =
* Compatible with PHP 8.1

= Version 1.1.6 =
* New version of Freemius SDK

= Version 1.1.5 =
* Added support for speed query by Newsletter plugin 7.3.1

= Version 1.1.4 =
* Verified compatibility with PHP 7.4

= Version 1.1.3 =
* Minor improvements

= Version 1.1.2 =
* Fix bug when sending to more than 1,000 addresses at 12,000/hour or more (Pro only)

= Version 1.1.1 =
* Fix issue causing stats not to be up-to-date (Pro only)

= Version 1.1.0 =
* Use new PHPMailer if WP version >= 5.5 and old PHPMailer otherwise
* Mime-encode the Display Name if necessary
* Hide submenu items in the Newsletter menu on the left

= Version 1.0.2 =
* Suppress confusing warning on admin pages
* Add link to support forum

= Version 1.0.1 =
* Fix layout issue on admin pages

= Version 1.0.0 =
* Initial published version
