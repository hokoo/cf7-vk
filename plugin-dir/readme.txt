=== Message Bridge for Contact Form 7 and VK ===
Contributors: hokku, igortron
Tags: contact form 7,vk,vkontakte
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Send Contact Form 7 notifications to VK dialogs through a configurable message bridge.

== Description ==

Message Bridge for Contact Form 7 and VK is under active development. The current milestone establishes the routing shell, VK transport backend, and manual dialog onboarding flow:

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

== Source Code and Build Tools ==

The development repository for this plugin is publicly available at:
https://github.com/hokoo/cf7-vk

The admin assets bundled in this plugin are generated from the React source in that repository.

Current build flow:

1. Install dependencies in <code>plugin-dir/react</code> with <code>npm ci</code> or <code>npm install</code>.
2. Run <code>npm run build</code> in <code>plugin-dir/react</code> to regenerate the production assets.

== External Services ==

= VK API =

This plugin connects to the VK API to verify the configured community, request Bots Long Poll bootstrap data, load user and conversation details for connected dialogs, and send Contact Form 7 notifications to VK dialogs.

When an administrator verifies or syncs a connection, the plugin sends the configured community ID, community access token, API version, and the identifiers required for the requested VK API call, such as peer IDs, conversation message IDs, and user IDs.

When a Contact Form 7 submission is delivered, the plugin sends the destination dialog peer ID and the formatted notification text. That notification text can include the form title, mail subject, and submitted field values.

This service is provided by VK:
Terms of Service: https://vk.com/terms
Privacy Policy: https://vk.com/privacy
API documentation: https://dev.vk.com/

= VK Bots Long Poll API =

This plugin connects to the VK Bots Long Poll API to discover dialogs that send the configured authorization command to the connected community and to fetch new message events for linked dialogs.

When an administrator runs dialog sync, the plugin sends the current Long Poll server key and timestamp issued by VK. VK returns new community message events and related dialog metadata, which can include peer IDs, sender IDs, conversation message IDs, message text, and chat titles.

This service is provided by VK:
Terms of Service: https://vk.com/terms
Privacy Policy: https://vk.com/privacy
Long Poll documentation: https://dev.vk.com/ru/api/bots-long-poll/getting-started

== Changelog ==

= 0.1.2 =
* Updated bundled dependencies and REST endpoint permissions for WordPress.org review.

= 0.1.1 =
* Updated plugin metadata, naming, external service disclosures, and release packaging for WordPress.org review.

= 0.1.0 =
* Bootstrap plugin shell created from the reference architecture.
* React admin shell, CPTs, relations, settings page, and migration scaffolding added.
* VK API wrapper, bot ping endpoint, Long Poll bootstrap sync, and outbound send primitive added.
* Manual Long Poll dialog discovery, VK chat model sync, and CF7 plain-text formatter added.
* Delivery is restricted to active dialogs, and the admin UI now exposes copyable auth commands and channel title editing.

== Upgrade Notice ==

= 0.1.2 =
Review fixes for bundled dependencies and REST endpoint permissions.
