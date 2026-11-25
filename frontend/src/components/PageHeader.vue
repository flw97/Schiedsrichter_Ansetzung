<template>
  <div class="mb-5">
    <div class="d-flex align-center flex-wrap ga-3">
      <h1 class="font-display text-h5 flex-grow-1">{{ title }}</h1>
      <slot name="actions" />
    </div>

    <div v-if="subtitle || $slots.default" class="text-body-2 text-soft mt-1"><slot>{{ subtitle }}</slot></div>
  </div>
</template>

<script lang="ts" setup>
  defineProps<{ title: string, subtitle?: string }>()
</script>
