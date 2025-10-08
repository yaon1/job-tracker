<script setup lang="ts">
const api = useApi()
const { load } = useBoard()
const message = ref('')
const input = ref<HTMLInputElement>()

async function exportCsv() {
  const csv = await api<string>('/export.csv', { responseType: 'text' })
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  Object.assign(document.createElement('a'), { href: url, download: 'otkliki.csv' }).click()
  URL.revokeObjectURL(url)
}

async function importCsv() {
  const file = input.value?.files?.[0]
  if (!file) return
  try {
    const { imported } = await api<{ imported: number }>('/import.csv', { method: 'POST', body: await file.text(), headers: { 'content-type': 'text/csv' } })
    message.value = `Импортировано: ${imported}`
    await load()
  } catch (e) {
    message.value = Object.entries(apiError(e).fields ?? {}).map(([k, v]) => `${k}: ${v}`).join('; ') || apiError(e).error
  } finally {
    if (input.value) input.value.value = ''
  }
}
</script>

<template>
  <div class="csv">
    <button type="button" class="btn" @click="exportCsv">Экспорт CSV</button>
    <label class="btn">Импорт CSV <input ref="input" type="file" accept=".csv,text/csv" hidden @change="importCsv" /></label>
    <span v-if="message" role="status">{{ message }}</span>
  </div>
</template>

<style scoped>
.csv { display: flex; gap: 8px; align-items: center; font-size: 14px; }
label.btn { cursor: pointer; }
</style>
