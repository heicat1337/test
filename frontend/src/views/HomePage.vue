<template>
  <div class="home-layout">
    <AppSidebar v-if="categories.length" :categories="filtered" :active-id="activeId" />
    <div v-else class="sidebar-skeleton" aria-hidden="true">
      <div v-for="i in 6" :key="i" class="sidebar-skel-item"></div>
    </div>
    <AppContent
      :categories="filtered"
      :loading="loading && !categories.length"
      :recommended="recommendedSites"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch, nextTick } from 'vue'
import AppSidebar from '../components/AppSidebar.vue'
import AppContent from '../components/AppContent.vue'
import { useNavCategories } from '../composables/useNavCategories'
import { useSearch } from '../composables/useSearch'
import { useActiveCategory } from '../composables/useActiveCategory'
import { useMeta } from '../composables/useMeta'

const { categories, loading, load } = useNavCategories()
const { filtered } = useSearch(categories)
const { activeId, refresh: refreshObserver } = useActiveCategory()

const recommendedSites = computed(() =>
  filtered.value.flatMap(c => c.sites).filter(s => s.is_recommended)
)

useMeta(() => ({
  title: '玄猫Web3 - Web3 行业资讯与导航平台',
  description: '玄猫Web3是专业的Web3行业资讯与导航平台，提供区块链、DeFi、NFT、加密货币、交易所、钱包、L2、跨链桥等领域的最新动态、深度分析和项目评测。',
  canonical: 'https://xuaweb3.com/',
  ogType: 'website',
  jsonLd: [
    {
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      name: '玄猫Web3',
      url: 'https://xuaweb3.com/',
      description: '专业的 Web3 行业资讯与导航平台',
      potentialAction: {
        '@type': 'SearchAction',
        target: 'https://xuaweb3.com/?q={search_term_string}',
        'query-input': 'required name=search_term_string',
      },
    },
    {
      '@context': 'https://schema.org',
      '@type': 'CollectionPage',
      name: '玄猫Web3 导航',
      url: 'https://xuaweb3.com/',
      hasPart: categories.value.map(c => ({
        '@type': 'ItemList',
        name: c.name,
        url: `https://xuaweb3.com/c/${c.slug || c.id}`,
        numberOfItems: c.sites.length,
      })),
    },
  ],
}))

watch([filtered, loading], () => {
  nextTick(refreshObserver)
}, { flush: 'post' })

onMounted(load)
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.home-layout {
  display: flex;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

.sidebar-skeleton {
  width: var(--sidebar-width);
  flex-shrink: 0;
  padding: 8px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  gap: 6px;

  .sidebar-skel-item {
    height: 36px;
    border-radius: var(--radius-sm);
    background: linear-gradient(
      90deg,
      rgba(255, 255, 255, 0.04) 0%,
      rgba(255, 255, 255, 0.08) 50%,
      rgba(255, 255, 255, 0.04) 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.4s ease-in-out infinite;
  }
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@include responsive(mobile) {
  .home-layout {
    flex-direction: column;
    gap: 0;
    padding: 16px;
  }
  .sidebar-skeleton { display: none; }
}
</style>
