<template>
  <header class="app-header">
    <div class="header-inner">
      <div class="header-left">
        <router-link to="/" class="logo">
          <span class="logo-icon">◈</span>
          <h1 class="logo-text">玄猫Web3</h1>
        </router-link>
        <nav class="header-tabs">
          <router-link to="/" class="tab" :class="{ active: route.name === 'home' }">导航</router-link>
          <router-link to="/articles" class="tab" :class="{ active: route.name === 'articles' || route.name === 'article-detail' }">文章</router-link>
        </nav>
      </div>
      <SearchBar
        v-if="showSearch"
        v-model="query"
        :placeholder="searchPlaceholder"
        :hint="hint"
        @submit="onSubmit"
      />
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import SearchBar from './SearchBar.vue'
import { useSearchState } from '../composables/useSearchState'
import { useSearch } from '../composables/useSearch'
import { useNavCategories } from '../composables/useNavCategories'

const route = useRoute()
const { query } = useSearchState()
const { categories } = useNavCategories()
const { firstMatch } = useSearch(categories)

const showSearch = computed(() => route.name === 'home' || route.name === 'articles')
const isHome = computed(() => route.name === 'home')
const searchPlaceholder = computed(() =>
  isHome.value ? '搜索 Web3 项目（回车直达）...' : '搜索文章...'
)
const hint = computed(() => {
  if (!isHome.value || !query.value.trim()) return ''
  return firstMatch.value ? `↵ 打开 ${firstMatch.value.name}` : ''
})

function onSubmit() {
  if (!isHome.value) return
  const target = firstMatch.value
  if (target?.url) {
    window.open(target.url, '_blank', 'noopener,noreferrer')
  }
}
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.app-header {
  position: sticky;
  top: 0;
  z-index: 100;
  padding: 0 24px;
  border-bottom: 1px solid var(--border-color);
  @include glass(rgba(10, 14, 26, 0.8), 16px);
}

.header-inner {
  max-width: 1400px;
  margin: 0 auto;
  /* 固定高度（不用 --header-height）：那个变量被 useHeaderHeight 测量后回写，
     一旦在这里读它就和 border 形成 1px 累加的反馈死循环 */
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 24px;
  flex-shrink: 0;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}

.logo-icon {
  font-size: 28px;
  @include gradient-text();
  line-height: 1;
}

.logo-text {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 700;
  @include gradient-text();
  letter-spacing: -0.02em;
}

.header-tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.04);
}

.tab {
  padding: 6px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-secondary);
  text-decoration: none;
  transition: all var(--transition-fast);

  &:hover { color: var(--text-primary); }

  &.active {
    background: rgba(0, 212, 255, 0.1);
    color: var(--neon-blue);
  }
}

@include responsive(mobile) {
  .app-header { padding: 0 16px; }

  .header-inner {
    flex-wrap: wrap;
    height: auto;
    padding: 12px 0;
    gap: 12px;
  }

  .header-left { width: 100%; }
  .logo-text { font-size: 18px; }

  :deep(.search-bar) { max-width: 100%; width: 100%; }
}
</style>
