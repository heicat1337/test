import { ref } from 'vue'

const query = ref('')

export function useSearchState() {
  return { query }
}
