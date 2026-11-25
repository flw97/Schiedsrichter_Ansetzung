<template>
  <v-dialog v-model="open" max-width="460">
    <template #activator="{ props: p }">
      <v-btn v-bind="{ ...p, ...$attrs }" :color="color ?? 'error'" :variant="variant ?? 'text'">{{ label }}</v-btn>
    </template>

    <v-card :title="title ?? label">
      <v-card-text>
        <div class="text-body-2 mb-3">{{ text }}</div>
        <v-text-field v-if="reason !== undefined" v-model="value" maxlength="200" placeholder="Grund (optional)" />
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Abbrechen</v-btn>
        <v-btn :color="color ?? 'error'" variant="flat" @click="confirm">{{ confirmLabel ?? 'Ja' }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import { ref, watch } from 'vue'

  defineOptions({ inheritAttrs: false })
  const props = defineProps<{ label: string, text: string, title?: string, confirmLabel?: string, color?: string, variant?: 'text' | 'outlined' | 'flat' | 'tonal', reason?: string }>()
  const emit = defineEmits<{ confirm: [reason: string] }>()
  const open = ref(false)
  const value = ref('')
  watch(open, o => o && (value.value = props.reason ?? ''))

  function confirm () {
    open.value = false
    emit('confirm', value.value.trim())
  }
</script>
