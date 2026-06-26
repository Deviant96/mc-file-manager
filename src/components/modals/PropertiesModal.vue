<script setup>
import { ref, onMounted } from 'vue';
import { api } from '../../api/client';
import { useFileManager } from '../../stores/fileManager';
import { formatBytes, formatDate } from '../../utils/format';

const props = defineProps({ path: String });
const emit = defineEmits(['close']);
const store = useFileManager();

const data = ref(null);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    data.value = await api.properties(props.path);
  } catch (e) {
    store.notify(e.message, 'error');
    emit('close');
  } finally {
    loading.value = false;
  }
}

onMounted(load);

async function rollback(id) {
  try {
    await api.restoreSnapshot(id);
    store.notify('Rolled back to snapshot', 'success', 2500);
    const tab = store.tabs.find((t) => t.path === props.path);
    if (tab) {
      const fresh = await api.readFile(props.path);
      tab.content = fresh.content || '';
      tab.original = tab.content;
      tab.dirty = false;
    }
    emit('close');
  } catch (e) {
    store.notify(e.message, 'error');
  }
}

async function removeSnapshot(id) {
  if (!window.confirm('Delete this snapshot permanently?')) return;
  try {
    await api.deleteSnapshot(id);
    store.notify('Snapshot deleted', 'success', 2000);
    await load();
    await store.refresh();
  } catch (e) {
    store.notify(e.message, 'error');
  }
}
</script>

<template>
  <div class="mcfm-overlay" @click.self="emit('close')">
    <div class="mcfm-modal" style="min-width:480px">
      <div class="mcfm-modal-head">
        Properties
        <span class="dashicons dashicons-no-alt" style="cursor:pointer" @click="emit('close')"></span>
      </div>
      <div class="mcfm-modal-body">
        <div v-if="loading" class="mcfm-spinner"></div>
        <template v-else-if="data">
          <div class="mcfm-prop-grid">
            <span class="k">Name</span><span class="v">{{ data.entry.name }}</span>
            <span class="k">Path</span><span class="v">/{{ data.entry.path }}</span>
            <span class="k">Type</span><span class="v">{{ data.entry.isDir ? 'Folder' : (data.entry.ext || 'file').toUpperCase() }}</span>
            <span class="k">Size</span><span class="v">{{ data.entry.isDir ? '—' : formatBytes(data.entry.size) }}</span>
            <span class="k">Modified</span><span class="v">{{ formatDate(data.entry.mtime) }}</span>
            <span class="k">Permissions</span><span class="v">{{ data.entry.permissions }}</span>
            <span class="k">Readable</span><span class="v">{{ data.entry.readable ? 'Yes' : 'No' }}</span>
            <span class="k">Writable</span><span class="v">{{ data.entry.writable ? 'Yes' : 'No' }}</span>
            <span class="k">Preview</span><span class="v">{{ data.preview }}</span>
            <span class="k">Snapshots</span><span class="v">{{ data.snapshots.length }}</span>
            <template v-if="data.hashes && data.hashes.md5">
              <span class="k">MD5</span><span class="v" style="font-family:monospace;font-size:11px">{{ data.hashes.md5 }}</span>
              <span class="k">SHA256</span><span class="v" style="font-family:monospace;font-size:11px">{{ data.hashes.sha256 }}</span>
            </template>
          </div>

          <template v-if="data.snapshots.length">
            <h4 style="margin:16px 0 8px">Revision snapshots</h4>
            <table class="mcfm-list">
              <thead>
                <tr><th>Version</th><th>Size</th><th>Created</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="s in data.snapshots" :key="s.id">
                  <td>v{{ s.version }}</td>
                  <td>{{ formatBytes(s.size) }}</td>
                  <td>{{ formatDate(s.createdAt) }}</td>
                  <td style="display:flex;gap:6px;justify-content:flex-end">
                    <button class="mcfm-btn" @click="rollback(s.id)">Roll back</button>
                    <button class="mcfm-btn danger" @click="removeSnapshot(s.id)">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </template>
        </template>
      </div>
      <div class="mcfm-modal-foot">
        <button class="mcfm-btn primary" @click="emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>
