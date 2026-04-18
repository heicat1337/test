<template>
  <div class="app">
    <AppHeader v-model:query="searchQuery" />
    <router-view v-slot="{ Component }">
      <component :is="Component" ref="pageRef" />
    </router-view>
    <footer class="app-footer">
      <p>Web3 Nav — 探索去中心化世界</p>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './components/AppHeader.vue'

const route = useRoute()
const searchQuery = ref('')
const pageRef = ref<any>(null)

watch(searchQuery, (val) => {
  if (pageRef.value?.query !== undefined) {
    pageRef.value.query = val
  }
})

watch(() => route.name, () => {
  searchQuery.value = ''
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
