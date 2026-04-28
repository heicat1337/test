import { ref } from 'vue'
import type { Category } from '../types'
import { categories as staticCategories } from '../data/sites'
import { fetchNavCategories } from '../api/nav'

const categories = ref<Category[]>([])
const loading = ref(false)
const loaded = ref(false)
let inflight: AbortController | null = null
let loadPromise: Promise<void> | null = null

async function load(): Promise<void> {
  if (loaded.value) return
  if (loadPromise) return loadPromise

  inflight = new AbortController()
  loading.value = true

  loadPromise = (async () => {
    try {
      const data = await fetchNavCategories(inflight!.signal)
      categories.value = data.length ? data : staticCategories
      loaded.value = true
    } catch (err: any) {
      if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return
      // network/server failure — fall back to bundled static catalog
      categories.value = staticCategories
      loaded.value = true
    } finally {
      loading.value = false
      inflight = null
      loadPromise = null
    }
  })()

  return loadPromise
}

export function useNavCategories() {
  return { categories, loading, loaded, load }
}
