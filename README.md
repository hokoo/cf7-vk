# cf7-vk

Development repository for a WordPress plugin that routes Contact Form 7 submissions to VK.

## Current milestone

The first implementation epic establishes the reusable application shell:

- standalone plugin bootstrap in `plugin-dir/`;
- domain entities for bots, chats, channels, and form routing;
- React-based admin shell under the Contact Form 7 menu;
- REST field registration and `wpConnections` relations;
- local development scaffolding for WordPress, PHP, Node, and Docker.

The actual VK transport layer is intentionally deferred to the next epic.

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
