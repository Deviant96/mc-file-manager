<script setup>
import { reactive, computed } from 'vue';
import { useFileManager } from '../../stores/fileManager';
import { api } from '../../api/client';

const store = useFileManager();
const emit = defineEmits(['close']);

const isPro = computed(() => !!api.boot.isPro);

const form = reactive({
  warn_before_edit: !!store.settings.warn_before_edit,
  max_editable_mb: Math.round((store.settings.max_editable_bytes || 104857600) / (1024 * 1024)),
  snapshot_retention: store.settings.snapshot_retention ?? 5,
  trash_enabled: !!store.settings.trash_enabled,
  theme: store.settings.theme || 'vscode',
  uninstall_drop_data: !!store.settings.uninstall_drop_data,
  uninstall_drop_files: !!store.settings.uninstall_drop_files,
  role_folder_rules_json: JSON.stringify(store.settings.role_folder_rules || [], null, 2),
});

async function save() {
  const payload = {
    warn_before_edit: form.warn_before_edit,
    max_editable_bytes: Math.max(1, form.max_editable_mb) * 1024 * 1024,
    snapshot_retention: form.snapshot_retention,
    trash_enabled: form.trash_enabled,
    theme: form.theme,
    uninstall_drop_data: form.uninstall_drop_data,
    uninstall_drop_files: form.uninstall_drop_files,
  };
  if (isPro.value) {
    try {
      payload.role_folder_rules = JSON.parse(form.role_folder_rules_json || '[]');
    } catch (e) {
      store.notify('Invalid role folder rules JSON', 'error');
      return;
    }
  }
  await store.saveSettings(payload);
  emit('close');
}
</script>

<template>
  <div class="mcfm-overlay" @click.self="emit('close')">
    <div class="mcfm-modal" style="min-width:480px">
      <div class="mcfm-modal-head">
        Settings
        <span class="dashicons dashicons-no-alt" style="cursor:pointer" @click="emit('close')"></span>
      </div>
      <div class="mcfm-modal-body">
        <div class="mcfm-check">
          <input id="warn" v-model="form.warn_before_edit" type="checkbox" />
          <label for="warn">Warn before opening a file for editing</label>
        </div>
        <div class="mcfm-check">
          <input id="trash" v-model="form.trash_enabled" type="checkbox" />
          <label for="trash">Move deleted items to Trash (hybrid delete)</label>
        </div>
        <div class="mcfm-field">
          <label>Maximum editable file size (MB)</label>
          <input v-model.number="form.max_editable_mb" class="mcfm-input" type="number" min="1" />
        </div>
        <div class="mcfm-field">
          <label>Snapshot retention (versions to keep)</label>
          <input v-model.number="form.snapshot_retention" class="mcfm-input" type="number" min="0" max="100" />
        </div>
        <div class="mcfm-field">
          <label>UI theme</label>
          <select v-model="form.theme" class="mcfm-select">
            <option value="vscode">VS Code (dark)</option>
            <option value="wordpress">VS Code (light)</option>
          </select>
        </div>
        <hr style="border-color:var(--mcfm-border);margin:16px 0" />
        <div class="mcfm-pane-title" style="padding:0 0 8px">Uninstall cleanup</div>
        <div class="mcfm-check">
          <input id="drop-data" v-model="form.uninstall_drop_data" type="checkbox" />
          <label for="drop-data">Remove plugin database tables on uninstall</label>
        </div>
        <div class="mcfm-check">
          <input id="drop-files" v-model="form.uninstall_drop_files" type="checkbox" />
          <label for="drop-files">Remove snapshot &amp; trash directories on uninstall</label>
        </div>
        <template v-if="isPro">
          <hr style="border-color:var(--mcfm-border);margin:16px 0" />
          <div class="mcfm-pane-title" style="padding:0 0 8px">
            Role folder visibility <span class="mcfm-pro-badge">Pro</span>
          </div>
          <div class="mcfm-field">
            <label>Rules JSON (role + allowed path prefixes)</label>
            <textarea
              v-model="form.role_folder_rules_json"
              class="mcfm-input"
              rows="6"
              style="font-family:monospace;font-size:11px"
              placeholder='[{"role":"editor","paths":["wp-content/uploads"]}]'
            ></textarea>
          </div>
        </template>
      </div>
      <div class="mcfm-modal-foot">
        <button class="mcfm-btn" @click="emit('close')">Cancel</button>
        <button class="mcfm-btn primary" @click="save">Save settings</button>
      </div>
    </div>
  </div>
</template>
