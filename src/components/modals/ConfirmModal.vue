<script setup>
defineProps({
  title: String,
  message: String,
  danger: Boolean,
  confirmLabel: { type: String, default: 'Confirm' },
});
const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
  <div class="mcfm-overlay" @click.self="emit('cancel')">
    <div class="mcfm-modal" @keydown.esc="emit('cancel')">
      <div class="mcfm-modal-head">{{ title }}</div>
      <div class="mcfm-modal-body">{{ message }}</div>
      <div class="mcfm-modal-foot">
        <button class="mcfm-btn" @click="emit('cancel')">Cancel</button>
        <button class="mcfm-btn" :class="danger ? 'danger' : 'primary'" @click="emit('confirm')">{{ confirmLabel }}</button>
      </div>
    </div>
  </div>
</template>
