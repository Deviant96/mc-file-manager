<script setup>
import { ref } from 'vue';
import { useFileManager } from '../stores/fileManager';
import { api } from '../api/client';

const store = useFileManager();
const emit = defineEmits(['new-folder', 'new-file', 'open-settings', 'open-trash', 'open-logs', 'save']);

const searchTerm = ref('');
const fileInput = ref(null);
let searchTimer = null;

function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    if (searchTerm.value.trim()) {
      store.runSearch(searchTerm.value.trim());
    } else {
      store.clearSearch();
      store.openPath(store.currentPath);
    }
  }, 300);
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
    <button class="mcfm-btn" @click="store.refresh()" title="Refresh">
      <span class="dashicons dashicons-update"></span>
    </button>

    <span class="spacer"></span>

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
        v-model="searchTerm"
        type="text"
        placeholder="Search filenames…"
        @input="onSearchInput"
      />
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
