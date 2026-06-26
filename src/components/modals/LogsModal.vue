<script setup>
import { ref, onMounted } from 'vue';
import { api } from '../../api/client';
import { useFileManager } from '../../stores/fileManager';
import { formatDate } from '../../utils/format';

const emit = defineEmits(['close']);
const store = useFileManager();
const items = ref([]);
const total = ref(0);
const page = ref(1);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    const data = await api.logs(page.value, 50);
    items.value = data.items;
    total.value = data.total;
  } catch (e) {
    store.notify(e.message, 'error');
  } finally {
    loading.value = false;
  }
}

function next() {
  if (page.value * 50 < total.value) {
    page.value += 1;
    load();
  }
}
function prev() {
  if (page.value > 1) {
    page.value -= 1;
    load();
  }
}

onMounted(load);
</script>

<template>
  <div class="mcfm-overlay" @click.self="emit('close')">
    <div class="mcfm-modal" style="min-width:720px">
      <div class="mcfm-modal-head">
        Activity Log
        <span class="dashicons dashicons-no-alt" style="cursor:pointer" @click="emit('close')"></span>
      </div>
      <div class="mcfm-modal-body" style="max-height:60vh">
        <div v-if="loading" class="mcfm-spinner"></div>
        <table v-else class="mcfm-list">
          <thead>
            <tr><th>When</th><th>User</th><th>Action</th><th>Status</th><th>Path</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in items" :key="row.id">
              <td style="white-space:nowrap">{{ formatDate(row.created_at) }}</td>
              <td style="white-space:nowrap">{{ row.display_name || row.username || '—' }}</td>
              <td>{{ row.action }}</td>
              <td><span class="badge" :class="row.status">{{ row.status }}</span></td>
              <td>{{ row.source_path ? row.source_path + ' → ' : '' }}{{ row.target_path }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mcfm-modal-foot" style="justify-content:space-between">
        <span style="font-size:12px;color:var(--mcfm-text-dim)">{{ total }} events</span>
        <div style="display:flex;gap:8px">
          <button class="mcfm-btn" :disabled="page === 1" @click="prev">Previous</button>
          <button class="mcfm-btn" :disabled="page * 50 >= total" @click="next">Next</button>
          <button class="mcfm-btn primary" @click="emit('close')">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>
