<script setup lang="ts">
const emit = defineEmits<{ saved: [] }>()
const { save } = useApiToken()
const value = ref('')
</script>

<template>
  <form class="token" @submit.prevent="(save(value.trim()), emit('saved'))">
    <p>Сервер защищён токеном (<code>API_TOKEN</code>). Введите его — он сохранится только в этом браузере.</p>
    <input v-model="value" type="password" autocomplete="current-password" aria-label="Токен API" required />
    <button class="btn primary">Войти</button>
  </form>
</template>

<style scoped>
.token {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  padding: 16px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.token p {
  width: 100%;
  margin: 0;
}
input {
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg);
}
</style>
