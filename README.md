=== FCM Push Notification from WP ===

Contributors: @anthlasserre [monapp.club]
Donate link: <https://monapp.club>
Tags: fcm, firebase, push, notification, android, ios, flutter
Requires at least: 4.6
Tested up to: 5.8.1
Stable tag: 1.4.2
Requires PHP: 5.6.20
License: GPLv2 or later
License URI: <https://www.gnu.org/licenses/gpl-2.0.html>

Notify your users using Firebase Cloud Messaging (FCM) when content is published or updated.

== Description ==

Notifications for posts, pages and custom post types.

Send notifications to users of your application from your website using Google's service, Firebase Push Notification.

Configure the plugin to start sending notifications.

Send a notification when you post news or update your content.

Compatible with applications developed with the SDK Flutter.

When editing a post or page, the screen no longer has the option of sending a marked notification. If you want to send a notification when performing an update you need to check the Send Push Notification option.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/fcm-push-notification-from-wp` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Settings->FCM Push Notification from WordPress screen to configure the plugin.
4. In the FCM Options put the FCM API Key and put the topic name registered in your app.
5. Optionally put the image url to display in the notification.

== Frequently Asked Questions ==

= Can I send to one user only? =

No. You can only send to the topic informed in the plugin configuration.

All users receive notification.

= Can I disable sending on the publication screen? =

Yes. Uncheck the checkbox to not send a notification.

== Screenshots ==

1. Plugin settings screen.
2. Sending from a post.
3. Sending from a custom post type.
4. Sending from a page.

== Changelog ==

= 1.0.0 =

* First version.

= 1.1.0 =

* Fixed the display of the title and message when the summary is not informed.

= 1.2.0 =

* When editing a post or page, the screen no longer has the option of sending a marked notification. If you want to send a notification when performing an update you need to check the Send Push Notification option.
