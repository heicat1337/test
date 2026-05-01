<template>
  <div class="category-page">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <router-link to="/">首页</router-link>
      <span class="separator">›</span>
      <span class="current">{{ category?.name || slug }}</span>
    </nav>

    <template v-if="loading">
      <SkeletonLoader />
    </template>

    <template v-else-if="category">
      <header class="category-header">
        <span class="cat-icon">{{ category.icon }}</span>
        <div>
          <h1 class="cat-title">{{ category.name }}</h1>
          <p class="cat-desc">{{ category.sites.length }} 个精选 {{ category.name }} 项目，全部经过人工审核与持续更新。</p>
        </div>
      </header>

      <section class="card-grid">
        <SiteCard v-for="site in category.sites" :key="site.url" :site="site" />
      </section>

      <section v-if="otherCategories.length" class="other-cats">
        <h2 class="other-title">其他分类</h2>
        <div class="other-grid">
          <router-link
            v-for="c in otherCategories"
            :key="c.id"
            :to="`/c/${c.slug || c.id}`"
            class="other-link"
          >
            <span>{{ c.icon }}</span>
            <span>{{ c.name }}</span>
            <span class="other-count">{{ c.sites.length }}</span>
          </router-link>
        </div>
      </section>
    </template>

    <div v-else class="empty">
      <span class="empty-icon">🔍</span>
      <p>未找到分类「{{ slug }}」</p>
      <router-link to="/" class="back-home">返回首页</router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import SiteCard from '../components/SiteCard.vue'
import SkeletonLoader from '../components/SkeletonLoader.vue'
import { useNavCategories } from '../composables/useNavCategories'
import { useMeta } from '../composables/useMeta'
import type { Category } from '../types'

const route = useRoute()
const { categories, loading, load } = useNavCategories()

const slug = computed(() => String(route.params.slug || ''))

const category = computed<Category | undefined>(() =>
  categories.value.find(c => (c.slug || c.id) === slug.value)
)

const otherCategories = computed(() =>
  categories.value.filter(c => (c.slug || c.id) !== slug.value).slice(0, 12)
)

const canonical = computed(() => `https://xuaweb3.com/c/${slug.value}`)

useMeta(() => {
  if (!category.value) {
    return {
      title: `分类 ${slug.value} - 玄猫Web3`,
      description: '正在加载分类内容...',
      canonical: canonical.value,
    }
  }
  const c = category.value
  const sampleNames = c.sites.slice(0, 5).map(s => s.name).join('、')
  const description = `${c.name}分类下精选 ${c.sites.length} 个 Web3 项目${sampleNames ? '，包含 ' + sampleNames : ''}。玄猫Web3 持续更新，覆盖区块链全生态。`

  const breadcrumb = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: '首页', item: 'https://xuaweb3.com/' },
      { '@type': 'ListItem', position: 2, name: c.name, item: canonical.value },
    ],
  }

  const itemList = {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name: `${c.name} - Web3 工具与项目`,
    itemListElement: c.sites.map((s, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      url: s.url,
      name: s.name,
      description: s.description,
    })),
  }

  return {
    title: `${c.name} | 玄猫Web3 导航`,
    description,
    canonical: canonical.value,
    ogType: 'website',
    jsonLd: [breadcrumb, itemList],
  }
})

onMounted(load)
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.category-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

.breadcrumb {
  font-size: 13px;
  color: var(--text-tertiary);
  margin-bottom: 16px;

  a {
    color: var(--text-secondary);
    text-decoration: none;
    &:hover { color: var(--neon-blue); }
  }

  .separator { margin: 0 8px; color: var(--text-tertiary); }
  .current { color: var(--text-primary); }
}

.category-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px 0;
  border-bottom: 1px solid var(--border-color);
  margin-bottom: 28px;

  .cat-icon { font-size: 48px; line-height: 1; flex-shrink: 0; }
  .cat-title {
    font-family: var(--font-display);
    font-size: 32px;
    font-weight: 700;
    background: var(--gradient-accent);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0 0 6px;
  }
  .cat-desc {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.6;
    max-width: 720px;
  }
}

.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;
  margin-bottom: 48px;
}

.other-cats { margin-top: 48px; }
.other-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 16px;
}
.other-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 8px;
}
.other-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 14px;
  transition: all var(--transition-fast);

  &:hover {
    border-color: var(--border-glow);
    color: var(--text-primary);
    background: var(--bg-card);
  }

  .other-count {
    margin-left: auto;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
    color: var(--text-tertiary);
  }
}

.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 20px;
  gap: 12px;
  color: var(--text-tertiary);

  .empty-icon { font-size: 48px; }
  .back-home {
    margin-top: 12px;
    color: var(--neon-blue);
    text-decoration: none;
    &:hover { text-decoration: underline; }
  }
}

@include responsive(mobile) {
  .category-page { padding: 16px; }
  .card-grid { grid-template-columns: 1fr; }
  .category-header .cat-title { font-size: 24px; }
  .category-header .cat-icon { font-size: 36px; }
}
</style>
