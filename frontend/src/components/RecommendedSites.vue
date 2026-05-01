<template>
  <section v-if="sites.length" class="recommended animate-slide-up">
    <div class="recommended-header">
      <span class="recommended-icon">⭐</span>
      <h2 class="recommended-name">新人推荐</h2>
      <span class="recommended-divider"></span>
      <span class="recommended-hint">{{ sites.length }} 个精选</span>
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

.recommended {
  position: relative;
  margin-bottom: 40px;
  padding: 20px 20px 24px;
  border-radius: var(--radius-lg);
  border: 1px solid rgba(0, 255, 136, 0.18);
  background:
    radial-gradient(80% 120% at 0% 0%, rgba(0, 255, 136, 0.06), transparent 60%),
    radial-gradient(80% 120% at 100% 0%, rgba(0, 212, 255, 0.06), transparent 60%);
}

.recommended-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
}

.recommended-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  line-height: 1;
  border-radius: 12px;
  background: rgba(0, 255, 136, 0.1);
  border: 1px solid rgba(0, 255, 136, 0.25);
}

.recommended-name {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.01em;
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

.recommended-hint {
  font-size: 11px;
  font-weight: 600;
  color: var(--neon-green);
  flex-shrink: 0;
  padding: 3px 8px;
  border-radius: 999px;
  background: rgba(0, 255, 136, 0.08);
  border: 1px solid rgba(0, 255, 136, 0.2);
}

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
