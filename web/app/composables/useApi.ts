export type Status = 'wishlist' | 'applied' | 'interview' | 'offer' | 'rejected'

export interface Application {
  id: number
  company: string
  position: string
  url: string | null
  salary_from: number | null
  salary_to: number | null
  currency: string | null
  status: Status
  notes: string
  next_action_at: string | null
  source: string
  /** comma-separated, from the list endpoint */
  tags?: string | null
  created_at: string
  updated_at: string
}

export type ApplicationForm = Partial<Omit<Application, 'id' | 'created_at' | 'updated_at' | 'source'>> &
  Pick<Application, 'company' | 'position'>

export interface Stats {
  byStatus: Record<Status, number>
  funnel: { applied: number; interview: number; offer: number }
  responseRate: number | null
  bySource: Record<string, { applied: number; interview: number; rate: number | null }>
  medianDaysToResponse: number | null
}

export interface Filters {
  tag: string
  source: string
  min_salary: string
  sort: 'updated' | 'created' | 'salary' | 'company' | 'next_action'
}

export interface ApiError {
  error: string
  fields?: Record<string, string>
  id?: number
}

const TOKEN_KEY = 'job-tracker-token'

/** API token (if the server sets API_TOKEN). Kept in localStorage: this is a personal, single-user app. */
export function useApiToken() {
  const token = useState('api-token', () => {
    if (!import.meta.client) return ''
    try {
      return localStorage.getItem(TOKEN_KEY) ?? ''
    } catch {
      return ''
    }
  })
  function save(value: string) {
    token.value = value
    try {
      if (value) localStorage.setItem(TOKEN_KEY, value)
      else localStorage.removeItem(TOKEN_KEY)
    } catch {
      // storage unavailable: the token lives for this tab only
    }
  }
  return { token, save }
}

export function useApi() {
  const { apiBase } = useRuntimeConfig().public
  const { token } = useApiToken()
  return $fetch.create({
    baseURL: apiBase,
    onRequest({ options }) {
      if (token.value) options.headers.set('authorization', `Bearer ${token.value}`)
    },
  })
}

export function apiError(e: unknown): ApiError {
  const data = (e as { data?: ApiError }).data
  return data?.error ? data : { error: 'Сервер недоступен' }
}
