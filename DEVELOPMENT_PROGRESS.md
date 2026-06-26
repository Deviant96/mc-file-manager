# MC File Manager — Development Progress

This document tracks build progress against `DEVELOPMENT_PLAN.md`.

Legend: `[ ]` not started · `[~]` in progress · `[x]` done

---

## Phase 1 — Foundation (backend core)

- [x] Plugin bootstrap (`mc-file-manager.php`, `MCFM\Plugin`, PSR-4 autoloader)
- [x] Activator / deactivator / uninstall (configurable cleanup)
- [x] Database tables (`logs`, `trash`, `snapshots`, `settings`)
- [x] Security service (capability, nonce, role-scope filter hook)
- [x] Path resolver + root jail + traversal protection
- [x] Filesystem driver interface + `LocalDriver`
- [x] Filesystem service

## Phase 2 — Services

- [x] Repositories (logs, trash, snapshots, settings)
- [x] Audit service
- [x] Trash service (hybrid delete, preserve structure)
- [x] Revision service (on-disk snapshots, retention)
- [x] Preview service (image/text classification + Monaco language map)
- [x] Settings service

## Phase 3 — REST API

- [x] Tree / children / breadcrumbs
- [x] list / folder / file / rename / move / copy / delete / restore
- [x] file (read) / save / snapshot / snapshot restore
- [x] search (filename, recursive, capped)
- [x] upload / download / raw (inline)
- [x] properties
- [x] logs / trash / purge
- [x] settings (get/post)
- All PHP files pass `php -l`.

## Phase 4 — Frontend shell

- [x] Vue 3 SPA + Vite build pipeline (relative base for plugin subpath)
- [x] Pinia state store (`src/stores/fileManager.js`)
- [x] API client (nonce-aware, XHR upload progress)
- [x] Layout: toolbar, panes, status bar, resizable editor

## Phase 5 — Browser UI

- [x] Lazy directory tree (`TreePane` / `TreeNode`)
- [x] File list + multi-select (ctrl/shift), sortable columns
- [x] Breadcrumbs
- [x] Context menu
- [x] Clipboard (cut/copy/paste) + keyboard shortcuts
- [x] Drag-and-drop upload + internal move

## Phase 6 — Editor

- [x] Monaco integration + tabs (per-file models, lazy-loaded)
- [x] Manual save workflow (Ctrl+S), dirty indicators
- [x] Edit warning (toggleable in settings)
- [x] File size guard + download fallback

## Phase 7 — Preview, properties, modals

- [x] Image preview (inline raw endpoint)
- [x] Text preview (editor)
- [x] Properties modal (with snapshot rollback)
- [x] Settings modal
- [x] Trash viewer + restore + purge
- [x] Logs viewer (paginated)

## Phase 8 — Settings + uninstall behavior

- [x] Warning toggle, size limit, snapshot retention, trash toggle, theme, uninstall cleanup options

## Build status

- [x] `npm install`
- [x] `npm run build` produces `assets/build/` (`mcfm-app.js`, `mcfm-app.css`, Monaco chunks/workers)
- [x] Assets wired into admin page (ES module, only on plugin page)

## Verification

- [x] All PHP files pass `php -l`
- [x] Vite build succeeds with no errors
- [x] Path resolver security harness: traversal, encoded, null-byte, and symlink escapes all blocked; leading-slash paths stay jailed

---

## How to run

The plugin lives at the WordPress plugins path (e.g. `wp-content/plugins/mc-file-manager`).
Activate it in wp-admin → Plugins, then open the "File Manager" menu item.
Compiled assets are already in `assets/build/`. To rebuild: `npm install && npm run build`.

## Notes / future (Pro-reserved, not built)

- Chunked uploads, advanced/content search, recently-opened files: backend hooks
  (`mcfm_authorize_path`, `mcfm_required_capability`) and a driver interface are in
  place so these can be added without reworking the UI or editor lifecycle.

## Changelog

- Init: read plan, scaffolded project skeleton.
- Backend: bootstrap, autoloader, DB tables, security/path resolver, filesystem driver + service, repositories, audit/trash/revision/preview/settings services, full REST API, activator/uninstall.
- Frontend: Vue 3 SPA (toolbar, tree, browser, breadcrumbs, editor, preview, status bar, context menu, toasts, modals), Pinia store, nonce-aware API client, Monaco editor.
- Build: Vite pipeline configured and producing assets; theme variables fixed to mount element; relative asset base for fonts/workers.
