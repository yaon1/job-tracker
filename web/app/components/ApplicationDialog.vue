<script setup lang="ts">
import type { Application, ApplicationForm } from '~/composables/useApi'

const props = defineProps<{ app: Application | null }>()
const emit = defineEmits<{ close: [] }>()
const { save, remove, items } = useBoard()

// Update the board's copy rather than the prop, so cards reflect new tags immediately.
function onTagsSaved(tags: string[]) {
  const item = items.value.find((a) => a.id === props.app?.id)
  if (item) item.tags = tags.join(',')
}

const dialog = ref<HTMLDialogElement>()
const errors = ref<Record<string, string>>({})
const message = ref('')
// After a 409 duplicate warning, a second save forces creation.
const duplicateConfirmed = ref(false)
const form = reactive<ApplicationForm>({ company: '', position: '' })

function open() {
  const a = props.app
  Object.assign(form, {
    company: a?.company ?? '',
    position: a?.position ?? '',
    url: a?.url ?? '',
    salary_from: a?.salary_from ?? null,
    salary_to: a?.salary_to ?? null,
    currency: a?.currency ?? 'RUR',
    status: a?.status ?? 'wishlist',
    notes: a?.notes ?? '',
    next_action_at: a?.next_action_at ?? '',
    resume: a?.resume ?? '',
  })
  errors.value = {}
  message.value = ''
  duplicateConfirmed.value = false
  dialog.value?.showModal()
}

defineExpose({ open })

async function submit() {
  try {
    const toInt = (v: unknown) => (v === '' || v === null || v === undefined ? null : Number(v))
    await save({ ...form, salary_from: toInt(form.salary_from), salary_to: toInt(form.salary_to) }, props.app?.id, duplicateConfirmed.value)
    dialog.value?.close()
  } catch (e) {
    const err = apiError(e)
    errors.value = err.fields ?? {}
    message.value = err.fields ? '' : err.id ? `${err.error} — проверьте доску или сохраните ещё раз, чтобы добавить всё равно` : err.error
    if (err.id) duplicateConfirmed.value = true
  }
}

async function destroy() {
  if (!props.app) return
  await remove(props.app.id)
  dialog.value?.close()
}
</script>

<template>
  <dialog ref="dialog" class="dialog" @close="emit('close')">
    <form method="dialog" @submit.prevent="submit">
      <h2>{{ app ? 'Редактировать отклик' : 'Новый отклик' }}</h2>
      <div class="grid">
        <label class="field"
          >Компания<input v-model="form.company" required /><span v-if="errors.company" class="error">{{ errors.company }}</span></label
        >
        <label class="field"
          >Должность<input v-model="form.position" required /><span v-if="errors.position" class="error">{{ errors.position }}</span></label
        >
        <label class="field wide"
          >Ссылка<input v-model="form.url" type="url" /><span v-if="errors.url" class="error">{{ errors.url }}</span></label
        >
        <label class="field">Зарплата от<input v-model="form.salary_from" type="number" min="0" /></label>
        <label class="field"
          >до<input v-model="form.salary_to" type="number" min="0" /><span v-if="errors.salary_to" class="error">{{
            errors.salary_to
          }}</span></label
        >
        <label class="field"
          >Статус
          <select v-model="form.status">
            <option v-for="c in COLUMNS" :key="c.status" :value="c.status">{{ c.label }}</option>
          </select>
        </label>
        <label class="field">Резюме<input v-model="form.resume" placeholder="например, backend-2026-10" maxlength="100" /></label>
        <label class="field">Следующий шаг<input v-model="form.next_action_at" type="date" /></label>
        <TagEditor
          v-if="app"
          class="wide"
          :application-id="app.id"
          :initial="(app.tags ?? '').split(',').filter(Boolean)"
          @saved="onTagsSaved"
        />
        <ActivityFeed v-if="app" :key="`a${app.id}`" class="wide" :application-id="app.id" />
        <InterviewList v-if="app" class="wide" :application-id="app.id" />
        <ContactList v-if="app" :key="`c${app.id}`" class="wide" :application-id="app.id" />
        <label class="field wide"
          >Заметки<textarea v-model="form.notes" rows="4" placeholder="Контакты, вопросы с собеседования, впечатления…" />
        </label>
      </div>
      <p v-if="message" class="field">
        <span class="error">{{ message }}</span>
      </p>
      <footer>
        <button v-if="app" type="button" class="btn danger" @click="destroy">Удалить</button>
        <span class="spacer" />
        <button type="button" class="btn" @click="dialog?.close()">Отмена</button>
        <button type="submit" class="btn primary">Сохранить</button>
      </footer>
    </form>
  </dialog>
</template>

<style scoped>
.dialog {
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  width: min(560px, calc(100vw - 32px));
  background: var(--card);
  color: var(--text);
}
.dialog::backdrop {
  background: rgb(0 0 0 / 0.4);
}
h2 {
  margin: 0 0 16px;
  font-size: 18px;
}
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.wide {
  grid-column: 1 / -1;
}
footer {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}
.spacer {
  flex: 1;
}
@media (max-width: 480px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
