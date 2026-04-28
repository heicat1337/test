import client from './client'
import type { Category } from '../types'

export async function fetchNavCategories(signal?: AbortSignal): Promise<Category[]> {
  const res = await client.get('/nav/categories', { signal })
  const payload = res.data?.data
  return Array.isArray(payload) ? payload : []
}
