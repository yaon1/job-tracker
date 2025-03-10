<script setup lang="ts">
import type { Application, Status } from '~/composables/useApi'

const props = defineProps<{ app: Application }>()
const emit = defineEmits<{ open: []; move: [status: Status] }>()

const salary = computed(() => {
  const { salary_from: from, salary_to: to, currency } = props.app
  if (from === null && to === null) return null
  const n = (v: number) => v.toLocaleString('ru-RU')
  const range = from !== null && to !== null ? `${n(from)}–${n(to)}` : from !== null ? `от ${n(from)}` : `до ${n(to!)}`
  return `${range} ${currency === 'RUR' ? '₽' : (currency ?? '')}`.trim()
})

const overdue = computed(() => props.app.next_action_at !== null && props.app.next_action_at <= new Date().toISOString().slice(0, 10))

function onDragStart(e: DragEvent) {
  e.dataTransfer?.setData('text/plain', String(props.app.id))
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}
</script>

<template>
  <article class="card" draggable="true" @dragstart="onDragStart">
    <button class="open" type="button" @click="emit('open')">
      <strong>{{ app.position }}</strong>
      <span class="company">{{ app.company }}</span>
    </button>
    <div class="meta">
      <span v-if="salary">{{ salary }}</span>
      <span v-if="app.next_action_at" :class="{ overdue }">⏰ {{ new Date(app.next_action_at).toLocaleDateString('ru-RU') }}</span>
      <span v-if="app.source === 'hh'" class="badge">hh</span>
    </div>
    <!-- Keyboard and touch alternative to drag-and-drop -->
    <label class="sr-only" :for="`move-${app.id}`">Статус</label>
    <select :id="`move-${app.id}`" class="move" :value="app.status" @change="emit('move', ($event.target as HTMLSelectElement).value as Status)">
      <option v-for="c in COLUMNS" :key="c.status" :value="c.status">{{ c.label }}</option>
    </select>
  </article>
</template>

<style scoped>
.card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 12px; display: grid; gap: 6px; cursor: grab; }
.card:active { cursor: grabbing; }
.open { all: unset; display: grid; gap: 2px; cursor: pointer; border-radius: 4px; }
.open:focus-visible { outline: 2px solid var(--accent); }
.company { color: var(--muted); font-size: 14px; }
.meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: var(--muted); }
.overdue { color: var(--danger); font-weight: 600; }
.badge { background: #d6001c; color: #fff; padding: 0 6px; border-radius: 4px; font-weight: 600; }
.move { font-size: 12px; padding: 2px 4px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
