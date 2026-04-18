<template>
  <aside class="sidebar" :class="{ collapsed: isMobile }">
    <nav class="sidebar-nav">
      <button
        v-for="cat in categories"
        :key="cat.id"
        class="sidebar-item"
        :class="{ active: activeId === cat.id }"
        @click="scrollTo(cat.id)"
      >
        <span class="sidebar-icon">{{ cat.icon }}</span>
        <span class="sidebar-label">{{ cat.name }}</span>
        <span class="sidebar-count">{{ cat.sites.length }}</span>
      </button>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import type { Category } from '../types'

defineProps<{ categories: Category[]; activeId: string }>()

const isMobile = ref(false)

function checkMobile() { isMobile.value = window.innerWidth < 769 }

function scrollTo(id: string) {
  const el = document.querySelector(`[data-category-id="${id}"]`)
  if (el) {
    const top = el.getBoundingClientRect().top + window.scrollY - 90
    window.scrollTo({ top, behavior: 'smooth' })
  }
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
  padding: 8px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  @include glass(var(--bg-sidebar), 12px);
  @include scrollbar-thin();
}

.sidebar-nav { display: flex; flex-direction: column; gap: 2px; }

.sidebar-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text-secondary);
  font-size: 14px;
  font-family: var(--font-sans);
  cursor: pointer;
  transition: all var(--transition-fast);
  position: relative;
  text-align: left;

  &:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-primary); }

  &.active {
    background: rgba(0, 212, 255, 0.08);
    color: var(--neon-blue);

    &::before {
      content: '';
      position: absolute;
      left: 0; top: 50%;
      transform: translateY(-50%);
      width: 3px; height: 20px;
      border-radius: 0 2px 2px 0;
      background: var(--gradient-primary);
    }

    .sidebar-count { background: rgba(0, 212, 255, 0.15); color: var(--neon-blue); }
  }
}

.sidebar-icon { font-size: 18px; line-height: 1; flex-shrink: 0; }
.sidebar-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.sidebar-count {
  font-size: 11px;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  color: var(--text-tertiary);
  transition: all var(--transition-fast);
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
  padding: 8px 12px;
  @include glass(rgba(10, 14, 26, 0.9), 12px);

  .sidebar-nav {
    flex-direction: row;
    gap: 4px;
    overflow-x: auto;
    @include scrollbar-thin();
    padding-bottom: 2px;
    &::-webkit-scrollbar { height: 2px; }
  }

  .sidebar-item {
    flex-shrink: 0;
    padding: 8px 12px;
    border-radius: 999px;
    gap: 6px;
    &.active::before { display: none; }
    &.active { border: 1px solid rgba(0, 212, 255, 0.3); }
  }

  .sidebar-count { display: none; }
}
</style>
