<script setup>
import { ref, onMounted } from 'vue';
import { api } from '../../api/client';
import { useFileManager } from '../../stores/fileManager';
import { formatBytes, formatDate } from '../../utils/format';

const emit = defineEmits(['close']);
const store = useFileManager();
const items = ref([]);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    const data = await api.trash();
    items.value = data.items;
  } catch (e) {
    store.notify(e.message, 'error');
  } finally {
    loading.value = false;
  }
}

async function restore(id) {
  try {
    await api.restore(id);
    store.notify('Restored', 'success', 2000);
    await load();
    store.refresh();
  } catch (e) {
    store.notify(e.message, 'error');
  }
}

async function purge(id) {
  try {
    await api.purge(id);
    store.notify('Permanently deleted', 'success', 2000);
    await load();
  } catch (e) {
    store.notify(e.message, 'error');
  }
}

onMounted(load);
</script>

<template>
  <div class="mcfm-overlay" @click.self="emit('close')">
    <div class="mcfm-modal" style="min-width:640px">
      <div class="mcfm-modal-head">
        Trash
        <span class="dashicons dashicons-no-alt" style="cursor:pointer" @click="emit('close')"></span>
      </div>
      <div class="mcfm-modal-body" style="max-height:60vh">
        <div v-if="loading" class="mcfm-spinner"></div>
        <div v-else-if="!items.length" class="mcfm-empty" style="height:120px">Trash is empty</div>
        <table v-else class="mcfm-list">
          <thead>
            <tr><th>Original path</th><th>Type</th><th>Size</th><th>Deleted</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>/{{ item.originalPath }}</td>
              <td>{{ item.type === 'dir' ? 'Folder' : 'File' }}</td>
              <td>{{ item.type === 'dir' ? '—' : formatBytes(item.size) }}</td>
              <td>{{ formatDate(item.deletedAt) }}</td>
              <td style="white-space:nowrap">
                <button class="mcfm-btn" @click="restore(item.id)">Restore</button>
                <button class="mcfm-btn danger" @click="purge(item.id)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mcfm-modal-foot">
        <button class="mcfm-btn primary" @click="emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>
