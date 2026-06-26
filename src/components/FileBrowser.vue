<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { useFileManager } from '../stores/fileManager';
import { api } from '../api/client';
import { formatBytes, formatDate, iconFor } from '../utils/format';
import SkeletonTable from './SkeletonTable.vue';

const store = useFileManager();
const emit = defineEmits(['activate', 'context', 'rename', 'properties', 'delete']);

const dragOver = ref(false);
const browserRef = ref(null);
const rubberBand = ref(null);

let dragStart = null;
let bandActive = false;
let suppressRowClick = false;

const rows = computed(() => (store.search.active ? store.search.results : store.sortedEntries));

function rectsIntersect(a, b) {
  return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
}

function onBrowserMouseDown(e) {
  if (e.button !== 0) return;
  if (e.target.closest('th')) return;
  dragStart = { x: e.clientX, y: e.clientY };
  bandActive = false;
  rubberBand.value = null;
  window.addEventListener('mousemove', onBrowserMouseMove);
  window.addEventListener('mouseup', onBrowserMouseUp);
}

function onBrowserMouseMove(e) {
  if (!dragStart) return;
  const dx = Math.abs(e.clientX - dragStart.x);
  const dy = Math.abs(e.clientY - dragStart.y);
  if (!bandActive && (dx > 4 || dy > 4)) {
    bandActive = true;
    if (!e.ctrlKey && !e.metaKey && !e.shiftKey) {
      store.clearSelection();
    }
  }
  if (!bandActive) return;

  const browser = browserRef.value;
  if (!browser) return;

  const browserRect = browser.getBoundingClientRect();
  rubberBand.value = {
    left: Math.min(dragStart.x, e.clientX) - browserRect.left + browser.scrollLeft,
    top: Math.min(dragStart.y, e.clientY) - browserRect.top + browser.scrollTop,
    width: Math.abs(e.clientX - dragStart.x),
    height: Math.abs(e.clientY - dragStart.y),
  };

  const bandRect = {
    left: Math.min(dragStart.x, e.clientX),
    top: Math.min(dragStart.y, e.clientY),
    right: Math.max(dragStart.x, e.clientX),
    bottom: Math.max(dragStart.y, e.clientY),
  };

  const selected = [];
  browser.querySelectorAll('.mcfm-row').forEach((row) => {
    const rowRect = row.getBoundingClientRect();
    if (rectsIntersect(bandRect, rowRect)) {
      selected.push(row.dataset.path);
    }
  });
  store.setSelection(selected);
}

function onBrowserMouseUp() {
  if (bandActive) suppressRowClick = true;
  dragStart = null;
  bandActive = false;
  rubberBand.value = null;
  window.removeEventListener('mousemove', onBrowserMouseMove);
  window.removeEventListener('mouseup', onBrowserMouseUp);
}

onBeforeUnmount(() => {
  window.removeEventListener('mousemove', onBrowserMouseMove);
  window.removeEventListener('mouseup', onBrowserMouseUp);
});

function onRowClick(entry, index, e) {
  if (suppressRowClick) {
    suppressRowClick = false;
    return;
  }
  store.select(entry, index, { ctrl: e.ctrlKey || e.metaKey, shift: e.shiftKey });
}

function onRowContext(entry, index, e) {
  e.preventDefault();
  e.stopPropagation();
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
  if (bandActive) {
    e.preventDefault();
    return;
  }
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
    ref="browserRef"
    class="mcfm-browser"
    :class="{ dragover: dragOver, selecting: bandActive }"
    @mousedown="onBrowserMouseDown"
    @dragover.prevent="dragOver = true"
    @dragleave="dragOver = false"
    @drop="onDrop"
    @contextmenu="onEmptyContext"
  >
    <div
      v-if="rubberBand"
      class="mcfm-select-band"
      :style="{
        left: rubberBand.left + 'px',
        top: rubberBand.top + 'px',
        width: rubberBand.width + 'px',
        height: rubberBand.height + 'px',
      }"
    ></div>

    <div v-if="store.listing" class="mcfm-browser-list">
      <SkeletonTable />
    </div>

    <div v-else-if="store.listError" class="mcfm-error-state">
      <span class="dashicons dashicons-warning" style="font-size:32px;width:32px;height:32px"></span>
      <div>Could not load folder</div>
      <div class="msg">{{ store.listError }}</div>
      <button class="mcfm-btn primary" @click="store.openPath(store.currentPath)">Retry</button>
    </div>

    <div v-else-if="rows.length" class="mcfm-browser-list">
      <table class="mcfm-table">
      <thead>
        <tr>
          <th @click="setSort('name')">Name {{ sortIndicator('name') }}</th>
          <th class="mcfm-col-size" @click="setSort('size')">Size {{ sortIndicator('size') }}</th>
          <th @click="setSort('name')">Type</th>
          <th class="mcfm-col-snapshot">Snapshot</th>
          <th class="mcfm-col-snapshot">Last Snapshot</th>
          <th class="mcfm-col-modified" @click="setSort('modified')">Modified {{ sortIndicator('modified') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(entry, index) in rows"
          :key="entry.path"
          class="mcfm-row"
          :data-path="entry.path"
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
          <td class="mcfm-col-snapshot">{{ entry.isDir ? '—' : (entry.hasSnapshot ? 'Yes' : 'No') }}</td>
          <td class="mcfm-col-snapshot">{{ entry.isDir ? '—' : (entry.hasSnapshot && entry.lastSnapshotAt ? formatDate(entry.lastSnapshotAt) : '—') }}</td>
          <td class="mcfm-col-modified">{{ formatDate(entry.mtime) }}</td>
        </tr>
      </tbody>
      </table>
    </div>

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
