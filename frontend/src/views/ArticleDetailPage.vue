<template>
  <div class="article-detail-page">
    <div v-if="loading" class="loading-state">
      <div class="loader"></div>
      <p>加载中...</p>
    </div>

    <template v-else-if="article">
      <article class="article animate-fade-in">
        <header class="article-header">
          <div class="article-meta">
            <span v-if="article.category_name" class="meta-category">{{ article.category_name }}</span>
            <span class="meta-date">{{ formatDate(article.published_at) }}</span>
            <span class="meta-views">{{ article.view_count }} 阅读</span>
          </div>
          <h1 class="article-title">{{ article.title }}</h1>
          <p v-if="article.author_name" class="article-author">作者：{{ article.author_name }}</p>
        </header>

        <div class="article-content" v-html="renderedContent"></div>

        <footer class="article-footer">
          <router-link to="/articles" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <path d="m15 18-6-6 6-6" />
            </svg>
            返回文章列表
          </router-link>
        </footer>
      </article>
    </template>

    <div v-else class="empty-state">
      <span class="empty-icon">404</span>
      <p>文章不存在</p>
      <router-link to="/articles" class="back-link">返回文章列表</router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { marked } from 'marked'
import type { Article } from '../types'
import { fetchArticleBySlug } from '../api/articles'

const route = useRoute()
const article = ref<Article | null>(null)
const loading = ref(true)

const renderedContent = computed(() => {
  if (!article.value?.content) return ''
  return marked.parse(article.value.content, { async: false }) as string
})

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(async () => {
  try {
    const slug = route.params.slug as string
    article.value = await fetchArticleBySlug(slug)
  } catch {
    article.value = null
  } finally {
    loading.value = false
  }
})
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.article-detail-page {
  max-width: 800px;
  margin: 0 auto;
  padding: 32px 24px;
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

@keyframes spin { to { transform: rotate(360deg); } }

.article-header {
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--border-color);
}

.article-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  font-size: 13px;
}

.meta-category {
  padding: 3px 10px;
  border-radius: 999px;
  background: rgba(0, 212, 255, 0.1);
  color: var(--neon-blue);
  font-weight: 500;
}

.meta-date, .meta-views { color: var(--text-tertiary); }

.article-title {
  font-family: var(--font-display);
  font-size: 32px;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.3;
  margin-bottom: 12px;
}

.article-author {
  font-size: 14px;
  color: var(--text-secondary);
}

.article-content {
  :deep(h1), :deep(h2), :deep(h3), :deep(h4) {
    color: var(--text-primary);
    font-family: var(--font-display);
    margin: 28px 0 12px;
    line-height: 1.3;
  }

  :deep(h2) { font-size: 24px; }
  :deep(h3) { font-size: 20px; }

  :deep(p) {
    margin-bottom: 16px;
    line-height: 1.8;
    color: var(--text-secondary);
  }

  :deep(a) {
    color: var(--neon-blue);
    text-decoration: underline;
    text-underline-offset: 3px;
    &:hover { color: var(--neon-purple); }
  }

  :deep(code) {
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.06);
    font-size: 0.9em;
    color: var(--neon-green);
  }

  :deep(pre) {
    margin: 16px 0;
    padding: 16px;
    border-radius: var(--radius-sm);
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border-color);
    overflow-x: auto;
    @include scrollbar-thin();

    code {
      padding: 0;
      background: transparent;
    }
  }

  :deep(blockquote) {
    margin: 16px 0;
    padding: 12px 20px;
    border-left: 3px solid var(--neon-blue);
    background: rgba(0, 212, 255, 0.04);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    color: var(--text-secondary);
  }

  :deep(img) {
    max-width: 100%;
    border-radius: var(--radius-sm);
    margin: 16px 0;
  }

  :deep(ul), :deep(ol) {
    margin: 12px 0;
    padding-left: 24px;
    color: var(--text-secondary);
    li { margin-bottom: 6px; }
  }

  :deep(hr) {
    margin: 24px 0;
    border: none;
    height: 1px;
    background: var(--border-color);
  }
}

.article-footer {
  margin-top: 40px;
  padding-top: 24px;
  border-top: 1px solid var(--border-color);
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--text-secondary);
  font-size: 14px;
  text-decoration: none;
  transition: color var(--transition-fast);
  &:hover { color: var(--neon-blue); }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 20px;
  gap: 12px;
  color: var(--text-tertiary);
  .empty-icon { font-size: 48px; font-weight: 700; @include gradient-text(); }
}

@include responsive(mobile) {
  .article-detail-page { padding: 20px 16px; }
  .article-title { font-size: 24px; }
}
</style>
