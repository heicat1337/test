<template>
  <div class="home-layout">
    <AppSidebar v-if="categories.length" :categories="filtered" :active-id="activeId" />
    <div v-else class="sidebar-skeleton" aria-hidden="true">
      <div v-for="i in 6" :key="i" class="sidebar-skel-item"></div>
    </div>
    <AppContent
      :categories="filtered"
      :loading="loading && !categories.length"
      :recommended="recommendedSites"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch, nextTick } from 'vue'
import AppSidebar from '../components/AppSidebar.vue'
import AppContent from '../components/AppContent.vue'
import { useNavCategories } from '../composables/useNavCategories'
import { useSearch } from '../composables/useSearch'
import { useActiveCategory } from '../composables/useActiveCategory'

const { categories, loading, load } = useNavCategories()
const { filtered } = useSearch(categories)
const { activeId, refresh: refreshObserver } = useActiveCategory()

const recommendedSites = computed(() =>
  filtered.value.flatMap(c => c.sites).filter(s => s.is_recommended)
)

watch([filtered, loading], () => {
  nextTick(refreshObserver)
}, { flush: 'post' })

onMounted(load)
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

.sidebar-skeleton {
  width: var(--sidebar-width);
  flex-shrink: 0;
  padding: 8px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  gap: 6px;

  .sidebar-skel-item {
    height: 36px;
    border-radius: var(--radius-sm);
    background: linear-gradient(
      90deg,
      rgba(255, 255, 255, 0.04) 0%,
      rgba(255, 255, 255, 0.08) 50%,
      rgba(255, 255, 255, 0.04) 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.4s ease-in-out infinite;
  }
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@include responsive(mobile) {
  .home-layout {
    flex-direction: column;
    gap: 0;
    padding: 16px;
  }
  .sidebar-skeleton { display: none; }
}
</style>
