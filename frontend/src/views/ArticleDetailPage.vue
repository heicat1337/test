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
          <img
            v-if="article.featured_image"
            class="article-cover"
            :src="article.featured_image"
            :alt="article.title"
            width="1200"
            height="630"
          />
        </header>

        <div class="article-content" v-html="renderedContent"></div>

        <aside class="article-cta">
          <p class="disclosure">
            <strong>推广披露：</strong>文中或下方推荐的外部网站可能含推荐/联盟链接。本站可能因此获得佣金，这不会额外增加你的费用。内容不构成投资建议。
          </p>
          <div class="cta-row">
            <router-link to="/articles" class="back-link">← 返回文章目录</router-link>
            <router-link to="/" class="back-link">浏览 Web3 导航</router-link>
          </div>
          <div class="share-row">
            <span>分享</span>
            <button type="button" @click="copyLink">{{ copied ? '已复制' : '复制链接' }}</button>
            <a :href="twitterShare" target="_blank" rel="noopener noreferrer">X</a>
            <a :href="weiboShare" target="_blank" rel="noopener noreferrer">微博</a>
          </div>
          <template v-if="related.length">
            <h2>同分类文章</h2>
            <ul class="related-list">
              <li v-for="item in related" :key="item.slug">
                <router-link :to="`/articles/${item.slug}`">{{ item.title }}</router-link>
                <p v-if="item.excerpt">{{ item.excerpt }}</p>
              </li>
            </ul>
          </template>
          <template v-if="relatedSites.length">
            <h2>相关导航项目</h2>
            <ul class="related-list">
              <li v-for="site in relatedSites" :key="site.id">
                <router-link :to="`/project/${site.id}`">{{ site.name }}</router-link>
                <p v-if="site.description">{{ site.description }}</p>
              </li>
            </ul>
          </template>
        </aside>
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
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { marked } from 'marked'
import type { Article } from '../types'
import { fetchArticleBySlug } from '../api/articles'
import { useMeta } from '../composables/useMeta'

const route = useRoute()
const article = ref<Article | null>(null)
const related = ref<Article[]>([])
const relatedSites = ref<NonNullable<Article['related_sites']>>([])
const loading = ref(true)
const copied = ref(false)

const renderedContent = computed(() => {
  if (!article.value?.content) return ''
  return marked.parse(article.value.content, { async: false }) as string
})

const slug = computed(() => String(route.params.slug || ''))
const pageUrl = computed(() => `https://xuaweb3.com/articles/${article.value?.slug || slug.value}`)
const twitterShare = computed(() =>
  `https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl.value)}&text=${encodeURIComponent(article.value?.title || '')}`
)
const weiboShare = computed(() =>
  `https://service.weibo.com/share/share.php?url=${encodeURIComponent(pageUrl.value)}&title=${encodeURIComponent(article.value?.title || '')}`
)

useMeta(() => {
  if (!article.value) {
    return {
      title: loading.value ? '文章加载中 - 玄猫Web3' : '文章不存在 - 玄猫Web3',
      description: '玄猫Web3 Web3 行业资讯与深度分析',
      canonical: `https://xuaweb3.com/articles/${slug.value}`,
      ogType: 'article',
      ogImage: 'https://xuaweb3.com/og/default.svg',
    }
  }
  const a = article.value
  const description = (a.excerpt || a.content || '').replace(/\s+/g, ' ').trim().slice(0, 160)
  const ogImage = a.featured_image || 'https://xuaweb3.com/og/default.svg'
  return {
    title: `${a.title} - 玄猫Web3`,
    description,
    canonical: `https://xuaweb3.com/articles/${a.slug}`,
    ogTitle: a.title,
    ogDescription: description,
    ogType: 'article',
    ogImage,
    jsonLd: [
      {
        '@context': 'https://schema.org',
        '@type': 'NewsArticle',
        headline: a.title,
        description,
        image: ogImage,
        datePublished: a.published_at,
        author: { '@type': 'Person', name: a.author_name || '玄猫Web3' },
        mainEntityOfPage: `https://xuaweb3.com/articles/${a.slug}`,
      },
    ],
  }
})

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric' })
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(pageUrl.value)
    copied.value = true
    window.setTimeout(() => { copied.value = false }, 2000)
  } catch {
    copied.value = false
  }
}

watch(slug, async (next) => {
  if (!next) return
  loading.value = true
  copied.value = false
  try {
    const data = await fetchArticleBySlug(next)
    article.value = data
    related.value = data.related || []
    relatedSites.value = data.related_sites || []
  } catch {
    article.value = null
    related.value = []
    relatedSites.value = []
  } finally {
    loading.value = false
  }
}, { immediate: true })
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

.article-cover {
  display: block;
  width: 100%;
  height: auto;
  margin-top: 20px;
  border-radius: var(--radius-sm);
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

.article-cta {
  margin-top: 40px;
  padding: 20px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.03);

  h2 {
    font-size: 16px;
    margin: 20px 0 8px;
  }
}

.disclosure {
  color: var(--text-secondary);
  font-size: 13px;
  line-height: 1.7;
  margin: 0 0 16px;
}

.cta-row, .share-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
  margin-bottom: 12px;
}

.share-row {
  font-size: 13px;
  color: var(--text-secondary);

  button, a {
    color: var(--neon-blue);
    background: none;
    border: 0;
    padding: 0;
    font: inherit;
    cursor: pointer;
    text-decoration: none;
    &:hover { color: var(--neon-purple); }
  }
}

.related-list {
  list-style: none;
  padding: 0;
  margin: 0;

  li {
    margin-bottom: 10px;
  }

  a {
    color: var(--text-primary);
    text-decoration: none;
    &:hover { color: var(--neon-blue); }
  }

  p {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--text-tertiary);
  }
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
