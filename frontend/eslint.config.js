import vuetify from 'eslint-config-vuetify'

// frontend/old is the original prototype, not the Vue app.
export default vuetify({ ts: true }).then(config => [
  { ignores: ['old/**'] },
  ...config,
])
