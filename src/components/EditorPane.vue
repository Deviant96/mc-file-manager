<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { useFileManager } from '../stores/fileManager';
import { api } from '../api/client';
import { formatBytes } from '../utils/format';

const store = useFileManager();
const editorHost = ref(null);

let monaco = null;
let editor = null;
const models = new Map();
let suppressChange = false;
let peerTimer = null;
let heartbeatTimer = null;

const active = computed(() => store.activeTabData);
const showTextEditor = computed(() => active.value && active.value.kind === 'text' && !active.value.tooLarge);
const showImage = computed(() => active.value && active.value.kind === 'image');

const concurrentPeers = computed(() => {
  if (!store.activeTab) return [];
  return store.peersForPath(store.activeTab).filter((p) => p.id !== api.boot.user?.id);
});

const editorTheme = computed(() => (store.settings.theme === 'wordpress' ? 'vs' : 'vs-dark'));

function imageUrl(path) {
  return api.rawFileUrl(path);
}

async function ensureEditor() {
  if (editor || !showTextEditor.value) return;
  const mod = await import('../editor/monaco');
  monaco = mod.default;
  await nextTick();
  if (!editorHost.value) return;
  editor = monaco.editor.create(editorHost.value, {
    value: '',
    theme: editorTheme.value,
    automaticLayout: true,
    fontSize: 13,
    minimap: { enabled: true },
    scrollBeyondLastLine: false,
    tabSize: 2,
  });
  editor.onDidChangeModelContent(() => {
    if (suppressChange || !store.activeTab) return;
    store.setTabContent(store.activeTab, editor.getValue());
  });
  editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
    if (store.activeTab) store.saveTab(store.activeTab);
  });
  syncModel();
}

function getModel(tab) {
  if (models.has(tab.path)) {
    const m = models.get(tab.path);
    if (m.getValue() !== tab.content) {
      suppressChange = true;
      m.setValue(tab.content);
      suppressChange = false;
    }
    return m;
  }
  const uri = monaco.Uri.parse('mcfm://' + tab.path);
  const model = monaco.editor.createModel(tab.content, tab.language, uri);
  models.set(tab.path, model);
  return model;
}

function syncModel() {
  if (!editor || !monaco || !active.value || !showTextEditor.value) return;
  const model = getModel(active.value);
  editor.setModel(model);
}

watch(
  () => store.activeTab,
  async (path) => {
    if (showTextEditor.value) {
      await ensureEditor();
      syncModel();
    }
    if (path) {
      store.registerEditorOpen(path);
      startPeerPolling(path);
    }
  }
);

function startPeerPolling(path) {
  clearInterval(peerTimer);
  clearInterval(heartbeatTimer);
  store.refreshEditorPeers(path);
  peerTimer = setInterval(() => store.refreshEditorPeers(path), 15000);
  heartbeatTimer = setInterval(() => api.editorHeartbeat(path), 30000);
}

watch(editorTheme, (t) => {
  if (monaco) monaco.editor.setTheme(t);
});

// Clean up models for closed tabs.
watch(
  () => store.tabs.map((t) => t.path).join('|'),
  () => {
    const open = new Set(store.tabs.map((t) => t.path));
    for (const [path, model] of models.entries()) {
      if (!open.has(path)) {
        model.dispose();
        models.delete(path);
      }
    }
  }
);

onMounted(() => {
  if (showTextEditor.value) ensureEditor();
});

onBeforeUnmount(() => {
  clearInterval(peerTimer);
  clearInterval(heartbeatTimer);
  for (const model of models.values()) model.dispose();
  models.clear();
  if (editor) editor.dispose();
});
</script>

<template>
  <div style="display:flex;flex-direction:column;height:100%">
    <div class="mcfm-tabs">
      <div
        v-for="tab in store.tabs"
        :key="tab.path"
        class="mcfm-tab"
        :class="{ active: tab.path === store.activeTab }"
        @click="store.activeTab = tab.path"
      >
        <span class="dashicons dashicons-media-text" style="font-size:14px;width:14px;height:14px"></span>
        <span>{{ tab.name }}</span>
        <span v-if="tab.dirty" class="dot">●</span>
        <span class="close dashicons dashicons-no-alt" style="font-size:14px;width:14px;height:14px" @click.stop="store.closeTab(tab.path)"></span>
      </div>
    </div>

    <div v-if="concurrentPeers.length" class="mcfm-editor-warning">
      <span class="dashicons dashicons-warning"></span>
      Also open by: {{ concurrentPeers.map((p) => p.name).join(', ') }}
    </div>

    <div v-if="showTextEditor" class="mcfm-editor-host">
      <div ref="editorHost" class="mcfm-monaco"></div>
    </div>

    <div v-else-if="showImage" class="mcfm-preview">
      <img :src="imageUrl(active.path)" :alt="active.name" />
      <div>{{ active.name }} — {{ formatBytes(active.entry.size) }}</div>
    </div>

    <div v-else-if="active && active.tooLarge" class="mcfm-preview">
      <span class="dashicons dashicons-warning" style="font-size:32px;width:32px;height:32px"></span>
      <div>This file is too large to edit safely ({{ formatBytes(active.entry.size) }}).</div>
      <div style="font-size:12px">Maximum editable size is {{ formatBytes(active.maxBytes) }}.</div>
      <a class="mcfm-btn primary" :href="api.downloadUrl(active.path)" target="_blank">Download instead</a>
    </div>

    <div v-else-if="active" class="mcfm-preview">
      <span class="dashicons dashicons-media-default" style="font-size:32px;width:32px;height:32px"></span>
      <div>{{ active.name }}</div>
      <div style="font-size:12px">No inline preview for this file type.</div>
      <a class="mcfm-btn primary" :href="api.downloadUrl(active.path)" target="_blank">Download</a>
    </div>

    <div v-else class="mcfm-editor-empty">
      <span class="dashicons dashicons-editor-code" style="font-size:32px;width:32px;height:32px"></span>
      <div>Open a file to start editing</div>
    </div>
  </div>
</template>
