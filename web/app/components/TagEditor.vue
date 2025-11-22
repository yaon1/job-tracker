<script setup lang="ts">
const props = defineProps<{ applicationId: number; initial: string[] }>()
const emit = defineEmits<{ saved: [tags: string[]] }>()
const api = useApi()
const text = ref(props.initial.join(', '))
const saving = ref(false)

async function save() {
  saving.value = true
  try {
    const tags = await api<string[]>(`/applications/${props.applicationId}/tags`, { method: 'PUT', body: { tags: text.value.split(',') } })
    text.value = tags.join(', ')
    emit('saved', tags)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <label class="field"
    >Теги через запятую
    <span class="row">
      <input v-model="text" placeholder="remote, startup, fintech" @keydown.enter.prevent="save" />
      <button type="button" class="btn" :disabled="saving" @click="save">Сохранить</button>
    </span>
  </label>
</template>

<style scoped>
.row {
  display: flex;
  gap: 6px;
}
.row input {
  flex: 1;
}
</style>
