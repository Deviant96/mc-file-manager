<script setup>
import { computed } from 'vue';
import { useFileManager } from '../stores/fileManager';
import { formatBytes } from '../utils/format';

const store = useFileManager();

const selectionInfo = computed(() => {
  if (!store.selection.length) return '';
  const entries = store.entries.filter((e) => store.selection.includes(e.path));
  const bytes = entries.reduce((acc, e) => acc + (e.isDir ? 0 : e.size), 0);
  return `${store.selection.length} selected · ${formatBytes(bytes)}`;
});
</script>

<template>
  <div class="mcfm-status">
    <span><span class="dashicons dashicons-location" style="font-size:13px;width:13px;height:13px"></span> /{{ store.currentPath }}</span>
    <span>{{ store.entries.length }} items</span>
    <span v-if="selectionInfo">{{ selectionInfo }}</span>
    <span style="margin-left:auto" v-if="store.dirtyCount">{{ store.dirtyCount }} unsaved</span>
    <span>MC File Manager v{{ store.version }}</span>
  </div>
</template>
