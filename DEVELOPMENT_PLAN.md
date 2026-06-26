# MC File Manager (mcfm) Development Plan

## Product goal

Build a WordPress admin file manager plugin that can safely browse, edit, and manage the full WordPress installation inside a single SPA admin page.

## Locked requirements

* WordPress 6.6+
* PHP 8.2+
* Admin only for v1
* Full WordPress install access
* Root jail to WordPress site directory only
* REST API backend
* Vue 3 frontend
* Monaco editor
* No jQuery
* No runtime Node.js dependency on the server
* Node.js only for development/build
* Full SPA inside one admin page
* Lazy-loaded directory tree
* Tree | Files layout
* Breadcrumb navigation
* Context menu
* File properties modal
* Image and text preview only for v1
* Filename search only for v1
* Manual save only
* Warning before editing, with option to disable in settings
* Warning only for file edits, no special dangerous-file confirmation
* Hybrid trash
* Preserve deleted file structure
* File revision snapshots on disk
* Default 5 snapshots, configurable
* Audit log and action history
* Recently opened files as Pro
* Chunk uploads as Pro
* Advanced search as Pro
* Max editable file size default 100 MB
* Path traversal protection
* Role-based folder visibility planned
* Custom tables allowed, with option to remove them on uninstall
* Generic branding:

  * Plugin name: **MC File Manager**
  * Slug: **mc-file-manager**
  * Namespace: **MCFM**

---

# 1. Architecture

## Backend

Use a layered PHP architecture:

* **REST controller layer**
* **Service layer**
* **Filesystem abstraction layer**
* **Repository layer** for DB tables
* **Security layer**
* **Snapshot/trash layer**
* **Audit layer**

This keeps WordPress hooks separate from file operations.

## Frontend

Use a Vue 3 single-page app mounted on one WordPress admin page.

Recommended structure:

* Left pane: directory tree
* Center pane: files and folders view
* Right pane or drawer: Monaco editor / preview
* Top toolbar: global actions
* Bottom status area: path, selection, file size, messages

The UI should feel like VS Code, not like a generic WordPress settings page.

---

# 2. Core plugin modules

## A. Admin bootstrap

* Register admin menu page
* Enqueue SPA assets only on plugin page
* Load nonce, permissions, settings, and root context
* Mount Vue app into a single root element

## B. Filesystem service

Create an abstraction layer so the UI never talks directly to raw PHP file functions.

Responsibilities:

* Resolve safe paths
* List directories
* Read files
* Save files
* Create folders/files
* Rename, move, copy, delete
* Get file metadata
* Stream downloads
* Handle uploads

Use one local driver for v1:

* `LocalFilesystemDriver`

Design it so future drivers can be added later without changing the UI:

* SFTP driver
* Remote storage driver
* Future Pro integrations

## C. Security service

Responsibilities:

* Authenticate every REST request
* Validate permissions
* Enforce root jail
* Prevent path traversal
* Restrict access by configured role scope
* Block access outside allowed root
* Sanitize all input
* Verify all file operations against canonical paths

## D. Audit service

Log every important action:

* browse
* open
* edit
* save
* upload
* delete
* restore
* rename
* copy
* move
* create folder
* create file
* download

Store who did it, when, what path, and outcome.

## E. Trash service

Implement hybrid delete:

* Deletion moves files/folders into a preserved trash structure
* Restore returns them to the original location
* Permanent purge can be a later admin action or setting

Preserve original folder structure inside the trash.

## F. Revision service

Before each save:

* snapshot the current file to disk
* keep only the latest configurable number of versions
* default retention: 5

Store revisions on disk, not in the database.

## G. Preview service

V1 supports:

* images
* text files

For text, preview can be plain text or editor read-only mode.
For images, use a preview modal or drawer.

---

# 3. UI layout

## Main layout

Use a 3-zone SPA:

### Top toolbar

* New file
* New folder
* Upload
* Refresh
* Search
* Save
* Restore
* Trash
* Settings

### Left pane

* Lazy-loaded tree
* Expand/collapse folder nodes
* Icons for folders and files

### Center pane

* Directory contents
* sortable list/grid
* selectable rows
* drag and drop targets

### Right pane / drawer

* Monaco editor
* image preview
* file properties
* messages and warnings

## Layout behavior

* Desktop first, but responsive
* On small screens, collapse panes into tabs or stacked panels
* Tree | Files split should remain the default desktop view

---

# 4. User interactions

## Breadcrumbs

Clickable breadcrumb segments for all folders in the current path.

## Context menu

Right-click menu on files and folders.

Required actions:

* delete
* copy
* paste
* cut
* save

You also still need the standard toolbar actions for discoverability.

## Drag and drop

Support:

* desktop files into the files pane for upload
* moving files/folders inside the file browser
* folder drops where allowed
* multi-select drag later if practical

## Search

V1 search should be filename-only and search the current root scope.

Later Pro:

* advanced search
* content search
* filters
* regex
* bulk results

## File properties modal

Show:

* name
* path
* type
* size
* modified date
* permissions
* location status
* snapshot availability
* trash status

## Recently opened files

Reserve for Pro, but plan the backend hooks now so it can be added without changing the editor lifecycle.

---

# 5. Editing behavior

## Monaco editor

Use Monaco for:

* syntax highlighting
* line numbers
* tabs
* basic search
* code-style editing

## Save mode

Manual save only.

No autosave in v1.

## Edit warning

Show a warning before opening a file for editing.

Include a setting:

* enable warning
* disable warning

## File size guard

Default editable file size:

* 100 MB

This should be configurable.

Important practical rule:

* if the file is too large, allow download and preview fallback
* do not try to force Monaco to handle files that will freeze the browser

## Open tabs

Support multiple open files in the editor area.

---

# 6. Security model

## Root jail

The plugin can only operate inside the WordPress installation root.

No access above it.

## Path traversal protection

All operations must resolve through a canonical path resolver.

Reject:

* `../`
* encoded traversal
* symlink escapes
* null-byte style tricks
* double-decoding tricks

## Role access

For v1:

* admin only

Design the permission layer so future role-based folder restrictions can be added later.

## File editing policy

Editable by default for all file types under the root, with size limits and warning controls.

## Safe operations

Every file operation should require:

* capability check
* nonce validation
* root validation
* path validation
* action logging

---

# 7. Database design

Use custom tables.

Recommended tables:

## `wp_mcfm_logs`

Stores audit events.

Fields:

* id
* user_id
* action
* target_path
* source_path
* status
* message
* created_at
* request_ip if desired

## `wp_mcfm_trash`

Stores trash metadata.

Fields:

* id
* original_path
* trash_path
* item_type
* deleted_by
* deleted_at
* restore_path
* status

## `wp_mcfm_snapshots`

Stores revision metadata.

Fields:

* id
* original_path
* snapshot_path
* version_number
* file_size
* created_by
* created_at

## `wp_mcfm_settings`

Stores plugin settings.

Fields:

* setting_key
* setting_value
* updated_at

## Optional later

If role-based folder visibility is implemented later, add a dedicated mapping table rather than overloading settings.

## Uninstall behavior

Add uninstall cleanup options:

* remove plugin tables
* keep plugin tables
* remove snapshot/trash directories
* keep files

This must be configurable.

---

# 8. Files on disk

Recommended plugin-managed storage paths:

* snapshots: `wp-content/uploads/mcfm-snapshots/`
* trash: `wp-content/uploads/mcfm-trash/`
* temp uploads: `wp-content/uploads/mcfm-temp/`

Keep trash and snapshots outside the plugin folder so updates do not wipe them.

---

# 9. REST API design

Use WordPress REST API under:

`/wp-json/mcfm/v1/`

Suggested endpoints:

## Tree and navigation

* `GET /tree`
* `GET /children?path=...`
* `GET /breadcrumbs?path=...`

## Files and folders

* `GET /list?path=...`
* `POST /folder`
* `POST /file`
* `POST /rename`
* `POST /move`
* `POST /copy`
* `POST /delete`
* `POST /restore`

## Editor

* `GET /file?path=...`
* `POST /save`
* `POST /snapshot`

## Search

* `GET /search?query=...&path=...`

## Upload and download

* `POST /upload`
* `GET /download?path=...`

## Metadata

* `GET /properties?path=...`

## Logs and trash

* `GET /logs`
* `GET /trash`
* `POST /purge`

## Settings

* `GET /settings`
* `POST /settings`

Every endpoint should be permission-checked and nonce-protected.

---

# 10. Frontend application structure

Suggested Vue modules:

## `app`

* bootstraps the SPA
* loads settings and permissions

## `layout`

* top toolbar
* panes
* status bar

## `tree`

* lazy directory tree component

## `browser`

* files/folders table or grid

## `editor`

* Monaco wrapper
* tabs
* save handling

## `preview`

* image/text preview

## `context-menu`

* right-click actions

## `breadcrumbs`

* clickable path segments

## `modals`

* properties
* warnings
* confirmations
* restore dialogs
* settings

## `state`

Use a central state store for:

* current path
* selected items
* open tabs
* clipboard state
* search state
* settings
* notifications

---

# 11. Build and development workflow

## Development-only Node.js use

Use Node.js only for:

* Vue build
* asset bundling
* editor integration
* static asset optimization

The production WordPress site does not need Node.js.

## Build tool

Use Vite.

## Output

Ship compiled assets in the plugin package.

## Package safety

Keep dependencies minimal and current.
Do not rely on deprecated jQuery-era plugins unless there is a strong reason.

---

# 12. Free vs Pro boundary

## Free v1

* full site browsing
* lazy tree
* files pane
* Monaco editor
* manual save
* upload
* download
* rename
* delete
* move
* copy
* create file/folder
* breadcrumbs
* context menu
* properties modal
* image and text preview
* filename search
* audit log
* trash
* revision snapshots
* warnings before edit
* adjustable editable file size
* split view layout
* theme switching between WP and VSCode styles

## Pro reserved

* chunk uploads
* advanced search
* recently opened files

Keep the codebase ready for these features, but do not build them into v1.

---

# 13. Recommended implementation phases

## Phase 1: Foundation

* plugin bootstrap
* admin page
* REST API skeleton
* authentication and capability checks
* filesystem abstraction
* root jail
* path resolver

## Phase 2: Browser UI

* Vue SPA shell
* tree view
* file list
* breadcrumbs
* context menu
* toolbar
* selection model

## Phase 3: Core file operations

* list
* open
* upload
* download
* create file/folder
* rename
* move
* copy
* delete
* restore

## Phase 4: Editor

* Monaco integration
* tabs
* save workflow
* edit warning
* file size guard
* revision snapshot creation

## Phase 5: Preview and properties

* image preview
* text preview
* properties modal
* status and metadata

## Phase 6: Audit and trash

* audit table
* log viewer
* trash viewer
* restore flows
* purge flows

## Phase 7: Settings

* warning toggle
* editable size limit
* snapshot retention
* trash behavior
* UI theme selection
* uninstall cleanup options

## Phase 8: Polish

* animations
* empty states
* loading skeletons
* keyboard shortcuts
* responsive behavior
* error handling

## Phase 9: Pro-ready hooks

* recently opened files hook
* chunk upload abstraction
* advanced search architecture

---

# 14. Key library choices

Recommended stack:

* **Vue 3**
* **Monaco Editor**
* **Pinia** for state
* **SortableJS** or native DnD if needed
* **Vite** for builds
* **Native WordPress REST API**
* **Native PHP filesystem abstraction**
* **Custom DB tables**
* **No jQuery**

---

# 15. Non-negotiable safety rules

* Never trust client paths
* Never allow root escape
* Never save without snapshotting first
* Never expose filesystem operations without capability checks
* Never load the whole tree at once
* Never assume large files are safe to edit in-browser
* Never couple UI state directly to filesystem calls
* Never make Pro-only features block the Free build

---

# 16. Final product shape

The first release should feel like this:

* Open one admin page
* See Tree | Files split
* Browse the entire WordPress install safely
* Right-click files and folders
* Drag and drop files
* Edit in Monaco
* Save manually
* Recover from trash
* Roll back to snapshots
* Search by filename
* See logs and properties
* Use a polished SPA instead of a WordPress settings form