import client from './client'
import type { Category } from '../types'

export async function fetchNavCategories(): Promise<Category[]> {
  const res = await client.get('/nav/categories')
  return res.data.data
}
