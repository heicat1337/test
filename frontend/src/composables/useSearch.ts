import { computed, type Ref } from 'vue'
import type { Category } from '../types'
import { useSearchState } from './useSearchState'

export function useSearch(categories: Ref<Category[]>) {
  const { query, categoryFilter } = useSearchState()

  // Filter narrowed by query only — used to count matches per category for chips
  const queryFiltered = computed(() => {
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

  // Final filter: scope to selected category chip — only when a query is active
  const filtered = computed(() => {
    const hasQuery = query.value.trim().length > 0
    if (!hasQuery || !categoryFilter.value) return queryFiltered.value
    return queryFiltered.value.filter(c => c.id === categoryFilter.value)
  })

  const firstMatch = computed(() => filtered.value[0]?.sites[0] || null)

  return { query, categoryFilter, filtered, queryFiltered, firstMatch }
}
