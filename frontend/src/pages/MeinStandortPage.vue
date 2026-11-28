<template>
  <div class="max-w-md">
    <PageHeader title="Standort">
      Deine Adresse und die Karte sind dieselbe Position. Tippen verschiebt die Markierung, ein Klick oder Ziehen setzt die Adresse.
    </PageHeader>

    <AddressPicker v-model:address="form.address" v-model:lat="form.lat" v-model:lon="form.lon" />
    <v-btn class="mt-3" color="primary" @click="save">Speichern</v-btn>
  </div>
</template>

<script lang="ts" setup>
  import { reactive } from 'vue'
  import AddressPicker from '@/components/AddressPicker.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const mine = store.me
  const form = reactive({
    address: mine?.address ?? '',
    lat: mine?.lat ?? null,
    lon: mine?.lon ?? null,
  })

  function save () {
    return store.act('PUT', '/me/standort', {
      address: form.address.trim() || null,
      lat: form.address.trim() ? form.lat : null,
      lon: form.address.trim() ? form.lon : null,
    }, 'Gespeichert.')
  }
</script>
