<template>
  <div class="articles-page">
    <div class="articles-header animate-slide-up">
      <h2 class="page-title">Web3 文章</h2>
      <p class="page-desc">探索最新的 Web3 行业资讯、技术分析与深度研究</p>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="loader"></div>
      <p>加载中...</p>
    </div>

    <template v-else-if="articles.length">
      <div class="articles-grid">
        <ArticleCard
          v-for="article in articles"
          :key="article.id"
          :article="article"
          class="animate-slide-up"
        />
      </div>
      <div v-if="totalPages > 1" class="pagination">
        <button
          class="page-btn"
          :disabled="page <= 1"
          @click="goPage(page - 1)"
        >上一页</button>
        <span class="page-info">{{ page }} / {{ totalPages }}</span>
        <button
          class="page-btn"
          :disabled="page >= totalPages"
          @click="goPage(page + 1)"
        >下一页</button>
      </div>
    </template>

    <div v-else class="empty-state">
      <span class="empty-icon">📝</span>
      <p>暂无文章</p>
      <p class="empty-hint">请先在后台管理系统中发布文章</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Article } from '../types'
import { fetchArticles } from '../api/articles'
import ArticleCard from '../components/ArticleCard.vue'

const articles = ref<Article[]>([])
const loading = ref(true)
const page = ref(1)
const totalPages = ref(1)

async function loadArticles() {
  loading.value = true
  try {
    const res = await fetchArticles(page.value)
    articles.value = res.data || []
    totalPages.value = res.total_pages || 1
  } catch {
    articles.value = []
  } finally {
    loading.value = false
  }
}

function goPage(p: number) {
  page.value = p
  loadArticles()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(loadArticles)
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.articles-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 32px 24px;
}

.articles-header {
  margin-bottom: 32px;
}

.page-title {
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 700;
  @include gradient-text();
  margin-bottom: 8px;
}

.page-desc {
  font-size: 15px;
  color: var(--text-secondary);
}

.articles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 0;
  gap: 16px;
  color: var(--text-tertiary);
}

.loader {
  width: 36px;
  height: 36px;
  border: 3px solid var(--border-color);
  border-top-color: var(--neon-blue);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 20px;
  color: var(--text-tertiary);
  gap: 8px;
  .empty-icon { font-size: 48px; }
  p { font-size: 15px; }
  .empty-hint { font-size: 13px; }
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 40px;
}

.page-btn {
  padding: 8px 20px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  background: var(--bg-card);
  color: var(--text-primary);
  font-size: 14px;
  font-family: var(--font-sans);
  cursor: pointer;
  transition: all var(--transition-fast);

  &:hover:not(:disabled) {
    border-color: var(--neon-blue);
    color: var(--neon-blue);
  }

  &:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
}

.page-info {
  font-size: 14px;
  color: var(--text-secondary);
}

@include responsive(mobile) {
  .articles-page { padding: 20px 16px; }
  .articles-grid { grid-template-columns: 1fr; }
  .page-title { font-size: 22px; }
}
</style>
