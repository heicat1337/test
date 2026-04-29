<template>
  <section v-if="sites.length" class="recommended animate-slide-up">
    <div class="recommended-header">
      <span class="recommended-icon">⭐</span>
      <h2 class="recommended-name">新人推荐</h2>
      <span class="recommended-divider"></span>
      <span class="recommended-hint">{{ sites.length }} 个精选站点</span>
    </div>
    <div class="card-grid">
      <SiteCard v-for="site in sites" :key="site.url" :site="site" />
    </div>
  </section>
</template>

<script setup lang="ts">
import type { Site } from '../types'
import SiteCard from './SiteCard.vue'
defineProps<{ sites: Site[] }>()
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.recommended { margin-bottom: 40px; }

.recommended-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
}

.recommended-icon { font-size: 22px; line-height: 1; }

.recommended-name {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 700;
  background: var(--gradient-accent);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.recommended-divider {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, var(--border-color), transparent);
}

.recommended-hint { font-size: 12px; color: var(--text-tertiary); flex-shrink: 0; }

.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;
}

@include responsive(mobile) {
  .card-grid { grid-template-columns: 1fr; }
  .recommended { margin-bottom: 32px; }
}
</style>
