import { ref, onMounted, onUnmounted } from 'vue'

export function useActiveCategory() {
  const activeId = ref('')
  let observer: IntersectionObserver | null = null

  function setup() {
    cleanup()
    const sections = document.querySelectorAll('[data-category-id]')
    if (!sections.length) return

    observer = new IntersectionObserver(
      entries => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            activeId.value = (entry.target as HTMLElement).dataset.categoryId || ''
          }
        }
      },
      { rootMargin: '-80px 0px -60% 0px', threshold: 0 }
    )
    sections.forEach(el => observer!.observe(el))
  }

  function cleanup() {
    observer?.disconnect()
    observer = null
  }

  onMounted(() => setTimeout(setup, 100))
  onUnmounted(cleanup)

  return { activeId, refresh: setup }
}
