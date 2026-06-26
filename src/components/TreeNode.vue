<script setup>
import { computed } from 'vue';
import { useFileManager } from '../stores/fileManager';

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
});
const emit = defineEmits(['navigate']);
const store = useFileManager();

const isActive = computed(() => store.currentPath === props.node.path && !store.search.active);
const canExpand = computed(() => props.node.path === '' || props.node.hasChildren || props.node.children.length > 0);

async function toggle() {
  if (!props.node.expanded && !props.node.loaded) {
    await store.loadTreeChildren(props.node);
  }
  props.node.expanded = !props.node.expanded;
}

async function activate() {
  emit('navigate', props.node.path);
  if (!props.node.expanded && canExpand.value) {
    await toggle();
  }
}
</script>

<template>
  <div class="mcfm-tree-node">
    <div
      class="mcfm-tree-row"
      :class="{ active: isActive }"
      :style="{ paddingLeft: 8 + depth * 12 + 'px' }"
      @click="activate"
    >
      <span class="mcfm-tree-twisty" @click.stop="toggle">
        <span v-if="canExpand" class="dashicons" :class="node.expanded ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2'"></span>
      </span>
      <span class="dashicons mcfm-icon-folder" :class="node.expanded ? 'dashicons-open-folder' : 'dashicons-portfolio'"></span>
      <span class="mcfm-name-text">{{ node.name }}</span>
    </div>
    <div v-if="node.expanded" class="mcfm-tree-children">
      <TreeNode
        v-for="child in node.children"
        :key="child.path"
        :node="child"
        :depth="depth + 1"
        @navigate="$emit('navigate', $event)"
      />
    </div>
  </div>
</template>
