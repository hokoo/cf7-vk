=== Message Bridge for Contact Form 7 and VK ===
Contributors: hokku, igortron
Tags: contact form 7,vk,vkontakte
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Send Contact Form 7 notifications to VK dialogs through a configurable message bridge.

== Description ==

Message Bridge for Contact Form 7 and VK sends Contact Form 7 submissions to VKontakte users through a connected VK community. The number of VK recipients is not limited by the plugin: users subscribe to messages from the connected community, and the community acts as the notification bridge.

To set up delivery:

1. Create a VK community that will send the notifications.
2. In the VK community settings, open Community management > Messages and enable community messages.
3. Open Advanced > API usage, go to the LongPoll API tab, enable LongPoll API, and select API version 5.199 from the dropdown.
4. Switch to Event types and enable Incoming messages.
5. Open the Access keys tab and create a community access token with permission to use community messages.
6. Open the Callback API tab and copy the `group_id` value.
7. In the plugin interface, click Create Bot.
8. Enter the Group ID, save the access token, or use the Copy PHP const controls to keep these values in PHP constants. Then wait for the bot status to become online. Configured and working bots are shown in blue.
9. Ask each VK recipient to open the community messages and send <code>start</code> to the community.
10. Approve or reject each subscription request in the plugin interface.
11. Create a channel in the plugin interface to connect specific Contact Form 7 forms with the VK bot/community that should send their notifications.

= Hooks =

Filter <code>cf7vk_skip_delivery</code>
Return a truthy value to stop delivery for the current Contact Form 7 submission before channels and messages are resolved.
Arguments: <code>$skip</code> (bool), <code>$contact_form</code> (WPCF7_ContactForm), <code>$submission</code> (WPCF7_Submission).

Filter <code>cf7vk_unfiltered_message</code>
Filters the Contact Form 7 mail body after mail-tag replacement and before VK-specific formatting.
Arguments: <code>$message</code> (string), <code>$submission</code> (WPCF7_Submission).

Filter <code>cf7vk_prepared_message</code>
Filters the formatted VK notification text before it is passed to the linked delivery channels.
Arguments: <code>$prepared_message</code> (string), <code>$submission</code> (WPCF7_Submission), <code>$contact_form</code> (WPCF7_ContactForm), <code>$mail</code> (array).

Action <code>cf7vk_channel_sendout</code>
Fires when a channel starts processing a prepared outgoing message, before bot and chat availability checks.
Arguments: <code>$channel</code> (iTRON\cf7Vk\Channel), <code>$message</code> (string), <code>$context</code> (array).

Action <code>cf7vk_delivery_exception</code>
Fires when delivery relation lookup, chat status lookup, or VK transport delivery fails. The <code>$chat</code> argument can be null for relation lookup errors.
Arguments: <code>$exception</code> (Throwable), <code>$channel</code> (iTRON\cf7Vk\Channel), <code>$chat</code> (?iTRON\cf7Vk\Chat), <code>$context</code> (array).

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

= 0.1.4 =
* Added PHP constant support and admin copy helpers for VK access tokens and group IDs.
* Expanded VK community setup documentation and corrected the documented delivery hooks.
* Added a WordPress Playground blueprint for installing Contact Form 7 and the latest stable plugin build.

= 0.1.3 =
* Clarified the WP Data Logger integration hook and aligned the localized admin script object with the plugin prefix.

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

