=== VK Notifications for Contact Form 7 ===
Contributors: hokku, igortron
Tags: contact form 7,vk,vkontakte
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Development build of VK notifications for Contact Form 7.

== Description ==

This plugin is under active development. The current milestone establishes the routing shell, VK transport backend, and manual dialog onboarding flow for VK notifications for Contact Form 7:

1. Create VK bot/community records in the plugin UI.
2. Verify VK credentials and fetch Long Poll bootstrap data from the admin screen.
3. Discover dialogs by syncing Long Poll updates and matching the configured authorization command.
4. Create routing channels in the plugin UI.
5. Link Contact Form 7 forms and discovered dialogs to channels.
6. Activate the dialogs that are allowed to receive notifications.
7. Deliver Contact Form 7 submissions to VK as formatted plain-text notifications.

= Hooks =

Filter <code>cf7vk_skip_delivery</code>
Use it to skip the delivery pipeline for a submission.

Action <code>cf7vk_channel_sendout</code>
Current shell action fired when a channel is asked to send an outgoing notification.

Action <code>cf7vk_delivery_exception</code>
Fired when delivery fails or delivery prerequisites cannot be resolved for a target channel/chat.

== Changelog ==

= 0.1.0 =
* Bootstrap plugin shell created from the reference architecture.
* React admin shell, CPTs, relations, settings page, and migration scaffolding added.
* VK API wrapper, bot ping endpoint, Long Poll bootstrap sync, and outbound send primitive added.
* Manual Long Poll dialog discovery, VK chat model sync, and CF7 plain-text formatter added.
* Delivery is restricted to active dialogs, and the admin UI now exposes copyable auth commands and channel title editing.

== Upgrade Notice ==

= 0.1.0 =
Initial development release.
