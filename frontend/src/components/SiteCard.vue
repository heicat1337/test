<template>
  <a
    :href="site.url"
    target="_blank"
    rel="noopener noreferrer"
    class="site-card"
    :class="{ 'is-featured': site.is_recommended }"
    @click="onClick"
    @auxclick="onClick"
  >
    <div class="card-glow"></div>
    <div class="card-content">
      <div class="card-header">
        <span class="card-icon" :title="site.name">{{ site.icon || '🌐' }}</span>
        <span class="card-name">{{ site.name }}</span>
        <span v-if="site.is_recommended" class="card-flag" title="新人推荐">★</span>
        <span v-if="visits > 0" class="card-badge" :title="`已访问 ${visits} 次`">{{ visits }}</span>
      </div>
      <p class="card-desc">{{ site.description }}</p>
    </div>
    <div class="card-arrow" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M7 17 17 7M7 7h10v10" />
      </svg>
    </div>
  </a>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Site } from '../types'
import { useFavorites } from '../composables/useFavorites'

const props = defineProps<{ site: Site }>()
const { trackVisit, visitCount } = useFavorites()
const visits = computed(() => visitCount(props.site.url))

function onClick() {
  trackVisit(props.site.url)
}
</script>

<style scoped lang="scss">
.site-card {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 16px;
  border-radius: var(--card-radius);
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
  overflow: hidden;
  transition: all var(--transition-base);
  min-height: 92px;

  &:hover {
    border-color: var(--border-glow);
    background: var(--bg-card-hover);
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
    .card-glow { opacity: 1; }
    .card-arrow { opacity: 1; transform: translate(0, 0); }
    .card-name { color: var(--neon-blue); }
    .card-icon { border-color: rgba(0, 212, 255, 0.35); }
  }

  &.is-featured {
    border-color: rgba(0, 255, 136, 0.25);
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.04), rgba(0, 212, 255, 0.04));

    .card-icon {
      background: rgba(0, 255, 136, 0.1);
      border-color: rgba(0, 255, 136, 0.25);
    }
    &:hover { border-color: rgba(0, 255, 136, 0.5); }
  }
}

.card-glow {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: var(--gradient-primary);
  opacity: 0;
  transition: opacity var(--transition-base);
}

.card-content { flex: 1; min-width: 0; }

.card-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.card-icon {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  line-height: 1;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid var(--border-color);
  transition: all var(--transition-base);
}

.card-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-primary);
  transition: color var(--transition-fast);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}

.card-flag {
  flex-shrink: 0;
  font-size: 12px;
  color: var(--neon-green);
  line-height: 1;
}

.card-badge {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 999px;
  background: rgba(0, 255, 136, 0.12);
  color: var(--neon-green);
  letter-spacing: 0.02em;
}

.card-desc {
  font-size: 13px;
  color: var(--text-secondary);
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-arrow {
  position: absolute;
  top: 14px; right: 14px;
  opacity: 0;
  transform: translate(-4px, 4px);
  transition: all var(--transition-base);
  color: var(--neon-blue);
  svg { width: 16px; height: 16px; }
}
</style>
