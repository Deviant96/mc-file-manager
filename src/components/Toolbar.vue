<script setup>
import { ref, computed } from 'vue';
import { useFileManager } from '../stores/fileManager';
import { api } from '../api/client';

const store = useFileManager();
const emit = defineEmits(['new-folder', 'new-file', 'open-settings', 'open-trash', 'open-logs', 'save', 'create-zip', 'extract-zip']);

const searchTerm = ref('');
const searchInput = ref(null);
const showRecent = ref(false);
const fileInput = ref(null);
let searchTimer = null;

const isPro = computed(() => !!api.boot.isPro);

const searchScopes = [
  { value: 'down', label: 'From here' },
  { value: 'folder', label: 'This folder only', pro: true },
  { value: 'site', label: 'Entire site', pro: true },
];

function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    if (searchTerm.value.trim()) {
      store.runSearch(searchTerm.value.trim(), store.search.scope);
    } else {
      store.clearSearch();
      store.openPath(store.currentPath);
    }
  }, 300);
}

function onScopeChange(e) {
  const scope = e.target.value;
  if ((scope === 'folder' || scope === 'site') && !isPro.value) {
    store.notify('Search scope options require MC File Manager Pro.', 'warning');
    return;
  }
  store.search.scope = scope;
  if (searchTerm.value.trim()) {
    store.runSearch(searchTerm.value.trim(), scope);
  }
}

function focusSearch() {
  searchInput.value?.focus();
  searchInput.value?.select();
}

function triggerUpload() {
  fileInput.value && fileInput.value.click();
}

async function onFilesPicked(e) {
  const files = Array.from(e.target.files || []);
  for (const file of files) {
    try {
      await api.upload(store.currentPath, file);
      store.notify(`Uploaded ${file.name}`, 'success', 2000);
    } catch (err) {
      store.notify(`${file.name}: ${err.message}`, 'error');
    }
  }
  e.target.value = '';
  store.refresh();
}

async function openRecent(item) {
  showRecent.value = false;
  const parent = item.path.includes('/') ? item.path.split('/').slice(0, -1).join('/') : '';
  await store.openPath(parent);
  const entry = store.entries.find((e) => e.path === item.path) || { path: item.path, name: item.name, isDir: false };
  await store.openFile(entry);
}

defineExpose({ focusSearch });
</script>

<template>
  <div class="mcfm-toolbar">
    <button class="mcfm-btn" @click="emit('new-file')" title="New file">
      <span class="dashicons dashicons-media-default"></span> New File
    </button>
    <button class="mcfm-btn" @click="emit('new-folder')" title="New folder">
      <span class="dashicons dashicons-portfolio"></span> New Folder
    </button>
    <button class="mcfm-btn" @click="triggerUpload" title="Upload">
      <span class="dashicons dashicons-upload"></span> Upload
    </button>
    <input ref="fileInput" type="file" multiple hidden @change="onFilesPicked" />
    <button class="mcfm-btn" @click="store.refresh()" title="Refresh (F5)">
      <span class="dashicons dashicons-update"></span>
    </button>

    <button
      v-if="store.selection.length"
      class="mcfm-btn"
      title="Create ZIP from selection"
      @click="emit('create-zip')"
    >
      <span class="dashicons dashicons-media-archive"></span> ZIP
    </button>

    <span class="spacer"></span>

    <div v-if="isPro && store.recentFiles.length" class="mcfm-recent-wrap" style="position:relative">
      <button class="mcfm-btn" title="Recently opened" @click="showRecent = !showRecent">
        <span class="dashicons dashicons-clock"></span>
      </button>
      <div v-if="showRecent" class="mcfm-context" style="position:absolute;top:100%;left:0;margin-top:4px;min-width:220px">
        <div class="mcfm-pane-title" style="padding:4px 10px">Recent</div>
        <div
          v-for="item in store.recentFiles"
          :key="item.path"
          class="mcfm-context-item"
          @click="openRecent(item)"
        >
          {{ item.name }}
        </div>
      </div>
    </div>

    <button
      class="mcfm-btn primary"
      :disabled="!store.activeTabData || !store.activeTabData.dirty"
      @click="emit('save')"
      title="Save (Ctrl+S)"
    >
      <span class="dashicons dashicons-saved"></span> Save
    </button>

    <div class="mcfm-search">
      <span class="dashicons dashicons-search"></span>
      <input
        ref="searchInput"
        v-model="searchTerm"
        type="text"
        placeholder="Search filenames… (Ctrl+F)"
        @input="onSearchInput"
      />
      <select
        class="mcfm-select"
        style="width:auto;border:none;background:transparent;font-size:11px;padding:0 4px"
        :value="store.search.scope"
        title="Search scope"
        @change="onScopeChange"
      >
        <option v-for="opt in searchScopes" :key="opt.value" :value="opt.value">
          {{ opt.label }}{{ opt.pro && !isPro ? ' (Pro)' : '' }}
        </option>
      </select>
    </div>

    <button class="mcfm-btn" @click="emit('open-trash')" title="Trash">
      <span class="dashicons dashicons-trash"></span>
    </button>
    <button class="mcfm-btn" @click="emit('open-logs')" title="Activity log">
      <span class="dashicons dashicons-list-view"></span>
    </button>
    <button class="mcfm-btn" @click="emit('open-settings')" title="Settings">
      <span class="dashicons dashicons-admin-generic"></span>
    </button>
  </div>
</template>
