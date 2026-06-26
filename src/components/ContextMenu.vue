<script setup>
import { computed } from 'vue';

const props = defineProps({
  x: Number,
  y: Number,
  entry: Object,
  hasClipboard: Boolean,
});
const emit = defineEmits(['action']);

const style = computed(() => ({
  left: Math.min(props.x, window.innerWidth - 200) + 'px',
  top: Math.min(props.y, window.innerHeight - 320) + 'px',
}));

const hasEntry = computed(() => !!props.entry);
const isFile = computed(() => props.entry && !props.entry.isDir);
const isZip = computed(() => props.entry && ['zip'].includes((props.entry.ext || '').toLowerCase()));
</script>

<template>
  <div class="mcfm-context" :style="style" @click.stop>
    <div v-if="hasEntry" class="mcfm-context-item" @click="emit('action', 'open')">
      <span class="dashicons dashicons-external"></span> Open
    </div>
    <div v-if="isFile" class="mcfm-context-item" @click="emit('action', 'download')">
      <span class="dashicons dashicons-download"></span> Download
    </div>
    <div v-if="isZip" class="mcfm-context-item" @click="emit('action', 'extract')">
      <span class="dashicons dashicons-media-archive"></span> Extract ZIP
    </div>
    <div v-if="hasEntry" class="mcfm-context-sep"></div>
    <div class="mcfm-context-item" :class="{ disabled: !hasEntry }" @click="emit('action', 'cut')">
      <span class="dashicons dashicons-editor-cut"></span> Cut
    </div>
    <div class="mcfm-context-item" :class="{ disabled: !hasEntry }" @click="emit('action', 'copy')">
      <span class="dashicons dashicons-admin-page"></span> Copy
    </div>
    <div class="mcfm-context-item" :class="{ disabled: !hasClipboard }" @click="emit('action', 'paste')">
      <span class="dashicons dashicons-clipboard"></span> Paste
    </div>
    <div v-if="hasEntry" class="mcfm-context-sep"></div>
    <div v-if="hasEntry" class="mcfm-context-item" @click="emit('action', 'rename')">
      <span class="dashicons dashicons-edit"></span> Rename
    </div>
    <div v-if="hasEntry" class="mcfm-context-item" @click="emit('action', 'delete')">
      <span class="dashicons dashicons-trash"></span> Delete
    </div>
    <div v-if="hasEntry" class="mcfm-context-sep"></div>
    <div v-if="hasEntry" class="mcfm-context-item" @click="emit('action', 'properties')">
      <span class="dashicons dashicons-info"></span> Properties
    </div>
  </div>
</template>
