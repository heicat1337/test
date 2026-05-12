<template>
  <aside class="sidebar" :class="{ collapsed: isMobile }">
    <nav class="sidebar-nav">
      <a
        v-for="cat in categories"
        :key="cat.id"
        :href="`/c/${cat.slug || cat.id}`"
        class="sidebar-item"
        :class="{ active: activeId === cat.id }"
        @click="onItemClick($event, cat)"
      >
        <span class="sidebar-icon">{{ cat.icon }}</span>
        <span class="sidebar-label">{{ cat.name }}</span>
        <span class="sidebar-count">{{ cat.sites.length }}</span>
      </a>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Category } from '../types'

defineProps<{ categories: Category[]; activeId: string }>()

const route = useRoute()
const router = useRouter()
const isMobile = ref(false)

function checkMobile() { isMobile.value = window.innerWidth < 769 }

function onItemClick(e: MouseEvent, cat: Category) {
  // 让浏览器/爬虫看到真实的 <a href>；只拦截普通左键点击做 SPA 行为
  if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return
  e.preventDefault()
  // 在首页：滚动到对应 anchor，保留原 UX
  if (route.name === 'home') {
    const el = document.querySelector(`[data-category-id="${cat.id}"]`)
    if (el) {
      const top = el.getBoundingClientRect().top + window.scrollY - 90
      window.scrollTo({ top, behavior: 'smooth' })
      return
    }
  }
  // 其他页面：路由到独立分类页
  router.push(`/c/${cat.slug || cat.id}`)
}

onMounted(() => { checkMobile(); window.addEventListener('resize', checkMobile) })
onUnmounted(() => window.removeEventListener('resize', checkMobile))
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.sidebar {
  position: sticky;
  top: calc(var(--header-height) + 16px);
  width: var(--sidebar-width);
  max-height: calc(100vh - var(--header-height) - 32px);
  flex-shrink: 0;
  overflow-y: auto;
  padding: 10px;
  border-radius: 20px;
  border: 1px solid rgba(0, 212, 255, 0.18);
  background:
    linear-gradient(180deg, rgba(17, 24, 39, 0.78), rgba(10, 14, 26, 0.55)),
    rgba(15, 20, 40, 0.42);
  backdrop-filter: blur(18px) saturate(1.1);
  -webkit-backdrop-filter: blur(18px) saturate(1.1);
  box-shadow:
    0 18px 48px rgba(0, 0, 0, 0.22),
    0 0 0 1px rgba(0, 212, 255, 0.06),
    inset 0 1px 0 rgba(255, 255, 255, 0.06);
  @include scrollbar-thin();
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sidebar-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 42px;
  padding: 8px 10px;
  border: 1px solid transparent;
  border-radius: 13px;
  background: transparent;
  color: var(--text-secondary);
  font-size: 14px;
  font-family: var(--font-sans);
  cursor: pointer;
  transition:
    color var(--transition-base),
    background var(--transition-base),
    border-color var(--transition-base),
    transform var(--transition-base),
    box-shadow var(--transition-base);
  position: relative;
  text-align: left;

  &::after {
    content: '';
    position: absolute;
    inset: 6px auto 6px -1px;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--neon-blue), var(--neon-purple));
    opacity: 0;
    transform: scaleY(0.45);
    box-shadow: 0 0 12px rgba(0, 212, 255, 0.5);
    transition: all var(--transition-base);
  }

  &:hover {
    background: rgba(0, 212, 255, 0.06);
    border-color: rgba(0, 212, 255, 0.15);
    color: var(--text-primary);
    transform: translateX(3px);
  }

  &.active {
    background: linear-gradient(
      90deg,
      rgba(0, 212, 255, 0.12) 0%,
      rgba(124, 58, 237, 0.06) 60%,
      transparent
    );
    border-color: rgba(0, 212, 255, 0.22);
    color: var(--text-primary);
    box-shadow:
      0 0 18px rgba(0, 212, 255, 0.08),
      inset 0 0 0 1px rgba(0, 212, 255, 0.08);

    &::after {
      opacity: 1;
      transform: scaleY(1);
    }

    .sidebar-icon {
      background: linear-gradient(135deg, rgba(0, 212, 255, 0.18), rgba(124, 58, 237, 0.12));
      box-shadow:
        0 0 0 1px rgba(0, 212, 255, 0.3) inset,
        0 0 16px rgba(0, 212, 255, 0.12);
    }

    .sidebar-label {
      color: var(--text-primary);
      font-weight: 700;
      letter-spacing: 0.02em;
    }

    .sidebar-count {
      background: linear-gradient(135deg, rgba(0, 212, 255, 0.22), rgba(124, 58, 237, 0.12));
      color: var(--neon-blue);
      border-color: rgba(0, 212, 255, 0.25);
      box-shadow: 0 0 10px rgba(0, 212, 255, 0.1);
    }
  }
}

.sidebar-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  line-height: 1;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.055);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
  transition: all var(--transition-fast);
}

.sidebar-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: 0.01em;
}

.sidebar-count {
  min-width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  padding: 0 7px;
  border-radius: 999px;
  border: 1px solid rgba(0, 212, 255, 0.12);
  background: rgba(0, 212, 255, 0.07);
  color: var(--neon-blue);
  transition: all var(--transition-base);
}

.sidebar.collapsed {
  position: sticky;
  top: var(--header-height);
  z-index: 50;
  width: 100%;
  max-height: none;
  border-radius: 0;
  border-left: none;
  border-right: none;
  border-bottom: 1px solid rgba(0, 212, 255, 0.15);
  padding: 8px 12px;
  background: rgba(10, 14, 26, 0.92);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.3),
    0 0 0 1px rgba(0, 212, 255, 0.04);

  .sidebar-nav {
    flex-direction: row;
    gap: 6px;
    overflow-x: auto;
    @include scrollbar-thin();
    padding-bottom: 2px;
    &::-webkit-scrollbar { height: 2px; }
  }

  .sidebar-item {
    flex-shrink: 0;
    min-height: auto;
    padding: 8px 14px;
    border-radius: 999px;
    gap: 6px;
    transform: none;
    &::after { display: none; }
    &.active {
      background: linear-gradient(90deg, rgba(0, 212, 255, 0.15), rgba(124, 58, 237, 0.08));
      border: 1px solid rgba(0, 212, 255, 0.3);
      box-shadow: 0 0 12px rgba(0, 212, 255, 0.08);
    }
  }

  .sidebar-count { display: none; }
}
</style>
