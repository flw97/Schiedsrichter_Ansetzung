<template>
  <div class="login d-flex align-center justify-center pa-4">
    <v-card
      :border="false"
      class="pa-8"
      elevation="12"
      rounded="xl"
      width="360"
    >
      <div class="d-flex align-center ga-2 mb-1">
        <span class="dot" />
        <span class="font-display text-h6">Ansetzung</span>
      </div>

      <div class="text-body-2 text-soft mb-6">Anmeldung mit deinen NuLiga-Zugangsdaten</div>

      <v-form @submit.prevent="submit">
        <v-text-field
          v-model="username"
          autocomplete="username"
          class="mb-3"
          label="NuLiga-Benutzername"
          placeholder="max.mustermann"
          prepend-inner-icon="mdi-account-outline"
        />

        <v-text-field
          v-model="password"
          autocomplete="current-password"
          class="mb-4"
          label="Passwort"
          prepend-inner-icon="mdi-lock-outline"
          type="password"
        />

        <v-alert
          v-if="error"
          class="mb-4"
          density="compact"
          type="error"
          variant="tonal"
        >{{ error }}</v-alert>

        <v-btn
          block
          color="primary"
          :loading="loading"
          prepend-icon="mdi-shield-check-outline"
          size="large"
          type="submit"
        >Anmelden</v-btn>
      </v-form>

      <div v-if="isDev" class="text-caption text-soft mt-4">
        Demo-Konten (Passwort „demo1234“): m.hartmann (Master-Admin), s.weber (Admin), t.lang (Lesend), k.rehberg (Schiedsrichter)
      </div>
    </v-card>
  </div>
</template>

<script lang="ts" setup>
  import { ref } from 'vue'
  import { ApiError } from '@/api'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const username = ref('')
  const password = ref('')
  const loading = ref(false)
  const error = ref(store.sessionNote)
  const isDev = import.meta.env.DEV

  async function submit () {
    if (!username.value || !password.value) {
      error.value = 'Bitte NuLiga-Benutzername und Passwort eingeben.'
      return
    }
    loading.value = true
    error.value = ''
    try {
      await store.login(username.value, password.value)
    } catch (error_) {
      error.value = error_ instanceof ApiError ? error_.message : 'Anmeldung fehlgeschlagen.'
    } finally {
      loading.value = false
    }
  }
</script>

<style scoped>
.login { min-height: 100vh; background: rgb(var(--v-theme-primary)); }
.dot { width: 8px; height: 8px; border-radius: 50%; background: rgb(var(--v-theme-accent)); display: inline-block; }
</style>
