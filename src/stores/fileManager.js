import { defineStore } from 'pinia';
import { api } from '../api/client';

let notificationId = 0;

export const useFileManager = defineStore('fileManager', {
  state: () => ({
    settings: { ...(api.boot.settings || {}) },
    user: api.boot.user || {},
    version: api.boot.version || '1.0.0',

    currentPath: '',
    breadcrumbs: [{ name: 'Root', path: '' }],
    entries: [],
    listing: false,
    listError: null,

    treeRoot: { name: 'WordPress Root', path: '', children: [], expanded: true, loaded: false },
    treeLoading: false,
    treeError: null,
    treeLoadingPaths: [],

    selection: [],
    lastSelectedIndex: -1,

    clipboard: null, // { mode: 'copy'|'cut', paths: [] }

    tabs: [], // { path, name, language, content, original, dirty, kind, tooLarge, entry }
    activeTab: null,

    search: { query: '', active: false, results: [], running: false, scope: 'down' },

    recentFiles: [],

    editorPeers: {}, // path -> [{ id, name }]

    notifications: [],

    sort: { key: 'name', dir: 'asc' },
  }),

  getters: {
    activeTabData(state) {
      return state.tabs.find((t) => t.path === state.activeTab) || null;
    },
    dirtyCount(state) {
      return state.tabs.filter((t) => t.dirty).length;
    },
    sortedEntries(state) {
      const { key, dir } = state.sort;
      const factor = dir === 'asc' ? 1 : -1;
      return [...state.entries].sort((a, b) => {
        if (a.isDir !== b.isDir) return a.isDir ? -1 : 1;
        let cmp = 0;
        if (key === 'size') cmp = a.size - b.size;
        else if (key === 'modified') cmp = (a.mtime || 0) - (b.mtime || 0);
        else cmp = a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
        return cmp * factor;
      });
    },
  },

  actions: {
    notify(message, type = 'info', timeout = 4000) {
      const id = ++notificationId;
      this.notifications.push({ id, message, type });
      if (timeout) {
        setTimeout(() => this.dismiss(id), timeout);
      }
      return id;
    },
    dismiss(id) {
      this.notifications = this.notifications.filter((n) => n.id !== id);
    },

    async openPath(path) {
      this.listing = true;
      this.listError = null;
      try {
        const data = await api.list(path);
        this.currentPath = data.path;
        this.entries = data.entries;
        this.selection = [];
        this.lastSelectedIndex = -1;
        const crumbs = await api.getBreadcrumbs(this.currentPath);
        this.breadcrumbs = crumbs.breadcrumbs;
        this.search.active = false;
      } catch (e) {
        this.listError = e.message;
        this.notify(e.message, 'error');
      } finally {
        this.listing = false;
      }
    },

    async refresh() {
      if (this.search.active) {
        await this.runSearch(this.search.query);
      } else {
        await this.openPath(this.currentPath);
      }
    },

    async loadTreeRoot() {
      this.treeLoading = true;
      this.treeError = null;
      try {
        const data = await api.getTree();
        this.treeRoot = {
          name: data.root.name,
          path: '',
          children: data.children.map(this.toTreeNode),
          expanded: true,
          loaded: true,
        };
      } catch (e) {
        this.treeError = e.message;
        this.notify(e.message, 'error');
      } finally {
        this.treeLoading = false;
      }
    },

    toTreeNode(node) {
      return {
        name: node.name,
        path: node.path,
        hasChildren: node.hasChildren,
        children: [],
        expanded: false,
        loaded: false,
      };
    },

    async loadTreeChildren(node) {
      if (node.loaded || this.treeLoadingPaths.includes(node.path)) return;
      this.treeLoadingPaths.push(node.path);
      try {
        const data = await api.getChildren(node.path);
        node.children = data.children.map(this.toTreeNode);
        node.loaded = true;
      } catch (e) {
        this.notify(e.message, 'error');
      } finally {
        this.treeLoadingPaths = this.treeLoadingPaths.filter((p) => p !== node.path);
      }
    },

    isTreeNodeLoading(path) {
      return this.treeLoadingPaths.includes(path);
    },

    // Selection -------------------------------------------------------
    select(entry, index, { ctrl = false, shift = false } = {}) {
      if (shift && this.lastSelectedIndex >= 0) {
        const sorted = this.sortedEntries;
        const [start, end] = [this.lastSelectedIndex, index].sort((a, b) => a - b);
        this.selection = sorted.slice(start, end + 1).map((e) => e.path);
      } else if (ctrl) {
        if (this.selection.includes(entry.path)) {
          this.selection = this.selection.filter((p) => p !== entry.path);
        } else {
          this.selection.push(entry.path);
        }
        this.lastSelectedIndex = index;
      } else {
        this.selection = [entry.path];
        this.lastSelectedIndex = index;
      }
    },
    isSelected(path) {
      return this.selection.includes(path);
    },
    clearSelection() {
      this.selection = [];
      this.lastSelectedIndex = -1;
    },

    // Clipboard -------------------------------------------------------
    copySelection() {
      if (!this.selection.length) return;
      this.clipboard = { mode: 'copy', paths: [...this.selection] };
      this.notify(`Copied ${this.selection.length} item(s)`, 'info', 2000);
    },
    cutSelection() {
      if (!this.selection.length) return;
      this.clipboard = { mode: 'cut', paths: [...this.selection] };
      this.notify(`Cut ${this.selection.length} item(s)`, 'info', 2000);
    },
    async paste() {
      if (!this.clipboard) return;
      const { mode, paths } = this.clipboard;
      for (const src of paths) {
        try {
          if (mode === 'copy') await api.copy(src, this.currentPath);
          else await api.move(src, this.currentPath);
        } catch (e) {
          this.notify(`${src}: ${e.message}`, 'error');
        }
      }
      if (mode === 'cut') this.clipboard = null;
      await this.refresh();
      await this.refreshTreeNode(this.currentPath);
      this.notify('Paste complete', 'success', 2000);
    },

    // File operations -------------------------------------------------
    async createFolder(name) {
      try {
        await api.createFolder(this.currentPath, name);
        await this.refresh();
        await this.refreshTreeNode(this.currentPath);
        this.notify(`Folder "${name}" created`, 'success', 2000);
      } catch (e) {
        this.notify(e.message, 'error');
      }
    },
    async createFile(name) {
      try {
        await api.createFile(this.currentPath, name);
        await this.refresh();
        this.notify(`File "${name}" created`, 'success', 2000);
      } catch (e) {
        this.notify(e.message, 'error');
      }
    },
    async rename(path, name) {
      try {
        await api.rename(path, name);
        await this.refresh();
        this.notify('Renamed', 'success', 2000);
      } catch (e) {
        this.notify(e.message, 'error');
      }
    },
    async deletePaths(paths) {
      try {
        const res = await api.remove(paths);
        if (res.errors && res.errors.length) {
          res.errors.forEach((err) => this.notify(`${err.path}: ${err.message}`, 'error'));
        }
        const deleted = (res.deleted || []).map((d) => d.path);
        this.tabs = this.tabs.filter((t) => !deleted.includes(t.path));
        if (this.activeTab && deleted.includes(this.activeTab)) {
          this.activeTab = this.tabs.length ? this.tabs[0].path : null;
        }
        await this.refresh();
        await this.refreshTreeNode(this.currentPath);
        this.notify(`Deleted ${deleted.length} item(s)`, 'success', 2000);
      } catch (e) {
        this.notify(e.message, 'error');
      }
    },

    async refreshTreeNode(path) {
      const node = this.findTreeNode(this.treeRoot, path);
      if (node) {
        node.loaded = false;
        if (node.expanded || node === this.treeRoot) {
          await this.loadTreeChildren(node);
          node.expanded = true;
        }
      }
    },
    findTreeNode(node, path) {
      if (node.path === path) return node;
      for (const child of node.children) {
        const found = this.findTreeNode(child, path);
        if (found) return found;
      }
      return null;
    },

    // Tabs / editor ---------------------------------------------------
    async openFile(entry) {
      const existing = this.tabs.find((t) => t.path === entry.path);
      if (existing) {
        this.activeTab = entry.path;
        this.registerEditorOpen(entry.path);
        return existing;
      }
      try {
        const data = await api.readFile(entry.path);
        const tab = {
          path: entry.path,
          name: data.entry.name,
          language: data.language,
          content: data.content || '',
          original: data.content || '',
          dirty: false,
          kind: data.kind,
          tooLarge: data.tooLarge,
          maxBytes: data.maxBytes,
          entry: data.entry,
        };
        this.tabs.push(tab);
        this.activeTab = tab.path;
        this.trackRecent(entry.path);
        this.registerEditorOpen(entry.path);
        return tab;
      } catch (e) {
        this.notify(e.message, 'error');
        return null;
      }
    },
    setTabContent(path, content) {
      const tab = this.tabs.find((t) => t.path === path);
      if (tab) {
        tab.content = content;
        tab.dirty = content !== tab.original;
      }
    },
    closeTab(path) {
      const idx = this.tabs.findIndex((t) => t.path === path);
      if (idx === -1) return;
      this.tabs.splice(idx, 1);
      if (this.activeTab === path) {
        this.activeTab = this.tabs.length ? this.tabs[Math.max(0, idx - 1)].path : null;
      }
      this.registerEditorClose(path);
    },
    async saveTab(path) {
      const tab = this.tabs.find((t) => t.path === path);
      if (!tab) return;
      try {
        const res = await api.save(tab.path, tab.content);
        tab.original = tab.content;
        tab.dirty = false;
        tab.entry = res.entry;
        this.notify(`Saved ${tab.name}`, 'success', 2000);
        if (this.currentPath === (tab.path.split('/').slice(0, -1).join('/') || '')) {
          await this.refresh();
        }
      } catch (e) {
        this.notify(e.message, 'error');
      }
    },

    // Search ----------------------------------------------------------
    async runSearch(query, scope = this.search.scope) {
      this.search.query = query;
      this.search.scope = scope;
      if (!query) {
        this.search.active = false;
        this.search.results = [];
        return;
      }
      this.search.running = true;
      this.search.active = true;
      try {
        const data = await api.search(query, this.currentPath, scope);
        this.search.results = data.results;
      } catch (e) {
        this.notify(e.message, 'error');
      } finally {
        this.search.running = false;
      }
    },
    clearSearch() {
      this.search = { query: '', active: false, results: [], running: false, scope: this.search.scope || 'down' };
    },

    // Recent files (Pro) ----------------------------------------------
    async loadRecent() {
      if (!api.boot.isPro) return;
      try {
        const data = await api.getRecent();
        this.recentFiles = data.items || [];
      } catch (e) {
        // Non-fatal for Pro hook.
      }
    },
    async trackRecent(path) {
      if (!api.boot.isPro || !path) return;
      try {
        const data = await api.addRecent(path);
        this.recentFiles = data.items || [];
      } catch (e) {
        // Non-fatal.
      }
    },

    // Editor open registry (v1.1) -------------------------------------
    async registerEditorOpen(path) {
      try {
        await api.editorOpen(path);
        await this.refreshEditorPeers(path);
      } catch (e) {
        // Non-fatal.
      }
    },
    async registerEditorClose(path) {
      try {
        await api.editorClose(path);
        const next = { ...this.editorPeers };
        delete next[path];
        this.editorPeers = next;
      } catch (e) {
        // Non-fatal.
      }
    },
    async refreshEditorPeers(path) {
      if (!path) return;
      try {
        const data = await api.editorPeers(path);
        this.editorPeers = { ...this.editorPeers, [path]: data.peers || [] };
      } catch (e) {
        // Non-fatal.
      }
    },
    peersForPath(path) {
      return this.editorPeers[path] || [];
    },

    // Settings --------------------------------------------------------
    async saveSettings(values) {
      try {
        const res = await api.updateSettings(values);
        this.settings = res.settings;
        this.notify('Settings saved', 'success', 2000);
      } catch (e) {
        this.notify(e.message, 'error');
      }
    },
  },
});
