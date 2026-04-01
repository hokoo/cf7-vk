# cf7-vk

Development repository for a WordPress plugin that routes Contact Form 7 submissions to VK community dialogs.

## Current milestone

The repository now has the main integration layers in place:

- standalone plugin bootstrap in `plugin-dir/`;
- domain entities for bots, chats, channels, and form routing;
- React-based admin shell under the Contact Form 7 menu;
- REST field registration and `wpConnections` relations;
- local development scaffolding for WordPress, PHP, Node, and Docker;
- VK API transport primitives for connection checks, Long Poll bootstrap data, and outbound message sending;
- manual Long Poll synchronization and dialog linking by the per-bot authorization command;
- VK-oriented CF7 delivery formatting before outbound send.

At this point the core implementation plan is in place: a VK community can be verified, synchronized through Bots Long Poll, linked to dialogs by an exact auth command, and used to deliver CF7 notifications into active dialogs.

## Repository layout

```text
docs/         planning and project notes
install/      local development setup helpers and templates
plugin-dir/   WordPress plugin source
```

## Plan

The implementation plan for the project lives in `docs/PLAN.md`.

## Development

Typical local workflow:

```bash
make setup.all
cd plugin-dir/react && npm install
npm run build
```

`make setup.all` now mirrors the original project bootstrap more closely: it creates `.env`, generates nginx configs, starts Docker, installs root and plugin Composer dependencies inside the PHP container, prepares `dev-content` / `betas-content`, creates the `dev-content/plugins/cf7-vk -> plugin-dir` symlink, generates `index.php` / config files, and can initialize the dev+betas WordPress databases interactively.

Useful commands:

```bash
make docker.up
make docker.down
make npm.build
make git.wpc
make php.connect
make node.connect
```

## VK community setup

Before using the plugin, the target VK community should be prepared as a bot endpoint:

1. Enable **Community messages** in the VK community settings.
2. Create a community access token with rights sufficient for messages and Bots Long Poll.
3. Enable **Bots Long Poll API** for the community.
4. Enable at least the **message_new** event for Long Poll.
5. In the plugin UI, create a bot entry with `group_id`, token, API version, and the exact authorization command.
6. Ask the future recipient to send that exact command to the community dialog, then run **Fetch dialogs** in the admin UI.
7. Activate the discovered dialog and attach it to a channel. Only `active` dialogs receive CF7 notifications.

The auth command is matched by strict string equality after `trim()`. No aliases, prefixes, or fallback variants are applied automatically.
