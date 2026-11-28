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
        <h2>{{ title }}</h2>
        <p v-if="lead" class="login-lead">{{ lead }}</p>
        <slot />
      </div>
    </section>
  </div>
</template>

<script lang="ts" setup>
  import ThemeToggle from '@/components/ThemeToggle.vue'

  defineProps<{ title: string, lead?: string }>()
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
.login-form :deep(.demo) {
  margin-top: 28px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--mint);
  color: var(--ink-soft);
  font-size: 0.75rem;
  line-height: 1.45;
}
.login-form :deep(.login-extra) { margin-top: 18px; font-size: 0.875rem; }
.login-form :deep(.login-extra a) {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  text-decoration: none;
}
@media (max-width: 800px) {
  .login { grid-template-columns: 1fr; }
  .login-brand { justify-content: flex-start; padding: 32px 24px 28px; }
  .login-logo { width: 64px; height: 64px; }
  .login-brand h1 { margin-top: 16px; font-size: 1.75rem; }
  .login-panel { align-items: flex-start; padding: 32px 20px 48px; }
}
</style>
