# MC File Manager — Development Progress

This document tracks build progress against `DEVELOPMENT_PLAN.md`.

Legend: `[ ]` not started · `[~]` in progress · `[x]` done

---

## Phase mapping (this document ↔ plan §14)

Progress is tracked in **implementation order** (backend first). The table below maps
each block here to the canonical phases in `DEVELOPMENT_PLAN.md` §14.

| Progress section | Plan §14 phase | Status |
|------------------|----------------|--------|
| Phases 1–3 (Foundation, Services, REST) | Phase 1 Foundation + core backend | `[x]` |
| Phase 4 (Frontend shell) | Phase 2 Browser UI (shell) | `[x]` |
| Phase 5 (Browser UI) | Phase 2 Browser UI (components) | `[x]` |
| Phase 6 (Editor) | Phase 4 Editor | `[x]` |
| Phase 7 (Preview, properties, modals) | Phase 5 Preview + Phase 6 Audit/trash | `[x]` |
| Phase 8 (Settings) | Phase 7 Settings | `[x]` |
| Phase 9 (Polish) | Phase 8 Polish | `[x]` |
| Phase 10 (Pro-ready hooks) | Phase 9 Pro-ready hooks | `[x]` |
| Phase 11 (Post-v1 ZIP) | Phase 10 Post-v1 advanced ops | `[~]` |
| v1.1 (Concurrent-edit warning) | v1.1 | `[x]` |

**v1 core** (Phases 1–8) is complete. Polish, Pro hooks, post-v1 ops, and v1.1 are
tracked below.

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
- [x] search (filename, recursive from current path, capped)
- [x] upload / download / raw (inline preview)
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
- [x] Clipboard (cut/copy/paste) + keyboard shortcuts — Ctrl+C/V/X/S, Delete, F2, F5, Ctrl+F
- [x] Drag-and-drop upload + internal move

## Phase 6 — Editor

- [x] Monaco integration + tabs (per-file models, lazy-loaded)
- [x] Manual save workflow (Ctrl+S), dirty indicators
- [x] Edit warning (toggleable in settings)
- [x] File size guard + download fallback

## Phase 7 — Preview, properties, modals

- [x] Image preview (inline `GET /raw` endpoint)
- [x] Text preview (editor)
- [x] Properties modal (with snapshot rollback)
- [x] Settings modal
- [x] Trash viewer + restore + purge
- [x] Logs viewer (paginated)

## Phase 8 — Settings + uninstall behavior

- [x] Warning toggle, size limit, snapshot retention, trash toggle, theme, uninstall cleanup options

---

## Phase 9 — Polish (plan §14 Phase 8)

- [x] Animations and transitions — toast enter/leave, modal fade CSS
- [x] Empty states — basic messages in tree, browser, search
- [x] Loading skeletons for tree and file list
- [x] Keyboard shortcuts — F2, Delete, Ctrl+C/V/X/S, F5 refresh, Ctrl+F focus search
- [x] Responsive panes — mobile tab bar (Explorer | Files | Editor) at ≤768px
- [x] Error handling polish — list/tree error states with Retry button

## Phase 10 — Pro-ready hooks (plan §14 Phase 9)

- [x] Recently opened files — per-user list via user meta + REST `/recent`
- [x] Chunk upload abstraction — `UploadHandlerInterface`, `StandardUploadHandler`, `ChunkUploadHandler` stub, `mcfm_upload_handler` filter
- [x] Advanced search architecture — `AdvancedSearchService` + `mcfm_advanced_search` filter
- [x] Search scope option — `scope` param (`down` | `folder` | `site`); Pro-gated for folder/site; toolbar select
- [x] Role-based folder visibility — `RoleFolderService` + settings JSON (Pro UI in Settings modal)

Hooks: `mcfm_is_pro`, `mcfm_authorize_path`, `mcfm_required_capability`, `mcfm_upload_handler`, `mcfm_advanced_search`.

## Phase 11 — Post-v1 advanced file operations (plan §14 Phase 10)

- [x] ZIP archive create — toolbar + `POST /archive`
- [x] ZIP extract (unzip) — context menu + `POST /extract`
- [~] Bulk operations on multi-select — ZIP from selection; dedicated bulk toolbar actions pending
- [x] chmod / permissions change — `POST /chmod` (API ready; UI in properties modal pending)
- [x] File hash (MD5/SHA256) in properties modal

## v1.1 — Concurrent-edit warning (plan §14 v1.1)

- [x] Detect when another admin session has the same file open — transient-based `OpenRegistryService`
- [x] Non-blocking warning banner in editor (no hard lock)
- [x] Audit log entry for concurrent-open events

---

## Build status

- [x] `npm install`
- [x] `npm run build` produces `assets/build/` (`mcfm-app.js`, `mcfm-app.css`, Monaco chunks/workers)
- [x] Assets wired into admin page (ES module, only on plugin page)

## Verification

- [x] All PHP files pass `php -l`
- [x] Vite build succeeds with no errors
- [x] Path resolver security harness: traversal, encoded, null-byte, and symlink escapes all blocked; leading-slash paths stay jailed
- [x] PHP 7.4 compatibility: union catch types replaced; minimum requirement lowered from 8.2 to 7.4 (authoritative; supersedes CP2 planning answer)

---

## How to run

The plugin lives at the WordPress plugins path (e.g. `wp-content/plugins/mc-file-manager`).
Activate it in wp-admin → Plugins, then open the "File Manager" menu item.
Compiled assets are already in `assets/build/`. To rebuild: `npm install && npm run build`.

## Deferred / out of v1 scope

See `DEVELOPMENT_PLAN.md` §13. Summary:

- **Pro:** search scope toggle, advanced/content search, chunk uploads, recently opened files, role-based folder visibility
- **Post-v1:** ZIP/unzip, bulk ops, chmod, file hash
- **v1.1:** concurrent-edit warning
- **Not planned:** SFTP, S3, Git, terminal, malware scan

## Changelog

- Init: read plan, scaffolded project skeleton.
- Backend: bootstrap, autoloader, DB tables, security/path resolver, filesystem driver + service, repositories, audit/trash/revision/preview/settings services, full REST API, activator/uninstall.
- Frontend: Vue 3 SPA (toolbar, tree, browser, breadcrumbs, editor, preview, status bar, context menu, toasts, modals), Pinia store, nonce-aware API client, Monaco editor.
- Build: Vite pipeline configured and producing assets; theme variables fixed to mount element; relative asset base for fonts/workers.
- PHP 7.4: lowered minimum from 8.2; replaced union catch types in `RestController` with a PHP 7.4-safe helper; updated plugin header and `readme.txt`.
- Docs: alignment pass — phase mapping table, explicit Polish/Pro/post-v1/v1.1 tracking, search scope and role folders moved to Pro, `GET /raw` documented in plan.
- Phase 9–11 + v1.1: polish (skeletons, F5/Ctrl+F, responsive tabs, error retry), Pro hooks (recent files, search scope, role folders, upload/search abstractions), ZIP create/extract, file hashes, concurrent-edit warning via open registry.
- FOUND_BUGS pass: context menu fix (row stopPropagation + tree), VS Code (light) theme label, search scope dark-theme colors, search clear button + debounce, disabled Pro scopes, back/forward navigation history.
- FOUND_BUGS batch 2: activity log usernames, snapshot delete, ZIP download (multi/folder), rubber-band selection, snapshot metadata in file list.
