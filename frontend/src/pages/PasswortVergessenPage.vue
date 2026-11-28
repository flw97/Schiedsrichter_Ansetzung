<template>
  <AuthScreen
    lead="Wir schicken einen Link an die hinterlegte E-Mail. Das NuLiga-Passwort ändert sich dabei nicht."
    title="Passwort vergessen"
  >
    <v-alert
      v-if="sent"
      class="mb-4"
      density="compact"
      type="success"
      variant="tonal"
    >{{ sent }}</v-alert>

    <v-form v-else @submit.prevent="submit">
      <v-text-field
        v-model="login"
        autocomplete="username"
        class="mb-4"
        density="comfortable"
        label="Benutzername oder E-Mail"
        prepend-inner-icon="mdi-account-outline"
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
      >Link senden</v-btn>
    </v-form>

    <div class="login-extra">
      <router-link to="/">Zur Anmeldung</router-link>
    </div>
  </AuthScreen>
</template>

<script lang="ts" setup>
  import { ref } from 'vue'
  import { api, ApiError } from '@/api'
  import AuthScreen from '@/components/AuthScreen.vue'

  const login = ref('')
  const loading = ref(false)
  const error = ref('')
  const sent = ref('')

  async function submit () {
    if (!login.value.trim()) {
      error.value = 'Bitte Benutzername oder E-Mail eingeben.'
      return
    }
    loading.value = true
    error.value = ''
    try {
      const res = await api.json<{ message: string }>('POST', '/password/forgot', { login: login.value.trim() })
      sent.value = res.message
    } catch (error_) {
      error.value = error_ instanceof ApiError ? error_.message : 'Der Link konnte nicht verschickt werden.'
    } finally {
      loading.value = false
    }
  }
</script>
