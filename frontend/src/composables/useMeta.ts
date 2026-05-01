import { watchEffect, onUnmounted } from 'vue'

export interface MetaConfig {
  title?: string
  description?: string
  canonical?: string
  ogTitle?: string
  ogDescription?: string
  ogType?: string
  ogImage?: string
  jsonLd?: Record<string, any> | Record<string, any>[]
}

const DEFAULT_TITLE = '玄猫Web3 - Web3 行业资讯与导航平台'
const DEFAULT_DESCRIPTION = '玄猫Web3是专业的Web3行业资讯与导航平台，提供区块链、DeFi、NFT、加密货币最新动态、深度分析和项目评测。'

function setOrCreateMeta(attr: 'name' | 'property', key: string, value: string) {
  let el = document.head.querySelector<HTMLMetaElement>(`meta[${attr}="${key}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }
  el.setAttribute('content', value)
}

function setOrCreateLink(rel: string, href: string) {
  let el = document.head.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`)
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', rel)
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

function setJsonLd(blocks: Record<string, any>[]) {
  // 移除旧 JSON-LD（仅本 composable 设置的，由 data-managed 标记区分）
  document.head
    .querySelectorAll<HTMLScriptElement>('script[type="application/ld+json"][data-managed="useMeta"]')
    .forEach(el => el.remove())

  for (const block of blocks) {
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.setAttribute('data-managed', 'useMeta')
    script.textContent = JSON.stringify(block)
    document.head.appendChild(script)
  }
}

export function useMeta(getter: () => MetaConfig) {
  const stop = watchEffect(() => {
    const cfg = getter()
    const title = cfg.title || DEFAULT_TITLE
    const description = cfg.description || DEFAULT_DESCRIPTION

    document.title = title
    setOrCreateMeta('name', 'description', description)

    if (cfg.canonical) {
      setOrCreateLink('canonical', cfg.canonical)
    }

    setOrCreateMeta('property', 'og:title', cfg.ogTitle || title)
    setOrCreateMeta('property', 'og:description', cfg.ogDescription || description)
    setOrCreateMeta('property', 'og:type', cfg.ogType || 'website')
    if (cfg.ogImage) setOrCreateMeta('property', 'og:image', cfg.ogImage)
    setOrCreateMeta('name', 'twitter:card', 'summary_large_image')

    if (cfg.jsonLd) {
      const blocks = Array.isArray(cfg.jsonLd) ? cfg.jsonLd : [cfg.jsonLd]
      setJsonLd(blocks)
    } else {
      setJsonLd([])
    }
  })

  onUnmounted(() => {
    stop()
    // 离开页面时清理 JSON-LD，避免残留影响下一个页面爬取
    setJsonLd([])
  })
}
