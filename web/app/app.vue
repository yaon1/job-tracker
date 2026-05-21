<script setup lang="ts">
import type { Application, Status } from '~/composables/useApi'

const { stats, query, columns, load, move, importHh } = useBoard()
const dialog = ref<{ open: () => void }>()
const editing = ref<Application | null>(null)
const hhUrl = ref('')
const notice = ref('')
const loadError = ref('')
const needsToken = ref(false)

onMounted(async () => {
  await reload()
})

async function reload() {
  loadError.value = ''
  needsToken.value = false
  try {
    await load()
  } catch (e) {
    if ((e as { statusCode?: number }).statusCode === 401) needsToken.value = true
    else loadError.value = apiError(e).error
  }
}

function edit(app: Application | null) {
  editing.value = app
  nextTick(() => dialog.value?.open())
}

// Keyboard shortcuts: "n" — new application, "/" — focus search.
function onKey(e: KeyboardEvent) {
  const target = e.target as HTMLElement
  if (target.closest('input, textarea, select, [contenteditable], dialog[open]')) return
  if (e.key === 'n') {
    e.preventDefault()
    edit(null)
  } else if (e.key === '/') {
    e.preventDefault()
    document.querySelector<HTMLInputElement>('.search')?.focus()
  }
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

async function onMove(id: number, status: Status) {
  try {
    await move(id, status)
  } catch (e) {
    notice.value = apiError(e).error
  }
}

const importing = ref(false)

async function onImport() {
  notice.value = ''
  importing.value = true
  try {
    const app = await importHh(hhUrl.value)
    hhUrl.value = ''
    notice.value = `Добавлено: ${app.position} — ${app.company}`
  } catch (e) {
    notice.value = apiError(e).error
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div class="page">
    <header class="top">
      <h1>Трекер откликов</h1>
      <form class="import" @submit.prevent="onImport">
        <input v-model="hhUrl" type="url" placeholder="https://hh.ru/vacancy/…" aria-label="Ссылка на вакансию hh.ru" required />
        <button class="btn" :disabled="importing" :aria-busy="importing">{{ importing ? 'Загружаю…' : 'Импорт с hh.ru' }}</button>
      </form>
      <input v-model="query" class="search" type="search" placeholder="Поиск по компании или должности (/)" aria-label="Поиск" />
      <button class="btn primary" title="Клавиша N" @click="edit(null)">+ Отклик</button>
    </header>

    <p v-if="notice" class="notice" role="status">{{ notice }}</p>
    <p v-if="loadError" class="notice error" role="alert">API недоступно: {{ loadError }}</p>
    <TokenPrompt v-if="needsToken" @saved="reload" />

    <StatsBar v-if="stats" :stats="stats" />
    <AgendaPanel @open="edit" />
    <div class="toolbar">
      <FilterBar />
      <CsvTools />
    </div>

    <main class="board">
      <BoardColumn v-for="c in columns" :key="c.status" v-bind="c" @drop="onMove" @open="edit" />
    </main>

    <ApplicationDialog ref="dialog" :app="editing" @close="editing = null" />
  </div>
</template>

<style scoped>
.page {
  padding: 16px;
  display: grid;
  gap: 16px;
}
.top {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
h1 {
  font-size: 20px;
  margin: 0 auto 0 0;
}
.import {
  display: flex;
  gap: 6px;
}
.import input,
.search {
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--card);
  min-width: 0;
}
.import input {
  width: 260px;
}
.notice {
  margin: 0;
  padding: 8px 12px;
  border-radius: var(--radius);
  background: var(--card);
  border: 1px solid var(--border);
}
.notice.error {
  color: var(--danger);
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: end;
  gap: 12px;
}
.board {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 8px;
  align-items: stretch;
  min-height: 60vh;
}
@media (max-width: 640px) {
  .import,
  .import input,
  .search {
    width: 100%;
  }
}
</style>
