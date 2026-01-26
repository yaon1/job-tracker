import type { Application, ApplicationForm, Filters, Stats, Status } from './useApi'

export const COLUMNS: { status: Status; label: string }[] = [
  { status: 'wishlist', label: 'Хочу откликнуться' },
  { status: 'applied', label: 'Отклик отправлен' },
  { status: 'interview', label: 'Собеседование' },
  { status: 'offer', label: 'Оффер' },
  { status: 'rejected', label: 'Отказ' },
]

export function useBoard() {
  const api = useApi()
  const items = useState<Application[]>('applications', () => [])
  const stats = useState<Stats | null>('stats', () => null)
  const query = ref('')
  const reminders = useState<Application[]>('reminders', () => [])
  const interviews = useState<
    { id: number; application_id: number; scheduled_at: string; kind: string; location: string; company: string; position: string }[]
  >('interviews', () => [])
  const filters = useState<Filters>('filters', () => ({ tag: '', source: '', min_salary: '', sort: 'updated' }))

  const columns = computed(() => {
    const q = query.value.trim().toLowerCase()
    const visible = q ? items.value.filter((a) => `${a.company} ${a.position}`.toLowerCase().includes(q)) : items.value
    return COLUMNS.map((c) => ({ ...c, items: visible.filter((a) => a.status === c.status) }))
  })

  async function load() {
    // Empty filter values are dropped so the API applies no constraint for them.
    const params = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== ''))
    ;[items.value, stats.value, interviews.value, reminders.value] = await Promise.all([
      api<Application[]>('/applications', { query: params }),
      api<Stats>('/stats'),
      api<typeof interviews.value>('/interviews'),
      api<Application[]>('/reminders'),
    ])
  }

  async function refreshStats() {
    stats.value = await api<Stats>('/stats')
  }

  function upsert(app: Application) {
    const i = items.value.findIndex((a) => a.id === app.id)
    if (i === -1) items.value.unshift(app)
    else items.value.splice(i, 1, app)
  }

  async function move(id: number, status: Status) {
    const app = items.value.find((a) => a.id === id)
    if (!app || app.status === status) return
    const previous = app.status
    app.status = status // optimistic
    try {
      upsert(await api<Application>(`/applications/${id}/status`, { method: 'PATCH', body: { status } }))
      await refreshStats()
    } catch (e) {
      app.status = previous
      throw e
    }
  }

  async function save(form: ApplicationForm, id?: number) {
    const app = await api<Application>(id ? `/applications/${id}` : '/applications', { method: id ? 'PUT' : 'POST', body: form })
    upsert(app)
    await refreshStats()
    return app
  }

  async function importHh(url: string) {
    const app = await api<Application>('/import/hh', { method: 'POST', body: { url } })
    upsert(app)
    await refreshStats()
    return app
  }

  async function remove(id: number) {
    await api(`/applications/${id}`, { method: 'DELETE' })
    items.value = items.value.filter((a) => a.id !== id)
    await refreshStats()
  }

  return { items, stats, interviews, reminders, query, filters, columns, load, move, save, importHh, remove }
}
