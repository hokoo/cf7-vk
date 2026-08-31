# React admin shell

This directory contains the admin UI for Message Bridge for Contact Form 7 and VK.

## Purpose in the first epic

The current React application is a shell for:

- creating VK bot/community records;
- creating routing channels;
- linking CF7 forms to channels;
- editing the base metadata that later epics will use for VK delivery and Long Poll.

## Commands

From `plugin-dir/react`:

```bash
npm install
npm run build
npm test -- --watch=false
```

The build emits stable `main.js`, `main.css`, `main.asset.php`, and `settings-content.html` files so WordPress can enqueue deterministic assets from PHP.
