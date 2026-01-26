<script setup lang="ts">
import type { Application } from '~/composables/useApi'

const emit = defineEmits<{ open: [app: Application] }>()
// Loaded together with the board, so they appear as soon as the token is entered.
const { interviews, items, reminders } = useBoard()
const { apiBase } = useRuntimeConfig().public
const { token } = useApiToken()

const upcoming = computed(() => interviews.value.filter((i) => new Date(i.scheduled_at) > new Date()).slice(0, 5))
const calendarUrl = computed(() => `${apiBase}/interviews.ics${token.value ? `?token=${encodeURIComponent(token.value)}` : ''}`)
const when = (iso: string) =>
  new Date(iso).toLocaleString('ru-RU', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
const openById = (id: number) => {
  const app = items.value.find((a) => a.id === id)
  if (app) emit('open', app)
}
</script>

<template>
  <aside v-if="upcoming.length || reminders.length" class="agenda">
    <section v-if="upcoming.length">
      <h2>Ближайшие собеседования</h2>
      <ul>
        <li v-for="i in upcoming" :key="i.id">
          <button type="button" class="link" @click="openById(i.application_id)">{{ when(i.scheduled_at) }} — {{ i.company }}</button>
        </li>
      </ul>
      <a class="subscribe" :href="calendarUrl">Подписаться в календаре (.ics)</a>
    </section>
    <section v-if="reminders.length">
      <h2>Пора напомнить о себе</h2>
      <ul>
        <li v-for="r in reminders" :key="r.id">
          <button type="button" class="link" @click="emit('open', r)">{{ r.company }} · {{ r.position }}</button>
          <span class="date">{{ new Date(r.next_action_at!).toLocaleDateString('ru-RU') }}</span>
        </li>
      </ul>
    </section>
  </aside>
</template>

<style scoped>
.agenda {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 12px;
}
section {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 12px 14px;
}
h2 {
  font-size: 14px;
  margin: 0 0 8px;
}
ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 4px;
  font-size: 14px;
}
.link {
  all: unset;
  cursor: pointer;
  color: var(--text);
}
.link:hover {
  color: var(--accent);
}
.link:focus-visible {
  outline: 2px solid var(--accent);
}
.date {
  color: var(--danger);
  font-size: 12px;
  margin-left: 6px;
}
.subscribe {
  display: inline-block;
  margin-top: 8px;
  font-size: 13px;
  color: var(--accent);
}
</style>
