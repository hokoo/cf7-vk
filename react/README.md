# React admin shell

This directory contains the admin UI for the `cf7-vk` plugin.

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

The build copies hashed CRA assets into stable `main.js` and `main.css` filenames so WordPress can enqueue deterministic paths from PHP.
