<template>
  <AuthScreen lead="Wähle ein neues Passwort für die Ansetzung." title="Neues Passwort">
    <v-alert
      v-if="!email || !token"
      class="mb-4"
      density="compact"
      type="error"
      variant="tonal"
    >Der Link ist unvollständig. Fordere einen neuen an.</v-alert>

    <v-alert
      v-else-if="done"
      class="mb-4"
      density="compact"
      type="success"
      variant="tonal"
    >Das Passwort wurde gespeichert. Du kannst dich jetzt anmelden.</v-alert>

    <v-form v-else @submit.prevent="submit">
      <v-text-field
        v-model="password"
        autocomplete="new-password"
        class="mb-3"
        density="comfortable"
        label="Neues Passwort"
        prepend-inner-icon="mdi-lock-outline"
        type="password"
      />

      <v-text-field
        v-model="confirmation"
        autocomplete="new-password"
        class="mb-4"
        density="comfortable"
        label="Passwort wiederholen"
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
      >Passwort speichern</v-btn>
    </v-form>

    <div class="login-extra">
      <router-link v-if="email && token && !done" to="/passwort-vergessen">Neuen Link anfordern</router-link>
      <router-link v-else to="/">Zur Anmeldung</router-link>
    </div>
  </AuthScreen>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue'
  import { useRoute } from 'vue-router'
  import { api, ApiError, token as sessionToken } from '@/api'
  import AuthScreen from '@/components/AuthScreen.vue'
  import { useAppStore } from '@/stores/app'

  const route = useRoute()
  const store = useAppStore()
  const password = ref('')
  const confirmation = ref('')
  const loading = ref(false)
  const error = ref('')
  const done = ref(false)

  function queryText (value: unknown) {
    return typeof value === 'string' ? value : ''
  }

  const email = computed(() => queryText(route.query.email))
  const token = computed(() => queryText(route.query.token))

  async function submit () {
    if (password.value.length < 8) {
      error.value = 'Das Passwort muss mindestens 8 Zeichen haben.'
      return
    }
    if (password.value !== confirmation.value) {
      error.value = 'Die Passwörter stimmen nicht überein.'
      return
    }
    loading.value = true
    error.value = ''
    try {
      await api.json('POST', '/password/reset', {
        email: email.value,
        token: token.value,
        password: password.value,
        password_confirmation: confirmation.value,
      })
      if (sessionToken.get() || sessionToken.refresh()) {
        await store.logout()
      }
      done.value = true
    } catch (error_) {
      error.value = error_ instanceof ApiError ? error_.message : 'Das Passwort konnte nicht gespeichert werden.'
    } finally {
      loading.value = false
    }
  }
</script>
