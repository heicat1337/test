<template>
  <div class="app">
    <AppHeader />
    <router-view />
    <footer class="app-footer">
      <p>玄猫Web3 — 探索去中心化世界</p>
      <nav class="footer-links">
        <router-link to="/about">关于</router-link>
        <router-link to="/contact">联系</router-link>
        <router-link to="/privacy">隐私</router-link>
        <router-link to="/terms">条款</router-link>
      </nav>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './components/AppHeader.vue'
import { useSearchState } from './composables/useSearchState'
import { useHeaderHeight } from './composables/useHeaderHeight'

const route = useRoute()
const { query, categoryFilter } = useSearchState()

useHeaderHeight()

watch(() => route.fullPath, () => {
  if (route.name === 'home' && typeof route.query.q === 'string' && route.query.q) {
    query.value = String(route.query.q)
    return
  }
  if (route.name !== 'home') {
    query.value = ''
    categoryFilter.value = ''
  }
})
</script>

<style scoped lang="scss">
.app-footer {
  text-align: center;
  padding: 24px;
  border-top: 1px solid var(--border-color);
  margin-top: 40px;

  p {
    font-size: 13px;
    color: var(--text-secondary);
  }

  .footer-links {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 8px;
    a {
      font-size: 13px;
      color: var(--text-secondary);
      text-decoration: none;
      &:hover { color: var(--neon-blue); }
    }
  }
}
</style>
