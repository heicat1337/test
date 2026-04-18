<template>
  <div class="home-layout">
    <AppSidebar :categories="filtered" :active-id="activeId" />
    <AppContent :categories="filtered" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppSidebar from '../components/AppSidebar.vue'
import AppContent from '../components/AppContent.vue'
import { categories as staticCategories } from '../data/sites'
import { fetchNavCategories } from '../api/nav'
import { useSearch } from '../composables/useSearch'
import { useActiveCategory } from '../composables/useActiveCategory'

const categories = ref(staticCategories)
const { query, filtered } = useSearch(categories)
const { activeId } = useActiveCategory()

defineExpose({ query })

onMounted(async () => {
  try {
    const data = await fetchNavCategories()
    if (data?.length) categories.value = data
  } catch {
    // fallback to static data
  }
})
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.home-layout {
  display: flex;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

@include responsive(mobile) {
  .home-layout {
    flex-direction: column;
    gap: 0;
    padding: 16px;
  }
}
</style>
