import { computed, type Ref } from 'vue'
import type { Category } from '../types'
import { useSearchState } from './useSearchState'

export function useSearch(categories: Ref<Category[]>) {
  const { query } = useSearchState()

  const filtered = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return categories.value

    return categories.value
      .map(cat => ({
        ...cat,
        sites: cat.sites.filter(
          site =>
            site.name.toLowerCase().includes(q) ||
            site.description.toLowerCase().includes(q) ||
            site.url.toLowerCase().includes(q)
        )
      }))
      .filter(cat => cat.sites.length > 0)
  })

  const firstMatch = computed(() => filtered.value[0]?.sites[0] || null)

  return { query, filtered, firstMatch }
}
