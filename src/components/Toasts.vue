<script setup>
import { useFileManager } from '../stores/fileManager';
const store = useFileManager();
</script>

<template>
  <div class="mcfm-toasts">
    <div
      v-for="n in store.notifications"
      :key="n.id"
      class="mcfm-toast"
      :class="n.type"
      @click="store.dismiss(n.id)"
    >
      {{ n.message }}
    </div>
  </div>
</template>
