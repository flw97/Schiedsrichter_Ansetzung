<template>
  <v-card class="px-4 py-3" min-width="140">
    <div class="text-caption text-soft d-flex align-center ga-2"><slot name="label">{{ label }}</slot></div>
    <div class="font-display text-h6">{{ value }}</div>
  </v-card>
</template>

<script lang="ts" setup>
  defineProps<{ label?: string, value: string | number }>()
</script>
