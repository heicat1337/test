import { onMounted, onUnmounted } from 'vue'

// Observes the .app-header element and exposes its actual height as a CSS
// variable on :root so sticky-positioned children below it can offset
// correctly on every breakpoint (the header wraps to a taller stack on mobile).
export function useHeaderHeight() {
  let observer: ResizeObserver | null = null
  let target: HTMLElement | null = null

  function update() {
    if (!target) return
    const h = Math.round(target.getBoundingClientRect().height)
    document.documentElement.style.setProperty('--header-height', `${h}px`)
  }

  onMounted(() => {
    target = document.querySelector('.app-header')
    if (!target) return
    update()
    if (typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(update)
      observer.observe(target)
    }
    window.addEventListener('resize', update)
  })

  onUnmounted(() => {
    observer?.disconnect()
    observer = null
    window.removeEventListener('resize', update)
    target = null
  })
}
