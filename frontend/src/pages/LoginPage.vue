<template>
  <div class="login">
    <section class="login-brand">
      <img alt="Ansetzung" class="login-logo" src="/logo.svg?v=5">
      <h1>Ansetzung</h1>
      <p>Schiedsrichtereinsätze planen, abstimmen und nachverfolgen.</p>
    </section>

    <section class="login-panel">
      <div class="login-theme"><ThemeToggle /></div>

      <div class="login-form">
        <h2>Anmeldung</h2>
        <p class="login-lead">Mit deinen NuLiga-Zugangsdaten</p>

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

        <div v-if="isDev" class="demo">
          Demo-Konten, Passwort „demo1234“: m.hartmann (Master-Admin), s.weber (Admin), t.lang (Lesend), k.rehberg (Schiedsrichter)
        </div>
      </div>
    </section>
  </div>
</template>

<script lang="ts" setup>
  import { ref } from 'vue'
  import { ApiError } from '@/api'
  import ThemeToggle from '@/components/ThemeToggle.vue'
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
.login {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(300px, 440px) 1fr;
  background: var(--surface);
}
.login-brand {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 56px 48px;
  background: var(--green);
  color: #fff;
}
.login-logo { width: 92px; height: 92px; object-fit: contain; }
.login-brand h1 {
  margin: 28px 0 8px;
  font-size: 2.25rem;
  font-weight: 600;
  letter-spacing: -0.035em;
  line-height: 1.05;
}
.login-brand p {
  max-width: 26ch;
  margin: 0;
  font-size: 1rem;
  line-height: 1.5;
  color: rgba(255, 255, 255, 0.78);
}
.login-panel {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
}
.login-theme { position: absolute; top: 8px; right: 8px; }
.login-form { width: min(100%, 380px); }
.login-form h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  letter-spacing: -0.028em;
}
.login-lead { margin: 6px 0 28px; color: var(--ink-soft); }
.demo {
  margin-top: 28px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--mint);
  color: var(--ink-soft);
  font-size: 0.75rem;
  line-height: 1.45;
}
@media (max-width: 800px) {
  .login { grid-template-columns: 1fr; }
  .login-brand { justify-content: flex-start; padding: 32px 24px 28px; }
  .login-logo { width: 64px; height: 64px; }
  .login-brand h1 { margin-top: 16px; font-size: 1.75rem; }
  .login-panel { align-items: flex-start; padding: 32px 20px 48px; }
}
</style>
