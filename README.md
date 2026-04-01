# cf7-vk

Development repository for a WordPress plugin that routes Contact Form 7 submissions to VK.

## Current milestone

The repository now has the first two implementation layers in place:

- standalone plugin bootstrap in `plugin-dir/`;
- domain entities for bots, chats, channels, and form routing;
- React-based admin shell under the Contact Form 7 menu;
- REST field registration and `wpConnections` relations;
- local development scaffolding for WordPress, PHP, Node, and Docker;
- VK API transport primitives for connection checks, Long Poll bootstrap data, and outbound message sending.

The next milestone is inbound Long Poll processing and automatic chat linking by the per-bot authorization command.

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
composer install
cd plugin-dir && composer install
cd plugin-dir/react && npm install
npm run build
```
