<script setup lang="ts">
import type { Stats } from '~/composables/useApi'

defineProps<{ stats: Stats }>()
</script>

<template>
  <dl class="stats">
    <div>
      <dt>Откликов</dt>
      <dd>{{ stats.funnel.applied }}</dd>
    </div>
    <div>
      <dt>Собеседований</dt>
      <dd>{{ stats.funnel.interview }}</dd>
    </div>
    <div>
      <dt>Офферов</dt>
      <dd>{{ stats.funnel.offer }}</dd>
    </div>
    <div>
      <dt>Конверсия в интервью</dt>
      <dd>{{ stats.responseRate === null ? '—' : `${stats.responseRate}%` }}</dd>
    </div>
    <div>
      <dt>Медиана до ответа</dt>
      <dd>{{ stats.medianDaysToResponse === null ? '—' : `${stats.medianDaysToResponse} дн.` }}</dd>
    </div>
    <div v-for="(s, source) in stats.bySource" :key="source">
      <dt>{{ source === 'hh' ? 'hh.ru' : source === 'manual' ? 'Вручную' : source }}</dt>
      <dd>
        {{ s.rate === null ? '—' : `${s.rate}%` }} <small>{{ s.interview }}/{{ s.applied }}</small>
      </dd>
    </div>
  </dl>
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  margin: 0;
}
.stats div {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 10px 14px;
}
dt {
  font-size: 12px;
  color: var(--muted);
}
small {
  font-size: 12px;
  font-weight: 400;
  color: var(--muted);
}
dd {
  margin: 2px 0 0;
  font-size: 22px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
</style>
