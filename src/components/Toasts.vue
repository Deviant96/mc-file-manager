<script setup>
import { TransitionGroup } from 'vue';
import { useFileManager } from '../stores/fileManager';
const store = useFileManager();
</script>

<template>
  <div class="mcfm-toasts">
    <TransitionGroup name="mcfm-toast">
      <div
        v-for="n in store.notifications"
        :key="n.id"
        class="mcfm-toast"
        :class="n.type"
        @click="store.dismiss(n.id)"
      >
        {{ n.message }}
      </div>
    </TransitionGroup>
  </div>
</template>
