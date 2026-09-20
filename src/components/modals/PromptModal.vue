<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  title: String,
  label: String,
  value: { type: String, default: '' },
  confirmLabel: { type: String, default: 'OK' },
});
const emit = defineEmits(['confirm', 'cancel']);

const input = ref(props.value);
const field = ref(null);
const settled = ref(false);

onMounted(() => {
  field.value && field.value.focus();
  field.value && field.value.select();
});

function submit() {
  if (settled.value) return;
  settled.value = true;
  emit('confirm', input.value.trim());
}

function cancel() {
  if (settled.value) return;
  settled.value = true;
  emit('cancel');
}
</script>

<template>
  <div class="mcfm-overlay" @click.self="cancel">
    <div class="mcfm-modal" @keydown.enter.prevent="submit" @keydown.esc="cancel">
      <div class="mcfm-modal-head">{{ title }}</div>
      <div class="mcfm-modal-body">
        <div class="mcfm-field">
          <label>{{ label }}</label>
          <input ref="field" v-model="input" class="mcfm-input" type="text" />
        </div>
      </div>
      <div class="mcfm-modal-foot">
        <button type="button" class="mcfm-btn" :disabled="settled" @click="cancel">Cancel</button>
        <button type="button" class="mcfm-btn primary" :disabled="settled" @click="submit">{{ confirmLabel }}</button>
      </div>
    </div>
  </div>
</template>
