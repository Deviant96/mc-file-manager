<script setup>
import { useFileManager } from '../stores/fileManager';
import TreeNode from './TreeNode.vue';
import SkeletonTree from './SkeletonTree.vue';

const store = useFileManager();
defineEmits(['navigate']);
</script>

<template>
  <div class="mcfm-tree">
    <div class="mcfm-pane-title">Explorer</div>
    <SkeletonTree v-if="store.treeLoading" />
    <div v-else-if="store.treeError" class="mcfm-error-state" style="height:auto;padding:24px 12px">
      <span class="dashicons dashicons-warning"></span>
      <div class="msg">{{ store.treeError }}</div>
      <button class="mcfm-btn" @click="store.loadTreeRoot()">Retry</button>
    </div>
    <TreeNode v-else :node="store.treeRoot" :depth="0" @navigate="$emit('navigate', $event)" />
  </div>
</template>
