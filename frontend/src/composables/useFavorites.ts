import { ref } from 'vue'
import type { Site } from '../types'

const STORAGE_KEY = 'web3_site_visits_v1'

function loadCounts(): Record<string, number> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

const counts = ref<Record<string, number>>(loadCounts())

function persist() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(counts.value))
  } catch {
    // storage may be full or disabled
  }
}

export function useFavorites() {
  function trackVisit(url: string) {
    if (!url) return
    counts.value = { ...counts.value, [url]: (counts.value[url] || 0) + 1 }
    persist()
  }

  function topSites(allSites: Site[], limit = 8): Site[] {
    return allSites
      .filter(s => (counts.value[s.url] || 0) > 0)
      .sort((a, b) => (counts.value[b.url] || 0) - (counts.value[a.url] || 0))
      .slice(0, limit)
  }

  function visitCount(url: string): number {
    return counts.value[url] || 0
  }

  return { trackVisit, topSites, visitCount, counts }
}
