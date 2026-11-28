<template>
  <div class="account-page">
    <PageHeader title="Benutzer">
      Name, Benutzername und E-Mail deines Kontos.
    </PageHeader>

    <div class="account-grid">
      <v-card class="pa-4">
        <div class="text-body-1 font-weight-medium mb-3">Konto</div>

        <v-text-field v-model="form.name" class="mb-3" label="Name" />
        <v-text-field v-model="form.username" class="mb-3" label="Benutzername" />
        <v-text-field v-model="form.email" class="mb-3" label="E-Mail" type="email" />
        <v-btn color="primary" @click="saveAccount">Speichern</v-btn>
      </v-card>

      <v-card class="pa-4">
        <div class="text-body-1 font-weight-medium mb-3">Passwort</div>

        <v-text-field
          v-model="form.password"
          autocomplete="new-password"
          class="mb-3"
          label="Neues Passwort"
          :type="show ? 'text' : 'password'"
        >
          <template #append-inner>
            <v-btn
              :aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'"
              :icon="show ? 'mdi-eye-off' : 'mdi-eye'"
              size="small"
              variant="text"
              @click="show = !show"
            />
          </template>
        </v-text-field>

        <v-text-field
          v-model="form.confirm"
          autocomplete="new-password"
          class="mb-3"
          label="Passwort wiederholen"
          :type="show ? 'text' : 'password'"
        />

        <v-text-field
          v-model="form.current"
          autocomplete="current-password"
          class="mb-3"
          label="Aktuelles Passwort"
          type="password"
        />

        <v-btn color="primary" @click="savePassword">Passwort speichern</v-btn>
      </v-card>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { notifyError } from '@/notify'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const me = store.state!.me
  const show = ref(false)
  const form = reactive({
    name: me.name,
    username: me.username,
    email: me.email ?? '',
    password: '',
    confirm: '',
    current: '',
  })

  function saveAccount () {
    return store.act('PUT', '/me', {
      name: form.name.trim(),
      username: form.username.trim(),
      email: form.email.trim() || null,
    }, 'Gespeichert.')
  }

  async function savePassword () {
    if (form.password.length < 8) {
      notifyError('Das Passwort muss mindestens 8 Zeichen haben.')
      return
    }
    if (form.password !== form.confirm) {
      notifyError('Die Passwörter stimmen nicht überein.')
      return
    }
    if (!form.current) {
      notifyError('Bitte das aktuelle Passwort eingeben.')
      return
    }
    if (await store.act('PUT', '/me', { password: form.password, current_password: form.current }, 'Passwort gespeichert.')) {
      form.password = ''
      form.confirm = ''
      form.current = ''
      show.value = false
    }
  }
</script>
