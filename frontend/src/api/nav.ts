import client from './client'
import type { Category, SiteDetail } from '../types'

export async function fetchNavCategories(signal?: AbortSignal): Promise<Category[]> {
  const res = await client.get('/nav/categories', { signal })
  const payload = res.data?.data
  return Array.isArray(payload) ? payload : []
}

export async function fetchSiteById(id: number | string, signal?: AbortSignal): Promise<SiteDetail> {
  const res = await client.get(`/nav/sites/${id}`, { signal })
  return res.data?.data as SiteDetail
}
