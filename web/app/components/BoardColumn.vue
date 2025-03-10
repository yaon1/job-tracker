<script setup lang="ts">
import type { Application, Status } from '~/composables/useApi'

const props = defineProps<{ status: Status; label: string; items: Application[] }>()
const emit = defineEmits<{ drop: [id: number, status: Status]; open: [app: Application] }>()
const over = ref(false)

function onDrop(e: DragEvent) {
  over.value = false
  const id = Number(e.dataTransfer?.getData('text/plain'))
  if (id) emit('drop', id, props.status)
}
</script>

<template>
  <section class="column" :class="{ over }" @dragover.prevent="over = true" @dragleave="over = false" @drop.prevent="onDrop">
    <h2>{{ label }} <span class="count">{{ items.length }}</span></h2>
    <div class="list">
      <ApplicationCard v-for="app in items" :key="app.id" :app="app" @open="emit('open', app)" @move="(s) => emit('drop', app.id, s)" />
      <p v-if="items.length === 0" class="empty">Перетащите карточку сюда</p>
    </div>
  </section>
</template>

<style scoped>
.column { background: color-mix(in srgb, var(--border) 40%, transparent); border-radius: var(--radius); padding: 10px; min-width: 260px; flex: 1; display: flex; flex-direction: column; }
.column.over { outline: 2px dashed var(--accent); }
h2 { font-size: 14px; margin: 4px 4px 10px; display: flex; justify-content: space-between; }
.count { color: var(--muted); font-weight: 400; }
.list { display: grid; gap: 8px; align-content: start; }
.empty { color: var(--muted); font-size: 13px; text-align: center; margin: 12px 0; }
</style>
