<script setup>
import { onMounted, onBeforeUnmount, ref, reactive, computed, watch } from 'vue';
import { useFileManager } from './stores/fileManager';
import { api } from './api/client';
import Toolbar from './components/Toolbar.vue';
import TreePane from './components/TreePane.vue';
import Breadcrumbs from './components/Breadcrumbs.vue';
import FileBrowser from './components/FileBrowser.vue';
import EditorPane from './components/EditorPane.vue';
import StatusBar from './components/StatusBar.vue';
import ContextMenu from './components/ContextMenu.vue';
import Toasts from './components/Toasts.vue';
import PromptModal from './components/modals/PromptModal.vue';
import ConfirmModal from './components/modals/ConfirmModal.vue';
import PropertiesModal from './components/modals/PropertiesModal.vue';
import SettingsModal from './components/modals/SettingsModal.vue';
import TrashModal from './components/modals/TrashModal.vue';
import LogsModal from './components/modals/LogsModal.vue';

const store = useFileManager();
const toolbarRef = ref(null);
const mobilePane = ref('files');
const isMobile = ref(window.innerWidth <= 768);

const theme = computed(() => store.settings.theme || 'vscode');

// The theme CSS variables live on the #mcfm-app mount element, so apply the
// attribute there rather than on an inner wrapper.
function applyTheme(value) {
  const root = document.getElementById('mcfm-app');
  if (root) root.dataset.theme = value;
}
watch(theme, applyTheme, { immediate: true });

// Modal manager -------------------------------------------------------
const modals = reactive({
  prompt: null, // { title, label, value, onConfirm }
  confirm: null, // { title, message, danger, onConfirm }
  properties: null, // path
  settings: false,
  trash: false,
  logs: false,
});

function askPrompt(opts) {
  modals.prompt = { value: '', ...opts };
}
function askConfirm(opts) {
  modals.confirm = { danger: false, ...opts };
}

// Context menu --------------------------------------------------------
const contextMenu = reactive({ visible: false, x: 0, y: 0, entry: null });
function openContext({ x, y, entry }) {
  if (entry) {
    if (!store.isSelected(entry.path)) {
      store.selection = [entry.path];
      const idx = store.entries.findIndex((e) => e.path === entry.path);
      store.lastSelectedIndex = idx >= 0 ? idx : -1;
    }
  } else {
    store.clearSelection();
  }
  contextMenu.x = x;
  contextMenu.y = y;
  contextMenu.entry = entry;
  contextMenu.visible = true;
}
function closeContext() {
  contextMenu.visible = false;
}

// Editor pane resizing ------------------------------------------------
const editorWidth = ref(50);
const resizing = ref(false);
function startResize(e) {
  resizing.value = true;
  const startX = e.clientX;
  const startWidth = editorWidth.value;
  const containerWidth = e.target.closest('.mcfm-body').offsetWidth;
  const onMove = (ev) => {
    const delta = ev.clientX - startX;
    const next = startWidth - (delta / containerWidth) * 100;
    editorWidth.value = Math.min(80, Math.max(20, next));
  };
  const onUp = () => {
    resizing.value = false;
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

const showEditor = computed(() => store.tabs.length > 0);

// File interactions ---------------------------------------------------
async function activate(entry) {
  if (entry.isDir) {
    await store.openPath(entry.path);
  } else {
    await tryOpenFile(entry);
  }
}

async function tryOpenFile(entry) {
  const warn = store.settings.warn_before_edit;
  const kind = entry.ext;
  if (warn) {
    askConfirm({
      title: 'Edit file?',
      message: `You are about to open "${entry.name}" for editing. Changes are saved manually and a snapshot is taken before each save.`,
      confirmLabel: 'Open',
      onConfirm: () => store.openFile(entry),
    });
  } else {
    await store.openFile(entry);
  }
}

// Toolbar actions -----------------------------------------------------
function newFolder() {
  askPrompt({
    title: 'New folder',
    label: 'Folder name',
    onConfirm: (name) => name && store.createFolder(name),
  });
}
function newFile() {
  askPrompt({
    title: 'New file',
    label: 'File name',
    onConfirm: (name) => name && store.createFile(name),
  });
}
function renameEntry(entry) {
  askPrompt({
    title: 'Rename',
    label: 'New name',
    value: entry.name,
    onConfirm: (name) => name && name !== entry.name && store.rename(entry.path, name),
  });
}
function onResize() {
  isMobile.value = window.innerWidth <= 768;
}

function createZip() {
  if (!store.selection.length) return;
  askPrompt({
    title: 'Create ZIP archive',
    label: 'Archive name',
    value: 'archive.zip',
    onConfirm: async (name) => {
      if (!name) return;
      try {
        await api.createArchive(store.selection, name.endsWith('.zip') ? name : `${name}.zip`, store.currentPath);
        store.notify('ZIP archive created', 'success', 2000);
        store.refresh();
      } catch (e) {
        store.notify(e.message, 'error');
      }
    },
  });
}

function extractZip(entry) {
  askConfirm({
    title: 'Extract ZIP',
    message: `Extract "${entry.name}" into the current folder?`,
    confirmLabel: 'Extract',
    onConfirm: async () => {
      try {
        await api.extractArchive(entry.path, store.currentPath);
        store.notify('Archive extracted', 'success', 2000);
        store.refresh();
      } catch (e) {
        store.notify(e.message, 'error');
      }
    },
  });
}

function deleteEntries(paths) {
  if (!paths.length) return;
  askConfirm({
    title: 'Delete items',
    message: `Delete ${paths.length} item(s)? ${store.settings.trash_enabled ? 'They will be moved to Trash.' : 'This cannot be undone.'}`,
    danger: true,
    confirmLabel: 'Delete',
    onConfirm: () => store.deletePaths(paths),
  });
}

function downloadPaths(paths, hintEntry = null) {
  if (!paths.length) return;
  const needsZip =
    paths.length > 1 ||
    paths.some((p) => {
      const e = store.entries.find((x) => x.path === p) || (hintEntry?.path === p ? hintEntry : null);
      return e?.isDir;
    });
  if (needsZip) {
    window.open(api.downloadZipUrl(paths), '_blank');
  } else {
    window.open(api.downloadUrl(paths[0]), '_blank');
  }
}

function handleContextAction(action) {
  const entry = contextMenu.entry;
  closeContext();
  const targets = entry && store.isSelected(entry.path) ? store.selection : entry ? [entry.path] : store.selection;
  switch (action) {
    case 'open':
      if (entry) activate(entry);
      break;
    case 'rename':
      if (entry) renameEntry(entry);
      break;
    case 'delete':
      deleteEntries(targets);
      break;
    case 'copy':
      store.copySelection();
      break;
    case 'cut':
      store.cutSelection();
      break;
    case 'paste':
      store.paste();
      break;
    case 'download':
      downloadPaths(targets, entry);
      break;
    case 'extract':
      if (entry) extractZip(entry);
      break;
    case 'properties':
      if (entry) modals.properties = entry.path;
      break;
    default:
      break;
  }
}

function onKeydown(e) {
  const tag = (e.target.tagName || '').toLowerCase();
  if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) return;
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault();
    if (store.activeTab) store.saveTab(store.activeTab);
  } else if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
    store.copySelection();
  } else if ((e.ctrlKey || e.metaKey) && e.key === 'x') {
    store.cutSelection();
  } else if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
    store.paste();
  } else if (e.key === 'Delete' && store.selection.length) {
    deleteEntries(store.selection);
  } else if (e.key === 'F2' && store.selection.length === 1) {
    const entry = store.entries.find((x) => x.path === store.selection[0]);
    if (entry) renameEntry(entry);
  } else if (e.key === 'F5') {
    e.preventDefault();
    store.refresh();
  } else if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
    e.preventDefault();
    toolbarRef.value?.focusSearch();
  } else if (e.altKey && e.key === 'ArrowLeft') {
    e.preventDefault();
    store.goBack();
  } else if (e.altKey && e.key === 'ArrowRight') {
    e.preventDefault();
    store.goForward();
  }
}

watch(showEditor, (visible) => {
  if (visible && isMobile.value) mobilePane.value = 'editor';
});

onMounted(async () => {
  window.addEventListener('resize', onResize);
  await store.loadTreeRoot();
  await store.openPath('');
  if (api.boot.isPro) await store.loadRecent();
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize);
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div style="display:flex;flex-direction:column;height:100%" @click="closeContext">
    <div style="display:contents">
      <Toolbar
        ref="toolbarRef"
        @new-folder="newFolder"
        @new-file="newFile"
        @open-settings="modals.settings = true"
        @open-trash="modals.trash = true"
        @open-logs="modals.logs = true"
        @save="store.activeTab && store.saveTab(store.activeTab)"
        @create-zip="createZip"
      />

      <div v-if="store.listing" class="mcfm-loading-bar"></div>

      <div v-if="isMobile" class="mcfm-mobile-tabs">
        <button class="mcfm-mobile-tab" :class="{ active: mobilePane === 'tree' }" @click="mobilePane = 'tree'">Explorer</button>
        <button class="mcfm-mobile-tab" :class="{ active: mobilePane === 'files' }" @click="mobilePane = 'files'">Files</button>
        <button
          v-if="showEditor"
          class="mcfm-mobile-tab"
          :class="{ active: mobilePane === 'editor' }"
          @click="mobilePane = 'editor'"
        >Editor</button>
      </div>

      <div class="mcfm-body" :class="{ 'mcfm-mobile': isMobile }">
        <TreePane
          :class="{ 'mcfm-pane-active': !isMobile || mobilePane === 'tree' }"
          @navigate="store.openPath($event)"
          @context="openContext"
        />

        <div class="mcfm-center" :class="{ 'mcfm-pane-active': !isMobile || mobilePane === 'files' }">
          <Breadcrumbs @navigate="store.openPath($event)" />
          <FileBrowser
            @activate="activate"
            @context="openContext"
            @rename="renameEntry"
            @properties="modals.properties = $event"
            @delete="deleteEntries"
          />
        </div>

        <template v-if="showEditor">
          <div v-if="!isMobile" class="mcfm-resizer" @mousedown="startResize"></div>
          <div
            class="mcfm-editor-pane"
            :class="{ 'mcfm-pane-active': !isMobile || mobilePane === 'editor' }"
            :style="isMobile ? {} : { width: editorWidth + '%' }"
          >
            <EditorPane />
          </div>
        </template>
      </div>

      <StatusBar />

      <ContextMenu
        v-if="contextMenu.visible"
        :x="contextMenu.x"
        :y="contextMenu.y"
        :entry="contextMenu.entry"
        :has-clipboard="!!store.clipboard"
        :selection-count="store.selection.length"
        @action="handleContextAction"
      />

      <Toasts />

      <PromptModal
        v-if="modals.prompt"
        v-bind="modals.prompt"
        @confirm="(val) => { modals.prompt.onConfirm(val); modals.prompt = null; }"
        @cancel="modals.prompt = null"
      />
      <ConfirmModal
        v-if="modals.confirm"
        v-bind="modals.confirm"
        @confirm="() => { modals.confirm.onConfirm(); modals.confirm = null; }"
        @cancel="modals.confirm = null"
      />
      <PropertiesModal
        v-if="modals.properties !== null"
        :path="modals.properties"
        @close="modals.properties = null"
      />
      <SettingsModal v-if="modals.settings" @close="modals.settings = false" />
      <TrashModal v-if="modals.trash" @close="modals.trash = false" />
      <LogsModal v-if="modals.logs" @close="modals.logs = false" />
    </div>
  </div>
</template>
