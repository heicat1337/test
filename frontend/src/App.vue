<template>
  <div class="app">
    <AppHeader />
    <router-view />
    <footer class="app-footer">
      <p>玄猫Web3 — 探索去中心化世界</p>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './components/AppHeader.vue'
import { useSearchState } from './composables/useSearchState'

const route = useRoute()
const { query } = useSearchState()

watch(() => route.name, () => {
  query.value = ''
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
    color: var(--text-tertiary);
  }
}
</style>
