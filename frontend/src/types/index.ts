export interface Site {
  id?: number
  name: string
  url: string
  description: string
  icon?: string
  is_recommended?: boolean
  tags?: string[]
  rating?: number
  social_links?: Record<string, string>
  screenshot_url?: string
  category_id?: number | null
}

export interface SiteDetail extends Site {
  id: number
  category?: {
    id: number
    name: string
    slug: string
    icon: string
  } | null
}

export interface Category {
  id: string
  name: string
  slug?: string
  icon: string
  sites: Site[]
}

export interface Article {
  id: number
  title: string
  slug: string
  excerpt: string
  content: string
  category_name?: string
  author_name?: string
  published_at: string
  view_count: number
  is_featured: boolean
  tags?: string[]
}

export interface ArticleListResponse {
  data: Article[]
  total: number
  page: number
  per_page: number
  total_pages: number
}
