<script setup lang="ts">
const { filters, load } = useBoard()
const api = useApi()
const { data: tags } = await useAsyncData('tags', () => api<string[]>('/tags'), { default: () => [], server: false })

watch(filters, () => load(), { deep: true })
</script>

<template>
  <div class="filters" role="search">
    <label>Тег
      <select v-model="filters.tag"><option value="">все</option><option v-for="t in tags" :key="t" :value="t">{{ t }}</option></select>
    </label>
    <label>Источник
      <select v-model="filters.source"><option value="">все</option><option value="hh">hh.ru</option><option value="manual">вручную</option></select>
    </label>
    <label>Зарплата от
      <input v-model="filters.min_salary" type="number" min="0" step="10000" placeholder="₽" />
    </label>
    <label>Сортировка
      <select v-model="filters.sort">
        <option value="updated">по обновлению</option>
        <option value="created">по дате добавления</option>
        <option value="salary">по зарплате</option>
        <option value="company">по компании</option>
        <option value="next_action">по следующему шагу</option>
      </select>
    </label>
  </div>
</template>

<style scoped>
.filters { display: flex; flex-wrap: wrap; gap: 12px; }
label { display: grid; gap: 4px; font-size: 12px; color: var(--muted); }
select, input { padding: 6px 8px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); width: 160px; }
</style>
