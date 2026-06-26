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

onMounted(() => {
  field.value && field.value.focus();
  field.value && field.value.select();
});

function submit() {
  emit('confirm', input.value.trim());
}
</script>

<template>
  <div class="mcfm-overlay" @click.self="emit('cancel')">
    <div class="mcfm-modal" @keydown.enter="submit" @keydown.esc="emit('cancel')">
      <div class="mcfm-modal-head">{{ title }}</div>
      <div class="mcfm-modal-body">
        <div class="mcfm-field">
          <label>{{ label }}</label>
          <input ref="field" v-model="input" class="mcfm-input" type="text" />
        </div>
      </div>
      <div class="mcfm-modal-foot">
        <button class="mcfm-btn" @click="emit('cancel')">Cancel</button>
        <button class="mcfm-btn primary" @click="submit">{{ confirmLabel }}</button>
      </div>
    </div>
  </div>
</template>
