<script setup>
import { useFileManager } from '../stores/fileManager';
import TreeNode from './TreeNode.vue';

const store = useFileManager();
defineEmits(['navigate']);
</script>

<template>
  <div class="mcfm-tree">
    <div class="mcfm-pane-title">Explorer</div>
    <TreeNode :node="store.treeRoot" :depth="0" @navigate="$emit('navigate', $event)" />
  </div>
</template>
