<template>
  <v-menu location="bottom end">
    <template #activator="{ props }">
      <v-btn
        aria-label="Farbschema"
        icon
        v-bind="props"
        variant="text"
      >
        <v-icon :icon="current.icon" />
      </v-btn>
    </template>

    <v-list class="theme-menu" density="compact">
      <v-list-item
        v-for="item in items"
        :key="item.value"
        :active="choice === item.value"
        color="primary"
        :prepend-icon="item.icon"
        :title="item.title"
        @click="apply(item.value)"
      />
    </v-list>
  </v-menu>
</template>

<script lang="ts" setup>
  import { computed } from 'vue'
  import { type ThemeChoice, useThemeChoice } from '@/theme'

  const { choice, apply } = useThemeChoice()
  const items: { value: ThemeChoice, title: string, icon: string }[] = [
    { value: 'system', title: 'System', icon: 'mdi-theme-light-dark' },
    { value: 'light', title: 'Hell', icon: 'mdi-weather-sunny' },
    { value: 'dark', title: 'Dunkel', icon: 'mdi-weather-night' },
  ]
  const current = computed(() => items.find(item => item.value === choice.value) ?? items[0])
</script>

<style scoped>
.theme-menu { min-width: 168px; }
</style>
