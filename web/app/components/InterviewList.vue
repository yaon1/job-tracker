<script setup lang="ts">
interface Interview {
  id: number
  application_id: number
  scheduled_at: string
  kind: string
  location: string
}

const props = defineProps<{ applicationId: number }>()
const api = useApi()
const all = useState<Interview[]>('interviews', () => [])
const mine = computed(() => all.value.filter((i) => i.application_id === props.applicationId))
const form = reactive({ when: '', kind: 'tech', location: '' })
const error = ref('')
const kinds: Record<string, string> = { hr: 'HR', tech: 'Техническое', homework: 'Тестовое', final: 'Финальное', other: 'Другое' }

async function schedule() {
  error.value = ''
  try {
    // datetime-local has no zone; send it with the browser's offset so the API stores the right instant.
    const at = new Date(form.when)
    const offset = -at.getTimezoneOffset()
    const iso = `${form.when}:00${offset >= 0 ? '+' : '-'}${String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0')}:${String(Math.abs(offset) % 60).padStart(2, '0')}`
    all.value.push(await api<Interview>(`/applications/${props.applicationId}/interviews`, { method: 'POST', body: { scheduled_at: iso, kind: form.kind, location: form.location } }))
    form.when = ''
    form.location = ''
  } catch (e) {
    error.value = Object.values(apiError(e).fields ?? {}).join('; ') || apiError(e).error
  }
}

async function cancel(i: Interview) {
  await api(`/interviews/${i.id}`, { method: 'DELETE' })
  all.value = all.value.filter((x) => x.id !== i.id)
}

const when = (iso: string) => new Date(iso).toLocaleString('ru-RU', { dateStyle: 'medium', timeStyle: 'short' })
</script>

<template>
  <section>
    <h3>Собеседования</h3>
    <ul v-if="mine.length">
      <li v-for="i in mine" :key="i.id">
        {{ when(i.scheduled_at) }} · {{ kinds[i.kind] ?? i.kind }}
        <a v-if="i.location.startsWith('http')" :href="i.location" target="_blank" rel="noopener">ссылка</a>
        <span v-else-if="i.location">· {{ i.location }}</span>
        <button type="button" class="link" aria-label="Отменить собеседование" @click="cancel(i)">×</button>
      </li>
    </ul>
    <div class="add">
      <input v-model="form.when" type="datetime-local" aria-label="Дата и время" />
      <select v-model="form.kind" aria-label="Тип"><option v-for="(label, k) in kinds" :key="k" :value="k">{{ label }}</option></select>
      <input v-model="form.location" placeholder="Ссылка на встречу или адрес" aria-label="Место" />
      <button type="button" class="btn" :disabled="!form.when" @click="schedule">Назначить</button>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
  </section>
</template>

<style scoped>
h3 { font-size: 14px; margin: 0 0 6px; }
ul { list-style: none; margin: 0 0 8px; padding: 0; font-size: 14px; }
.add { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.add input, .add select { padding: 6px 8px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg); }
.link { border: 0; background: none; color: var(--muted); cursor: pointer; }
.error { color: var(--danger); font-size: 12px; }
</style>
