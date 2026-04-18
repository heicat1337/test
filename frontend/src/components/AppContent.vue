<template>
  <main class="content">
    <template v-if="categories.length">
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
          <span class="category-count">{{ cat.sites.length }} 个项目</span>
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
  </main>
</template>

<script setup lang="ts">
import type { Category } from '../types'
import SiteCard from './SiteCard.vue'
defineProps<{ categories: Category[] }>()
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

.category-icon { font-size: 22px; line-height: 1; }

.category-name {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 700;
  color: var(--text-primary);
}

.category-divider {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, var(--border-color), transparent);
}

.category-count { font-size: 12px; color: var(--text-tertiary); flex-shrink: 0; }

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
