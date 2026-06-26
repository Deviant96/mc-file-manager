# Planning Alignment Report

**Date:** 2026-06-26  
**Scope:** Cross-check `DEVELOPMENT_PLAN.md` and `DEVELOPMENT_PROGRESS.md` against the five planning transcripts (`CHAT_PLANNING.md` through `CHAT_PLANNING_5.md`), with spot verification against the current codebase where progress claims completion.

---

## Executive summary

The **development plan** is a faithful consolidation of the five chat planning sessions. Roughly **85–90% of locked requirements** from the chats are reflected in `DEVELOPMENT_PLAN.md` with consistent naming (`mc-file-manager`, `MCFM`, `mcfm/v1`).

`DEVELOPMENT_PROGRESS.md` reports **near-complete v1 delivery** against a **reorganized phase model** (backend-first) that does not match the phase numbering in `DEVELOPMENT_PLAN.md`. Most core features are implemented, but several chat decisions were **never added to the plan**, **deferred without explicit tracking**, or **marked done while polish/future items remain thin**.

| Area | Alignment |
|------|-----------|
| Core architecture (REST, Vue SPA, filesystem layer, security) | Strong |
| Free v1 feature set (browse, edit, trash, snapshots, audit) | Strong |
| Branding and API namespace | Strong |
| PHP minimum version | Intentional drift (8.2 → 7.4) |
| Advanced file operations (ZIP, chmod, etc.) | Not planned, not built |
| Search scope toggle (folder vs entire site) | Partially implemented, no UI toggle |
| Phase documentation | Misaligned numbering |
| Polish phase (responsive, shortcuts, animations) | Under-tracked / incomplete |
| Role-based folder visibility | Planned hooks only |

---

## Document lineage

| Document | Role |
|----------|------|
| `CHAT_PLANNING.md` | Initial scope: use case, permissions, operations, editor, UI, security |
| `CHAT_PLANNING_2.md` | Platform versions, trash model, audit depth, lazy tree, Pro boundary |
| `CHAT_PLANNING_3.md` | REST vs AJAX, abstraction layer, themes, save mode, revisions, search strategy |
| `CHAT_PLANNING_4.md` | Breadcrumbs, context menu, properties, SPA, file-size limit, branding |
| `CHAT_PLANNING_5.md` | Final limits (100 MB), warning model, slug lock-in, tech stack, split view |
| `DEVELOPMENT_PLAN.md` | Authoritative specification synthesized from the above |
| `DEVELOPMENT_PROGRESS.md` | Build tracker (reordered phases, all major items checked complete) |

Early chat examples used placeholder prefixes (`wpfm`, `wp_wpfm_*`). Final sessions and the plan consistently use **`mcfm`** / **`mc-file-manager`**.

---

## Fully aligned decisions

These items appear in the final chat answers and are correctly captured in the plan and (where applicable) implemented.

| Requirement | Chat source | Plan | Progress / code |
|-------------|-------------|------|-----------------|
| Entire WordPress install (`ABSPATH` root jail) | CP1 #2, CP4 #7 | §6 Root jail | Done — path resolver + security harness |
| Admin-only v1; future custom capability | CP1 #4 | §6 Role access | Done — capability checks |
| Warning before edit; disable in settings | CP1 #3, CP4 #8–9, CP5 #2 | §5 Edit warning | Done — `SettingsModal`, editor flow |
| No per-file “dangerous file” confirmation | CP4 #9 | Locked req. line 30 | Done — generic warning only |
| Vue 3 + Monaco + no jQuery | CP1 #6–7 | §14 stack | Done |
| Node.js dev-only; Vite build | CP2 #11, CP5 #5 | §11 Build | Done |
| REST API (`/wp-json/mcfm/v1/`) | CP3 #1 | §9 REST | Done |
| Filesystem abstraction (`LocalDriver`) | CP3 #2 | §2B | Done |
| Hybrid trash; preserve structure | CP2 #3, CP3 #7 | §2E, §7 | Done |
| On-disk revisions; default 5; configurable | CP2 #10, CP3 #8 | §2F, §7 | Done |
| Lazy-loaded directory tree | CP2 #6 | § Locked req. | Done — `TreePane` / `TreeNode` |
| Tree \| Files split layout | CP5 #6 | §3 UI | Done |
| Full SPA on one admin page | CP4 #11 | Locked req. | Done |
| Manual save only | CP3 #5 | §5 Save mode | Done — Ctrl+S |
| Multi-tab Monaco editor | CP3 #4 | §5 Open tabs | Done |
| Image + text preview only (v1) | CP2 #9 | §2G | Done |
| Filename search (not content) | CP1 #9 | §4 Search | Done — recursive walk, capped 500 |
| Real-time filesystem search (no index) | CP3 #9 | Implied in §4 | Done |
| Custom DB tables + uninstall cleanup options | CP2 #5 | §7, §7 Uninstall | Done |
| Audit log | CP1 #13, CP2 #4 | §2D | Done — broader than “minimum” |
| Pro reserved: chunk upload, advanced search, recently opened | CP2 #7, CP3 #10, CP4 #5 | §12 | Hooks noted in progress; not built |
| Generic branding: MC File Manager / `mc-file-manager` / `MCFM` | CP4 #10, CP5 #3 | Locked req. | Done — plugin header, tables, REST |
| Max editable file size default **100 MB** | CP5 #1 (negotiated from 200 MB) | Locked req. line 39 | Done — default `104857600` bytes |
| WP 6.6+ | CP2 #1 | Locked req. line 9 | Done — `Requires at least: 6.6` |
| Breadcrumbs | CP4 #1 | §4 | Done |
| File properties modal | CP4 #4 | §4 | Done |
| Context menu (cut/copy/paste/delete) | CP4 #3 | §4 | Done (see nuance below) |
| Drag-drop upload + internal move | CP1 #8 | §4 | Done — no folder upload |
| Security first; traversal protection | CP1, CP4 #6 | §6, §15 | Done — verified in progress notes |

---

## Intentional evolutions (chat → plan)

These are **documented or justified changes**, not silent omissions.

### 1. PHP minimum: 8.2+ → 7.4+

| | |
|---|---|
| **Chat** | CP2 answer: “Yes go ahead with 6.6 and **8.2**” |
| **Plan** | PHP **7.4+** with explicit 7.4-safe syntax guidance (§11) |
| **Progress** | “PHP 7.4 compatibility: union catch types replaced; minimum lowered from 8.2 to 7.4” |
| **Verdict** | **Intentional drift**, documented in progress and `readme.txt`. Plan and plugin header agree on 7.4. Chat transcripts are stale on this point. |

### 2. Editable file size: 200 MB → 100 MB

| | |
|---|---|
| **Chat** | CP4 answer: 200 MB; CP5 recommends lowering; user accepts **100 MB** |
| **Plan** | Default 100 MB, configurable |
| **Verdict** | **Aligned** after CP5 negotiation. |

### 3. Audit log depth: “minimum” → expanded

| | |
|---|---|
| **Chat** | CP2 #4: minimum set (login, upload, delete, rename, move, copy, edit, download) |
| **Plan** | Also logs browse, open, save, restore, create folder/file, etc. (§2D) |
| **Code** | `browse`, `open`, `search` events logged in `RestController` |
| **Verdict** | **Plan exceeds chat “minimum”** — acceptable enhancement; login event not logged (reasonable for admin-only REST plugin). |

### 4. Early Pro wishlist trimmed

| | |
|---|---|
| **Chat** | CP3 #10 lists SFTP, S3, Git, diff, malware scan, etc.; user chose only chunk upload + advanced search (+ recently opened from CP4) |
| **Plan** | §12 matches trimmed Pro list; driver interface reserved for future |
| **Verdict** | **Aligned.** |

---

## Gaps and misalignments

### A. Never carried from chat into `DEVELOPMENT_PLAN.md`

| Chat decision | Source | Status in plan | Status in build |
|---------------|--------|----------------|-----------------|
| **Advanced file operations** (ZIP, unzip, bulk, chmod, owner/group, file hash) | CP1 FILE OPERATIONS answer: “Basic and Advanced” | Not in v1 scope | Not implemented |
| **Search scope option** (current folder vs entire site) | CP2 #8 | Plan says “search the current root scope” only — no toggle | Search walks recursively from `currentPath`; at root ≈ entire site; **no user-facing toggle** |
| **Folder upload** via drag-drop | CP1 #8 “all” | Not explicit | Not implemented (`webkitdirectory` absent) |
| **Multi-select drag** | CP1 #8 | Plan: “multi-select drag later if practical” | Not implemented |
| **Concurrent-edit warning** (no hard lock) | CP3 #6 “Warning Only” | Not mentioned | Not implemented |
| **VS Code Light theme** (third theme option) | CP3 #3 recommendation: WP + VSCode Dark + VSCode Light | Plan: “WP and VSCode styles” (ambiguous) | Only `vscode` (dark) and `wordpress` (light) in `SettingsModal` |
| **Login event in audit log** | CP2 minimum list | Omitted from plan audit list | Not logged |

**Severity:** Advanced ops and search-scope toggle are the largest **functional gaps** relative to early chat answers. The plan effectively **narrowed scope** without explicitly noting that “Advanced” operations from CP1 were dropped.

### B. Plan vs chat nuance not reflected in progress

| Topic | Plan / chat | Implementation vs progress claim |
|-------|-------------|----------------------------------|
| **Context menu items** | CP4 narrowed to delete, copy, paste, cut, save; plan lists cut/copy/paste/delete/**save** | Menu includes Open, Download, Rename, Properties — **broader than CP4 answer**; **Save is missing** from context menu |
| **Keyboard shortcuts** | CP4 table: F2, Delete, Ctrl+C/V/X/S, **Ctrl+F**, **F5** | `App.vue`: F2, Delete, Ctrl+C/V/X/S only — **no F5 refresh, no Ctrl+F search** |
| **Mobile responsive** | CP1 #11; plan Phase 8 | One `@media (max-width: 960px)` rule; **no tab/stack pane behavior** described in plan §3 |
| **Animations / empty states / loading skeletons** | Plan Phase 8 | Minimal spinners in CSS; empty states present; **no skeleton loaders** |
| **Role-based folder visibility** | CP1 #5, CP5 Free list | Plan: “planned”; `mcfm_authorize_path` filter exists — **no admin UI or role mapping table** |
| **REST `GET /raw` (inline)** | Not in plan endpoint list | Implemented and used for image preview — **undocumented extension** |

### C. `DEVELOPMENT_PROGRESS.md` vs `DEVELOPMENT_PLAN.md` phase model

The two documents use **different phase schemes**, which makes “100% complete” hard to interpret.

| DEVELOPMENT_PLAN phases | DEVELOPMENT_PROGRESS phases |
|-------------------------|----------------------------|
| 1 Foundation | 1 Foundation (backend core) |
| 2 Browser UI | 2 Services |
| 3 Core file operations | 3 REST API |
| 4 Editor | 4 Frontend shell |
| 5 Preview and properties | 5 Browser UI |
| 6 Audit and trash | 6 Editor |
| 7 Settings | 7 Preview, properties, modals |
| 8 Polish | 8 Settings + uninstall |
| 9 Pro-ready hooks | *(not a dedicated phase)* |

**Issues:**

1. Progress **merges** plan Phases 6–7 into its Phase 7 and has **no Phase 8 (Polish)** or **Phase 9 (Pro-ready hooks)** checklist.
2. Progress marks **all listed items `[x]`**, implying v1 is finished, while plan Phase 8 (polish) and Phase 9 (hooks) are **not explicitly tracked**.
3. “Pro-ready hooks” appear only in a short **Notes / future** paragraph, not as verifiable checklist items.

**Recommendation:** Either remap progress to plan phases or add a mapping table at the top of `DEVELOPMENT_PROGRESS.md`.

### D. Progress “done” vs codebase spot-check

| Progress claim | Spot-check result |
|----------------|-------------------|
| Phase 5 clipboard + keyboard shortcuts | Partial — core shortcuts yes; F5/Ctrl+F missing |
| Phase 7 trash viewer + logs | Appears complete from component list |
| Phase 8 settings | Complete for listed settings |
| Build + verification | Consistent with `npm run build` and `php -l` notes |
| Path resolver security harness | Claimed; aligns with plan §15 |

Overall: **core backend and primary UI paths are genuinely built**. Polish and several chat “nice to have” items are **overstated as complete** because they were folded into other phases without criteria.

---

## Requirement traceability matrix (high-signal items)

| ID | Requirement | CP | Plan | Progress | Code |
|----|-------------|-----|------|----------|------|
| R01 | Internal use → future WP.org + Pro | 1 | Implied | — | — |
| R02 | Full `ABSPATH` access | 1 | Yes | Done | Yes |
| R03 | Edit critical files + generic warning | 1,4,5 | Yes | Done | Yes |
| R04 | Admin only; future roles | 1 | Yes | Done | Yes |
| R05 | Role-based folder visibility | 1,5 | Planned | Hook only | Filter only |
| R06 | Basic file ops | 1 | Yes | Done | Yes |
| R07 | Advanced file ops (ZIP, chmod, …) | 1 | **No** | **No** | **No** |
| R08 | Monaco mid-tier editor | 1 | Yes | Done | Yes |
| R09 | Vue 3, no jQuery | 1 | Yes | Done | Yes |
| R10 | DnD: upload, move, multi, folder | 1 | Partial | Partial | Upload+move only |
| R11 | Filename search | 1 | Yes | Done | Yes |
| R12 | Search folder vs site toggle | 2 | **No** | **No** | Recursive from path only |
| R13 | Large install; lazy tree | 1,2 | Yes | Done | Yes |
| R14 | Mobile responsive | 1 | Phase 8 | Implicit | Minimal |
| R15 | VS Code layout | 1 | Yes | Done | Yes |
| R16 | Audit + trash + revisions | 1,2 | Yes | Done | Yes |
| R17 | WP 6.6+ | 2 | Yes | Done | Yes |
| R18 | PHP 8.2+ | 2 | **7.4+** | 7.4 | 7.4 |
| R19 | Admin login only (no re-auth) | 2 | Yes | Done | Yes |
| R20 | Hybrid trash | 2 | Yes | Done | Yes |
| R21 | Chunk upload Pro | 2 | Pro | Not built | Not built |
| R22 | REST API | 3 | Yes | Done | Yes |
| R23 | FS abstraction | 3 | Yes | Done | Yes |
| R24 | Dual themes | 3 | Yes | Done | 2 themes, not 3 |
| R25 | Manual save | 3 | Yes | Done | Yes |
| R26 | Concurrent edit warning | 3 | **No** | **No** | **No** |
| R27 | Snapshots on disk, default 5 | 3 | Yes | Done | Yes |
| R28 | Real-time search | 3 | Yes | Done | Yes |
| R29 | Breadcrumbs | 4 | Yes | Done | Yes |
| R30 | Context menu (minimal set) | 4 | save listed | Done | Broader menu, no save |
| R31 | Recently opened Pro | 4 | Pro | Not built | Not built |
| R32 | 100 MB edit limit | 5 | Yes | Done | Yes |
| R33 | `mc-file-manager` slug | 5 | Yes | Done | Yes |
| R34 | Tree \| Files split | 5 | Yes | Done | Yes |

---

## Consistency within `DEVELOPMENT_PLAN.md`

The plan is internally consistent. Minor internal notes:

1. **§4 Context menu** requires `save`, but save is normally a toolbar/editor action — matches chat ambiguity; implementation uses toolbar/editor for save.
2. **§12 Free list** includes “theme switching between WP and VSCode styles” — does not promise VS Code Light as a third skin.
3. **§9 REST** does not list inline/raw preview endpoint — implementation added one.
4. **§13 Phase 8 Polish** and **Phase 9 Pro hooks** have no counterpart in progress tracking.

---

## Recommended actions

### Documentation (low effort)

1. Add a **phase mapping table** to `DEVELOPMENT_PROGRESS.md` linking to plan §13 phases.
2. Add an **“Out of v1 scope”** section to `DEVELOPMENT_PLAN.md` explicitly listing CP1 advanced operations and CP2 search-scope toggle as deferred.
3. Document **`GET /raw`** (or equivalent) in plan §9 if it remains part of the API.
4. Note **PHP 7.4** as the authoritative minimum and mark CP2’s 8.2 answer as superseded.

### Product decisions (medium effort)

5. **Search scope:** Add setting or toolbar control: “Search current folder only” vs “Search from here downward” vs “Search entire site” — per CP2 #8.
6. **Advanced operations:** Either add a post-v1 phase for ZIP/chmod/hash or formally reject in plan.
7. **Polish phase:** Track Phase 8 items separately (F5, Ctrl+F, responsive panes, skeletons) instead of marking entire v1 complete.
8. **Concurrent-edit warning:** Add to plan as v1.1 or document as intentionally omitted.
9. **Role-based folders:** Keep as Pro/planned; add to progress as `[ ]` not done.

### Progress honesty (quick fix)

10. Split “Phase 8 — Polish” and “Phase 9 — Pro-ready hooks” in progress with explicit `[ ]` / `[~]` items rather than implying full completion.

---

## Conclusion

**`DEVELOPMENT_PLAN.md` is well aligned with the final state of the five planning chats** for the core product: a VS Code–style, admin-only, REST-backed WordPress file manager with Monaco editing, hybrid trash, disk snapshots, and audit logging. Naming, security model, and Free/Pro boundaries match CP5.

**Gaps concentrate in three areas:**

1. **Scope narrowing not written down** — CP1 “Advanced” file operations and CP2 search-scope choice never entered the plan.
2. **Polish and future features** — Plan Phases 8–9 and several CP3–CP4 details (extra shortcuts, responsive layout, concurrent-edit warning, third theme) are missing or only partially delivered, while progress reads as fully complete.
3. **Phase taxonomy drift** — Progress reorganizes work backend-first (reasonable) but obscures alignment with the plan’s phase checklist.

For release readiness: **functional v1 core is largely built and matches the plan’s main specification**; **chat-derived edge requirements and polish items need either implementation or explicit deferral** to keep planning documents trustworthy.

---

*Generated from static analysis of planning transcripts, `DEVELOPMENT_PLAN.md`, `DEVELOPMENT_PROGRESS.md`, and targeted codebase inspection.*
