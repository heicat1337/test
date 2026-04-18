<template>
  <router-link :to="`/articles/${article.slug}`" class="article-card">
    <div class="card-glow"></div>
    <div class="card-body">
      <div class="card-meta">
        <span v-if="article.category_name" class="card-category">{{ article.category_name }}</span>
        <span class="card-date">{{ formatDate(article.published_at) }}</span>
      </div>
      <h3 class="card-title">{{ article.title }}</h3>
      <p class="card-excerpt">{{ article.excerpt }}</p>
      <div class="card-footer">
        <span v-if="article.author_name" class="card-author">{{ article.author_name }}</span>
        <span class="card-views">{{ article.view_count }} 阅读</span>
      </div>
    </div>
  </router-link>
</template>

<script setup lang="ts">
import type { Article } from '../types'

defineProps<{ article: Article }>()

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('zh-CN', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<style scoped lang="scss">
.article-card {
  display: block;
  padding: 20px;
  border-radius: var(--card-radius);
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  text-decoration: none;
  position: relative;
  overflow: hidden;
  transition: all var(--transition-base);

  &:hover {
    border-color: var(--border-glow);
    background: var(--bg-card-hover);
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
    .card-glow { opacity: 1; }
    .card-title { color: var(--neon-blue); }
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

.card-body { position: relative; }

.card-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
  font-size: 12px;
}

.card-category {
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(0, 212, 255, 0.1);
  color: var(--neon-blue);
  font-weight: 500;
}

.card-date { color: var(--text-tertiary); }

.card-title {
  font-size: 17px;
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.4;
  margin-bottom: 8px;
  transition: color var(--transition-fast);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-excerpt {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.6;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: 14px;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: var(--text-tertiary);
}
</style>
