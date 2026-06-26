<script setup>
defineProps({ rows: { type: Number, default: 8 } });
</script>

<template>
  <table class="mcfm-table">
    <thead>
      <tr>
        <th>Name</th>
        <th class="mcfm-col-size">Size</th>
        <th>Type</th>
        <th class="mcfm-col-snapshot">Snapshot</th>
        <th class="mcfm-col-snapshot">Last Snapshot</th>
        <th class="mcfm-col-modified">Modified</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="n in rows" :key="n" class="mcfm-skeleton-row">
        <td><span class="mcfm-skeleton" style="width:55%"></span></td>
        <td><span class="mcfm-skeleton" style="width:40px"></span></td>
        <td><span class="mcfm-skeleton" style="width:50px"></span></td>
        <td><span class="mcfm-skeleton" style="width:36px"></span></td>
        <td><span class="mcfm-skeleton" style="width:90px"></span></td>
        <td><span class="mcfm-skeleton" style="width:100px"></span></td>
      </tr>
    </tbody>
  </table>
</template>
