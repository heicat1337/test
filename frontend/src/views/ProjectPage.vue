<template>
  <div class="project-page">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <router-link to="/">首页</router-link>
      <span class="separator">›</span>
      <router-link v-if="site?.category" :to="`/c/${site.category.slug}`">{{ site.category.name }}</router-link>
      <template v-if="site?.category">
        <span class="separator">›</span>
      </template>
      <span class="current">{{ site?.name || '项目详情' }}</span>
    </nav>

    <div v-if="loading" class="loading">
      <div class="skel skel-shot"></div>
      <div class="skel-info">
        <div class="skel skel-line skel-title"></div>
        <div class="skel skel-line"></div>
        <div class="skel skel-line skel-short"></div>
      </div>
    </div>

    <div v-else-if="!site" class="empty">
      <span class="empty-icon">🔍</span>
      <p>未找到该项目</p>
      <router-link to="/" class="back-home">返回首页</router-link>
    </div>

    <article v-else class="project-layout">
      <section class="shot-col">
        <div class="shot-frame">
          <img
            v-if="screenshotSrc"
            :src="screenshotSrc"
            :alt="`${site.name} 网站截图`"
            class="shot-img"
            loading="eager"
            @error="onShotError"
          />
          <div v-else class="shot-fallback">
            <span class="shot-fallback-icon">{{ site.icon || '🌐' }}</span>
            <span class="shot-fallback-text">截图暂未生成</span>
          </div>
        </div>
        <a
          :href="site.url"
          target="_blank"
          rel="noopener noreferrer"
          class="cta-btn"
          @click="onVisit"
        >
          访问官网
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M7 17 17 7M7 7h10v10" />
          </svg>
        </a>
      </section>

      <section class="info-col">
        <header class="info-header">
          <span class="info-icon">{{ site.icon || '🌐' }}</span>
          <div class="info-title-wrap">
            <h1 class="info-title">{{ site.name }}</h1>
            <a class="info-url" :href="site.url" target="_blank" rel="noopener noreferrer">{{ shortUrl }}</a>
          </div>
          <span v-if="site.is_recommended" class="info-flag">★ 推荐</span>
        </header>

        <p class="info-desc">{{ site.description || '暂无项目简介。' }}</p>

        <div v-if="rating > 0" class="meta-row">
          <span class="meta-label">评分</span>
          <span class="rating">
            <span class="rating-stars" :style="{ '--fill': `${(rating / 5) * 100}%` }" aria-hidden="true">★★★★★</span>
            <span class="rating-num">{{ rating.toFixed(1) }}</span>
          </span>
        </div>

        <div v-if="tags.length" class="meta-row">
          <span class="meta-label">标签</span>
          <span class="tags">
            <span v-for="tag in tags" :key="tag" class="tag">#{{ tag }}</span>
          </span>
        </div>

        <div v-if="socialEntries.length" class="meta-row">
          <span class="meta-label">社交</span>
          <span class="socials">
            <a
              v-for="entry in socialEntries"
              :key="entry.key"
              :href="entry.url"
              target="_blank"
              rel="noopener noreferrer"
              :title="entry.label"
              class="social-link"
            >
              <span class="social-icon">{{ entry.icon }}</span>
              <span class="social-label">{{ entry.label }}</span>
            </a>
          </span>
        </div>
      </section>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import type { SiteDetail } from '../types'
import { fetchSiteById } from '../api/nav'
import { useMeta } from '../composables/useMeta'
import { useFavorites } from '../composables/useFavorites'

const route = useRoute()
const site = ref<SiteDetail | null>(null)
const loading = ref(true)
const shotErrored = ref(false)
const { trackVisit } = useFavorites()
let inflight: AbortController | null = null

const id = computed(() => String(route.params.id || ''))

const rating = computed(() => Number(site.value?.rating || 0))
const tags = computed(() => site.value?.tags || [])

const SOCIAL_META: Record<string, { label: string; icon: string }> = {
  twitter: { label: 'Twitter / X', icon: '𝕏' },
  x: { label: 'Twitter / X', icon: '𝕏' },
  discord: { label: 'Discord', icon: '💬' },
  telegram: { label: 'Telegram', icon: '✈️' },
  github: { label: 'GitHub', icon: '🐙' },
  medium: { label: 'Medium', icon: 'M' },
  youtube: { label: 'YouTube', icon: '▶' },
  reddit: { label: 'Reddit', icon: '🤖' },
  linkedin: { label: 'LinkedIn', icon: 'in' },
  docs: { label: '文档', icon: '📖' },
  blog: { label: '博客', icon: '📝' },
}

const socialEntries = computed(() => {
  const links = site.value?.social_links
  if (!links || typeof links !== 'object') return []
  return Object.entries(links)
    .filter(([, url]) => typeof url === 'string' && url.trim() !== '')
    .map(([key, url]) => {
      const meta = SOCIAL_META[key.toLowerCase()] || { label: key, icon: '🔗' }
      return { key, url: String(url), ...meta }
    })
})

const shortUrl = computed(() => {
  try {
    const u = new URL(site.value!.url)
    return u.host + (u.pathname === '/' ? '' : u.pathname)
  } catch {
    return site.value?.url || ''
  }
})

const screenshotSrc = computed(() => {
  if (!site.value?.url) return ''
  if (shotErrored.value) return ''
  if (site.value.screenshot_url) return site.value.screenshot_url
  // mshots 是 WordPress 的免费截图服务，第一次访问可能慢但后续 CDN 缓存
  return `https://s.wordpress.com/mshots/v1/${encodeURIComponent(site.value.url)}?w=900&h=600`
})

function onShotError() {
  shotErrored.value = true
}

function onVisit() {
  if (site.value?.url) trackVisit(site.value.url)
}

async function load(): Promise<void> {
  inflight?.abort()
  inflight = new AbortController()
  loading.value = true
  shotErrored.value = false
  try {
    site.value = await fetchSiteById(id.value, inflight.signal)
  } catch (err: any) {
    if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return
    site.value = null
  } finally {
    loading.value = false
  }
}

useMeta(() => {
  if (!site.value) {
    return {
      title: '项目详情 - 玄猫Web3',
      description: '正在加载项目详情...',
      canonical: `https://xuaweb3.com/project/${id.value}`,
    }
  }
  const s = site.value
  const tagPart = s.tags?.length ? '，标签：' + s.tags.join('、') : ''
  const description = `${s.name}${s.category ? '（' + s.category.name + '分类）' : ''} - ${s.description || 'Web3 项目'}${tagPart}。在玄猫Web3 一键访问官网。`
  const canonical = `https://xuaweb3.com/project/${s.id}`
  const breadcrumb = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: '首页', item: 'https://xuaweb3.com/' },
      ...(s.category ? [{ '@type': 'ListItem', position: 2, name: s.category.name, item: `https://xuaweb3.com/c/${s.category.slug}` }] : []),
      { '@type': 'ListItem', position: s.category ? 3 : 2, name: s.name, item: canonical },
    ],
  }
  const product = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: s.name,
    url: s.url,
    description: s.description,
    ...(s.rating && s.rating > 0
      ? {
          aggregateRating: {
            '@type': 'AggregateRating',
            ratingValue: s.rating,
            bestRating: 5,
            ratingCount: 1,
          },
        }
      : {}),
  }
  return {
    title: `${s.name} | 玄猫Web3`,
    description,
    canonical,
    ogType: 'website',
    jsonLd: [breadcrumb, product],
  }
})

watch(id, load, { immediate: false })
onMounted(load)
onUnmounted(() => inflight?.abort())
</script>

<style scoped lang="scss">
@use '../styles/mixins' as *;

.project-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
}

.breadcrumb {
  font-size: 13px;
  color: var(--text-tertiary);
  margin-bottom: 20px;
  a {
    color: var(--text-secondary);
    text-decoration: none;
    &:hover { color: var(--neon-blue); }
  }
  .separator { margin: 0 8px; }
  .current { color: var(--text-primary); }
}

.project-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  gap: 32px;
  align-items: start;
}

.shot-col { display: flex; flex-direction: column; gap: 16px; }

.shot-frame {
  position: relative;
  width: 100%;
  aspect-ratio: 3 / 2;
  border-radius: var(--radius-lg);
  overflow: hidden;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
}

.shot-img { width: 100%; height: 100%; object-fit: cover; display: block; }

.shot-fallback {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 12px; color: var(--text-tertiary);
  .shot-fallback-icon { font-size: 64px; }
  .shot-fallback-text { font-size: 13px; }
}

.cta-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px 20px;
  border-radius: var(--card-radius);
  background: var(--gradient-primary);
  color: #fff;
  font-weight: 600;
  font-size: 15px;
  text-decoration: none;
  transition: all var(--transition-base);
  box-shadow: 0 4px 24px rgba(0, 212, 255, 0.25);

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0, 212, 255, 0.4);
  }

  svg { width: 18px; height: 18px; }
}

.info-col { display: flex; flex-direction: column; gap: 20px; }

.info-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border-color);
}

.info-icon {
  flex-shrink: 0;
  width: 64px;
  height: 64px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  border-radius: 16px;
  background: rgba(0, 212, 255, 0.08);
  border: 1px solid rgba(0, 212, 255, 0.2);
}

.info-title-wrap { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }

.info-title {
  font-family: var(--font-display);
  font-size: 32px;
  font-weight: 700;
  margin: 0;
  letter-spacing: -0.02em;
  background: var(--gradient-accent);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  word-break: break-word;
}

.info-url {
  font-size: 13px;
  color: var(--text-tertiary);
  text-decoration: none;
  word-break: break-all;
  &:hover { color: var(--neon-blue); }
}

.info-flag {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--neon-green);
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(0, 255, 136, 0.08);
  border: 1px solid rgba(0, 255, 136, 0.25);
  white-space: nowrap;
}

.info-desc {
  color: var(--text-secondary);
  font-size: 15px;
  line-height: 1.7;
  margin: 0;
}

.meta-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 14px;
}

.meta-label {
  flex-shrink: 0;
  width: 56px;
  color: var(--text-tertiary);
  font-size: 13px;
  padding-top: 4px;
}

.rating {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.rating-stars {
  position: relative;
  display: inline-block;
  font-size: 18px;
  letter-spacing: 2px;
  color: rgba(255, 255, 255, 0.12);
  background: linear-gradient(90deg, #ffb84d var(--fill, 0%), rgba(255, 255, 255, 0.12) var(--fill, 0%));
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
.rating-num { color: var(--text-primary); font-weight: 600; }

.tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tag {
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.25);
  color: #c4b5fd;
}

.socials { display: flex; flex-wrap: wrap; gap: 8px; }
.social-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 13px;
  transition: all var(--transition-fast);

  &:hover {
    color: var(--neon-blue);
    border-color: var(--border-glow);
    transform: translateY(-1px);
  }
}
.social-icon { font-size: 14px; line-height: 1; }
.social-label { font-weight: 500; }

.loading {
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  gap: 32px;
}
.skel {
  background: linear-gradient(90deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.04) 100%);
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
  border-radius: var(--radius-lg);
}
.skel-shot { aspect-ratio: 3 / 2; }
.skel-info { display: flex; flex-direction: column; gap: 14px; padding-top: 8px; }
.skel-line { height: 16px; border-radius: 6px; }
.skel-title { height: 32px; width: 60%; }
.skel-short { width: 40%; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
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

@include responsive(tablet) {
  .project-layout, .loading { grid-template-columns: 1fr; }
  .shot-col { max-width: 100%; }
}

@include responsive(mobile) {
  .project-page { padding: 16px; }
  .info-title { font-size: 26px; }
  .info-icon { width: 52px; height: 52px; font-size: 30px; }
  .meta-label { width: 48px; }
}
</style>
