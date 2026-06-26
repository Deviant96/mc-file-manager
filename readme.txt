=== MC File Manager ===
Contributors: mc
Tags: file manager, editor, admin, monaco, files
Requires at least: 6.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A safe, VS Code-style file manager for the entire WordPress installation. Browse, edit, snapshot, and recover files from a single admin SPA.

== Description ==

MC File Manager gives administrators a polished single-page file manager inside wp-admin:

* Lazy-loaded directory tree with a Tree | Files split layout
* Monaco editor with syntax highlighting, tabs, and manual save
* On-disk revision snapshots taken before every save (configurable retention)
* Hybrid trash that preserves the original folder structure, with restore
* Image and text preview, filename search, breadcrumbs, and a right-click context menu
* Drag-and-drop upload and download
* Full audit log of every action
* Strict root jail to the WordPress installation directory with path-traversal protection
* Configurable max editable file size (default 100 MB) with download fallback
* Switchable VS Code (dark) and WordPress (light) themes

== Security ==

Every REST request is capability-checked (manage_options) and nonce-protected.
All paths resolve through a canonical resolver that blocks traversal, encoded
tricks, null bytes, and symlink escapes.

== Build (developers) ==

The production site does not require Node.js. To rebuild the SPA:

    npm install
    npm run build

Compiled assets are written to `assets/build/`.

== Changelog ==

= 1.0.0 =
* Initial release.
