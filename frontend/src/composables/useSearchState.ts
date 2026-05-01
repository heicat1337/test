import { ref, watch } from 'vue'

const query = ref('')
const categoryFilter = ref<string>('')

// Reset chip filter when the query is cleared so it can't linger invisibly.
watch(query, v => {
  if (!v.trim()) categoryFilter.value = ''
})

export function useSearchState() {
  return { query, categoryFilter }
}
