<script setup lang="ts">
interface Contact {
  id: number
  name: string
  role: string
  email: string | null
  phone: string | null
  telegram: string | null
}

const props = defineProps<{ applicationId: number }>()
const api = useApi()
const contacts = ref<Contact[]>([])
const form = reactive({ name: '', role: '', email: '', telegram: '' })
const errors = ref<Record<string, string>>({})

onMounted(async () => (contacts.value = await api<Contact[]>(`/applications/${props.applicationId}/contacts`)))

async function add() {
  errors.value = {}
  try {
    contacts.value.push(await api<Contact>(`/applications/${props.applicationId}/contacts`, { method: 'POST', body: { ...form } }))
    Object.assign(form, { name: '', role: '', email: '', telegram: '' })
  } catch (e) {
    errors.value = apiError(e).fields ?? {}
  }
}

async function remove(c: Contact) {
  await api(`/applications/${props.applicationId}/contacts/${c.id}`, { method: 'DELETE' })
  contacts.value = contacts.value.filter((x) => x.id !== c.id)
}
</script>

<template>
  <section class="contacts">
    <h3>Контакты</h3>
    <ul v-if="contacts.length">
      <li v-for="c in contacts" :key="c.id">
        <strong>{{ c.name }}</strong
        ><span v-if="c.role"> · {{ c.role }}</span>
        <a v-if="c.telegram" :href="`https://t.me/${c.telegram}`" target="_blank" rel="noopener">@{{ c.telegram }}</a>
        <a v-if="c.email" :href="`mailto:${c.email}`">{{ c.email }}</a>
        <button type="button" class="link" :aria-label="`Удалить ${c.name}`" @click="remove(c)">×</button>
      </li>
    </ul>
    <div class="add">
      <input v-model="form.name" placeholder="Имя" aria-label="Имя контакта" />
      <input v-model="form.role" placeholder="Роль (HR, тимлид…)" aria-label="Роль" />
      <input v-model="form.telegram" placeholder="@telegram" aria-label="Telegram" />
      <input v-model="form.email" type="email" placeholder="email" aria-label="Email" />
      <button type="button" class="btn" @click="add">Добавить</button>
    </div>
    <p v-for="(msg, field) in errors" :key="field" class="error">{{ msg }}</p>
  </section>
</template>

<style scoped>
h3 {
  font-size: 14px;
  margin: 0 0 6px;
}
ul {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  display: grid;
  gap: 4px;
  font-size: 14px;
}
li {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: baseline;
}
.add {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}
.add input {
  padding: 6px 8px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg);
}
.link {
  border: 0;
  background: none;
  color: var(--muted);
  cursor: pointer;
}
.error {
  color: var(--danger);
  font-size: 12px;
  margin: 4px 0 0;
}
</style>
