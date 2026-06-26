<script setup>
import { ref, computed } from 'vue';
import { useFileManager } from '../stores/fileManager';
import { api } from '../api/client';
import { formatBytes, formatDate, iconFor } from '../utils/format';

const store = useFileManager();
const emit = defineEmits(['activate', 'context', 'rename', 'properties', 'delete']);

const dragOver = ref(false);

const rows = computed(() => (store.search.active ? store.search.results : store.sortedEntries));

function onRowClick(entry, index, e) {
  store.select(entry, index, { ctrl: e.ctrlKey || e.metaKey, shift: e.shiftKey });
}

function onRowContext(entry, index, e) {
  e.preventDefault();
  if (!store.isSelected(entry.path)) {
    store.select(entry, index, {});
  }
  emit('context', { x: e.clientX, y: e.clientY, entry });
}

function onEmptyContext(e) {
  e.preventDefault();
  store.clearSelection();
  emit('context', { x: e.clientX, y: e.clientY, entry: null });
}

function setSort(key) {
  if (store.sort.key === key) {
    store.sort.dir = store.sort.dir === 'asc' ? 'desc' : 'asc';
  } else {
    store.sort.key = key;
    store.sort.dir = 'asc';
  }
}

function sortIndicator(key) {
  if (store.sort.key !== key) return '';
  return store.sort.dir === 'asc' ? '▲' : '▼';
}

// Drag & drop: desktop files in, and internal move ---------------------
async function onDrop(e) {
  e.preventDefault();
  dragOver.value = false;
  const internal = e.dataTransfer.getData('text/mcfm-path');
  if (internal) {
    // Internal move handled by row drop target; ignore here for the pane root.
    if (store.currentPath !== internal.split('/').slice(0, -1).join('/')) {
      try {
        await api.move(internal, store.currentPath);
        store.refresh();
      } catch (err) {
        store.notify(err.message, 'error');
      }
    }
    return;
  }
  const files = Array.from(e.dataTransfer.files || []);
  for (const file of files) {
    try {
      await api.upload(store.currentPath, file);
      store.notify(`Uploaded ${file.name}`, 'success', 2000);
    } catch (err) {
      store.notify(`${file.name}: ${err.message}`, 'error');
    }
  }
  store.refresh();
}

function onDragStart(entry, e) {
  e.dataTransfer.setData('text/mcfm-path', entry.path);
  e.dataTransfer.effectAllowed = 'move';
}

async function onRowDrop(targetEntry, e) {
  e.preventDefault();
  e.stopPropagation();
  if (!targetEntry.isDir) return;
  const src = e.dataTransfer.getData('text/mcfm-path');
  if (!src || src === targetEntry.path) return;
  try {
    await api.move(src, targetEntry.path);
    store.refresh();
    store.notify('Moved', 'success', 2000);
  } catch (err) {
    store.notify(err.message, 'error');
  }
}
</script>

<template>
  <div
    class="mcfm-browser"
    :class="{ dragover: dragOver }"
    @dragover.prevent="dragOver = true"
    @dragleave="dragOver = false"
    @drop="onDrop"
    @contextmenu="onEmptyContext"
  >
    <table v-if="rows.length" class="mcfm-table">
      <thead>
        <tr>
          <th @click="setSort('name')">Name {{ sortIndicator('name') }}</th>
          <th class="mcfm-col-size" @click="setSort('size')">Size {{ sortIndicator('size') }}</th>
          <th @click="setSort('name')">Type</th>
          <th class="mcfm-col-modified" @click="setSort('modified')">Modified {{ sortIndicator('modified') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(entry, index) in rows"
          :key="entry.path"
          class="mcfm-row"
          :class="{
            selected: store.isSelected(entry.path),
            cut: store.clipboard && store.clipboard.mode === 'cut' && store.clipboard.paths.includes(entry.path),
          }"
          draggable="true"
          @click="onRowClick(entry, index, $event)"
          @dblclick="emit('activate', entry)"
          @contextmenu="onRowContext(entry, index, $event)"
          @dragstart="onDragStart(entry, $event)"
          @dragover.prevent
          @drop="onRowDrop(entry, $event)"
        >
          <td>
            <div class="mcfm-name-cell">
              <span class="dashicons" :class="[iconFor(entry), entry.isDir ? 'mcfm-icon-folder' : 'mcfm-icon-file']"></span>
              <span class="mcfm-name-text">{{ entry.name }}</span>
            </div>
          </td>
          <td class="mcfm-col-size">{{ entry.isDir ? '—' : formatBytes(entry.size) }}</td>
          <td>{{ entry.isDir ? 'Folder' : (entry.ext ? entry.ext.toUpperCase() : 'File') }}</td>
          <td class="mcfm-col-modified">{{ formatDate(entry.mtime) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-else-if="store.search.active && store.search.running" class="mcfm-empty">
      <div class="mcfm-spinner"></div>
      <div>Searching…</div>
    </div>
    <div v-else-if="store.search.active" class="mcfm-empty">
      <span class="dashicons dashicons-search" style="font-size:32px;width:32px;height:32px"></span>
      <div>No files match "{{ store.search.query }}"</div>
    </div>
    <div v-else class="mcfm-empty">
      <span class="dashicons dashicons-portfolio" style="font-size:32px;width:32px;height:32px"></span>
      <div>This folder is empty</div>
      <div style="font-size:12px">Drag files here to upload</div>
    </div>
  </div>
</template>
