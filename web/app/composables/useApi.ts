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
  created_at: string
  updated_at: string
}

export type ApplicationForm = Partial<Omit<Application, 'id' | 'created_at' | 'updated_at' | 'source'>> &
  Pick<Application, 'company' | 'position'>

export interface Stats {
  byStatus: Record<Status, number>
  funnel: { applied: number; interview: number; offer: number }
  responseRate: number | null
}

export interface ApiError {
  error: string
  fields?: Record<string, string>
  id?: number
}

export function useApi() {
  const { apiBase } = useRuntimeConfig().public
  return $fetch.create({ baseURL: apiBase })
}

export function apiError(e: unknown): ApiError {
  const data = (e as { data?: ApiError }).data
  return data?.error ? data : { error: 'Сервер недоступен' }
}
