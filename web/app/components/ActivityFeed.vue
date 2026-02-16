<script setup lang="ts">
interface Activity {
  id: number
  happened_at: string
  text: string
}

const props = defineProps<{ applicationId: number }>()
const api = useApi()
const entries = ref<Activity[]>([])
const text = ref('')

onMounted(async () => (entries.value = await api<Activity[]>(`/applications/${props.applicationId}/activity`)))

async function add() {
  if (!text.value.trim()) return
  entries.value.unshift(
    await api<Activity>(`/applications/${props.applicationId}/activity`, { method: 'POST', body: { text: text.value } }),
  )
  text.value = ''
}

const when = (iso: string) => new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <section>
    <h3>Журнал</h3>
    <div class="add">
      <input v-model="text" placeholder="Что произошло? (Enter — сохранить)" aria-label="Новая запись" @keydown.enter.prevent="add" />
    </div>
    <ol>
      <li v-for="e in entries" :key="e.id">
        <time :datetime="e.happened_at">{{ when(e.happened_at) }}</time> {{ e.text }}
      </li>
    </ol>
  </section>
</template>

<style scoped>
h3 {
  font-size: 14px;
  margin: 0 0 6px;
}
.add input {
  width: 100%;
  padding: 6px 8px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg);
}
ol {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: grid;
  gap: 4px;
  font-size: 14px;
  max-height: 160px;
  overflow-y: auto;
}
time {
  color: var(--muted);
  font-size: 12px;
  margin-right: 6px;
}
</style>
