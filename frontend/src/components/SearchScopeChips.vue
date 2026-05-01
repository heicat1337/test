<template>
  <div v-if="show" class="scope-chips" role="tablist" aria-label="搜索范围">
    <button
      type="button"
      class="chip"
      :class="{ active: !categoryFilter }"
      role="tab"
      :aria-selected="!categoryFilter"
      @click="categoryFilter = ''"
    >
      <span class="chip-label">全部</span>
      <span class="chip-count">{{ totalCount }}</span>
    </button>
    <button
      v-for="cat in chipOptions"
      :key="cat.id"
      type="button"
      class="chip"
      :class="{ active: categoryFilter === cat.id }"
      role="tab"
      :aria-selected="categoryFilter === cat.id"
      @click="onChipClick(cat.id)"
    >
      <span class="chip-icon">{{ cat.icon }}</span>
      <span class="chip-label">{{ cat.name }}</span>
      <span class="chip-count">{{ cat.sites.length }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Category } from '../types'
import { useSearchState } from '../composables/useSearchState'

const props = defineProps<{ chipOptions: Category[] }>()
const { query, categoryFilter } = useSearchState()

const show = computed(() => query.value.trim().length > 0 && props.chipOptions.length > 1)
const totalCount = computed(() => props.chipOptions.reduce((sum, c) => sum + c.sites.length, 0))

function onChipClick(id: string) {
  categoryFilter.value = categoryFilter.value === id ? '' : id
}
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.scope-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border-color);
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-secondary);
  font-size: 13px;
  font-family: var(--font-sans);
  cursor: pointer;
  transition: all var(--transition-fast);

  &:hover {
    color: var(--text-primary);
    border-color: rgba(255, 255, 255, 0.18);
  }

  &.active {
    background: rgba(0, 212, 255, 0.1);
    border-color: rgba(0, 212, 255, 0.4);
    color: var(--neon-blue);
    .chip-count {
      background: rgba(0, 212, 255, 0.2);
      color: var(--neon-blue);
    }
  }
}

.chip-icon { font-size: 14px; line-height: 1; }
.chip-label { font-weight: 500; }
.chip-count {
  font-size: 11px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  color: var(--text-tertiary);
}

@include responsive(mobile) {
  .scope-chips {
    margin-bottom: 16px;
    padding-bottom: 12px;
    overflow-x: auto;
    flex-wrap: nowrap;
    @include scrollbar-thin();
    &::-webkit-scrollbar { height: 0; }
    -webkit-overflow-scrolling: touch;
  }
  .chip { flex-shrink: 0; }
}
</style>
