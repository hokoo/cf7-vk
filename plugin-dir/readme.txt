=== Contact Form 7 VK Adapter ===
Contributors: hokku, igortron
Tags: contact form 7,vk,vkontakte
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Development build of a VK transport adapter for Contact Form 7.

== Description ==

This plugin is under active development. The current milestone establishes the routing shell and VK transport backend for a VK-based Contact Form 7 adapter:

1. Create VK bot/community records in the plugin UI.
2. Verify VK credentials and fetch Long Poll bootstrap data from the admin screen.
3. Create routing channels in the plugin UI.
4. Link Contact Form 7 forms to channels.
5. Continue with inbound chat discovery and linking in the next milestones.

= Hooks =

Filter <code>cf7vk_skip_delivery</code>
Use it to skip the delivery pipeline for a submission.

Action <code>cf7vk_channel_sendout</code>
Current shell action fired when a channel is asked to send an outgoing notification.

== Changelog ==

= 0.1.0 =
* Bootstrap plugin shell created from the reference architecture.
* React admin shell, CPTs, relations, settings page, and migration scaffolding added.
* VK API wrapper, bot ping endpoint, Long Poll bootstrap sync, and outbound send primitive added.

== Upgrade Notice ==

= 0.1.0 =
Initial development release.
