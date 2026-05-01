export interface Site {
  name: string
  url: string
  description: string
  icon?: string
  is_recommended?: boolean
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
