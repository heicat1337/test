import { onMounted, onUnmounted, ref } from 'vue'

export function useActiveCategory() {
  const activeId = ref('')
  let observer: IntersectionObserver | null = null
  let scheduledTimer: number | null = null

  function setup() {
    cleanupObserver()
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

  function cleanupObserver() {
    observer?.disconnect()
    observer = null
  }

  function clearScheduled() {
    if (scheduledTimer !== null) {
      window.clearTimeout(scheduledTimer)
      scheduledTimer = null
    }
  }

  function refresh() {
    clearScheduled()
    scheduledTimer = window.setTimeout(() => {
      scheduledTimer = null
      setup()
    }, 100)
  }

  onMounted(refresh)
  onUnmounted(() => {
    clearScheduled()
    cleanupObserver()
  })

  return { activeId, refresh }
}
