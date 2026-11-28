<template>
  <div class="account-page">
    <PageHeader title="Lizenz">
      Deine Lizenzstufe und wie lange sie gilt.
    </PageHeader>

    <div class="account-grid">
      <v-card class="pa-4">
        <div class="text-body-1 font-weight-medium mb-3">Lizenz</div>

        <v-select v-model="form.license" class="mb-3" :items="LICENSE_LEVELS" label="Lizenzstufe" />
        <v-text-field v-model="form.until" class="mb-3" label="Gültig bis" type="date" />
        <v-btn color="primary" @click="save">Speichern</v-btn>
      </v-card>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import { reactive } from 'vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { LICENSE_LEVELS } from '@/domain/rules'
  import { notifyError } from '@/notify'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const mine = store.me
  const form = reactive({
    license: mine?.license ?? LICENSE_LEVELS[0],
    until: mine?.license_valid_until ?? '',
  })

  async function save () {
    if (!form.until) {
      notifyError('Bitte ein Gültigkeitsdatum wählen.')
      return
    }
    await store.act('PUT', '/me/license', { license: form.license, license_valid_until: form.until }, 'Gespeichert.')
  }
</script>
