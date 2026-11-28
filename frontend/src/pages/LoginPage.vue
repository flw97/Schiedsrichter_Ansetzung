<template>
  <AuthScreen lead="Mit deinen NuLiga-Zugangsdaten" title="Anmeldung">
    <v-form @submit.prevent="submit">
      <v-text-field
        v-model="username"
        autocomplete="username"
        class="mb-3"
        density="comfortable"
        label="NuLiga-Benutzername"
        placeholder="max.mustermann"
        prepend-inner-icon="mdi-account-outline"
      />

      <v-text-field
        v-model="password"
        autocomplete="current-password"
        class="mb-4"
        density="comfortable"
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
        size="large"
        type="submit"
      >Anmelden</v-btn>
    </v-form>

    <div class="login-extra">
      <router-link to="/passwort-vergessen">Passwort vergessen?</router-link>
    </div>

    <div v-if="isDev" class="demo">
      Demo-Konten, Passwort „demo1234“: m.hartmann (Master-Admin), s.weber (Admin), t.lang (Lesend), k.rehberg (Schiedsrichter)
    </div>
  </AuthScreen>
</template>

<script lang="ts" setup>
  import { ref } from 'vue'
  import { ApiError } from '@/api'
  import AuthScreen from '@/components/AuthScreen.vue'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const username = ref('')
  const password = ref('')
  const loading = ref(false)
  const error = ref('')
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
