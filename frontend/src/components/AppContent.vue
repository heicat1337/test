<template>
  <main class="content">
    <SkeletonLoader v-if="loading" />
    <template v-else>
      <SearchScopeChips :chip-options="chipOptions" />
      <template v-if="categories.length">
        <RecommendedSites v-if="recommended.length" :sites="recommended" />
        <section
          v-for="(cat, ci) in categories"
          :key="cat.id"
          :data-category-id="cat.id"
          class="category-section animate-slide-up"
          :style="{ animationDelay: `${Math.min(ci * 60, 400)}ms` }"
        >
          <div class="category-header">
            <span class="category-icon">{{ cat.icon }}</span>
            <h2 class="category-name">{{ cat.name }}</h2>
            <span class="category-divider"></span>
            <span class="category-count">{{ cat.sites.length }}</span>
          </div>
          <div class="card-grid">
            <SiteCard v-for="site in cat.sites" :key="site.url" :site="site" />
          </div>
        </section>
      </template>
      <div v-else class="empty-state">
        <span class="empty-icon">🔍</span>
        <p>没有找到匹配的项目</p>
      </div>
    </template>
  </main>
</template>

<script setup lang="ts">
import type { Category, Site } from '../types'
import SiteCard from './SiteCard.vue'
import SkeletonLoader from './SkeletonLoader.vue'
import RecommendedSites from './RecommendedSites.vue'
import SearchScopeChips from './SearchScopeChips.vue'

withDefaults(defineProps<{
  categories: Category[]
  chipOptions?: Category[]
  loading?: boolean
  recommended?: Site[]
}>(), {
  loading: false,
  recommended: () => [],
  chipOptions: () => []
})
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.content { flex: 1; min-width: 0; padding-bottom: 40px; }

.category-section { margin-bottom: 40px; }

.category-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
}

.category-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  line-height: 1;
  border-radius: 12px;
  background: rgba(0, 212, 255, 0.06);
  border: 1px solid rgba(0, 212, 255, 0.18);
}

.category-name {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 700;
  color: var(--text-primary);
  letter-spacing: -0.01em;
}

.category-divider {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, var(--border-color), transparent);
}

.category-count {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-tertiary);
  flex-shrink: 0;
  padding: 3px 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border-color);
}

.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  color: var(--text-tertiary);
  gap: 12px;
  .empty-icon { font-size: 48px; }
  p { font-size: 15px; }
}

@include responsive(mobile) {
  .card-grid { grid-template-columns: 1fr; }
  .category-section { margin-bottom: 32px; }
}
</style>
