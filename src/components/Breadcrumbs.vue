<script setup>
import { useFileManager } from '../stores/fileManager';

const store = useFileManager();
defineEmits(['navigate']);
</script>

<template>
  <div class="mcfm-breadcrumbs">
    <template v-for="(crumb, i) in store.breadcrumbs" :key="crumb.path">
      <span class="mcfm-crumb" @click="$emit('navigate', crumb.path)">{{ crumb.name }}</span>
      <span v-if="i < store.breadcrumbs.length - 1" class="mcfm-crumb-sep dashicons dashicons-arrow-right-alt2"></span>
    </template>
    <span v-if="store.search.active" class="mcfm-crumb-sep">— search results for "{{ store.search.query }}"</span>
  </div>
</template>
