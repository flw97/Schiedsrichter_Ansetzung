<template>
  <header class="page-header">
    <div class="d-flex align-start flex-wrap ga-3">
      <h1 class="page-title flex-grow-1">{{ title }}</h1>
      <div v-if="$slots.actions" class="d-flex align-center flex-wrap ga-2"><slot name="actions" /></div>
    </div>

    <div v-if="subtitle || $slots.default" class="page-sub"><slot>{{ subtitle }}</slot></div>
  </header>
</template>

<script lang="ts" setup>
  defineProps<{ title: string, subtitle?: string }>()
</script>

<style scoped>
.page-header { margin-bottom: 24px; }
.page-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  letter-spacing: -0.028em;
  line-height: 1.2;
  color: var(--ink);
}
.page-sub {
  max-width: 76ch;
  margin-top: 6px;
  color: var(--ink-soft);
  font-size: 0.925rem;
  line-height: 1.55;
}
</style>
