<script setup>
defineProps({ rows: { type: Number, default: 6 } });
</script>

<template>
  <div
    v-for="n in rows"
    :key="n"
    class="mcfm-skeleton-tree"
    :style="{ paddingLeft: 8 + (n % 3) * 12 + 'px' }"
  >
    <span class="mcfm-skeleton" style="width:70%;display:inline-block"></span>
  </div>
</template>
