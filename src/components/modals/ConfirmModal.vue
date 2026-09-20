<script setup>
import { ref } from 'vue';

defineProps({
  title: String,
  message: String,
  danger: Boolean,
  confirmLabel: { type: String, default: 'Confirm' },
});
const emit = defineEmits(['confirm', 'cancel']);

const settled = ref(false);

function confirm() {
  if (settled.value) return;
  settled.value = true;
  emit('confirm');
}

function cancel() {
  if (settled.value) return;
  settled.value = true;
  emit('cancel');
}
</script>

<template>
  <div class="mcfm-overlay" @click.self="cancel">
    <div class="mcfm-modal" @keydown.esc="cancel">
      <div class="mcfm-modal-head">{{ title }}</div>
      <div class="mcfm-modal-body">{{ message }}</div>
      <div class="mcfm-modal-foot">
        <button type="button" class="mcfm-btn" :disabled="settled" @click="cancel">Cancel</button>
        <button
          type="button"
          class="mcfm-btn"
          :class="danger ? 'danger' : 'primary'"
          :disabled="settled"
          @click="confirm"
        >{{ confirmLabel }}</button>
      </div>
    </div>
  </div>
</template>
