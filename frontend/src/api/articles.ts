import client from './client'
import type { Article, ArticleListResponse } from '../types'

export async function fetchArticles(page = 1, perPage = 12): Promise<ArticleListResponse> {
  const res = await client.get('/articles', {
    params: { status: 'published', page, per_page: perPage }
  })
  return res.data
}

export async function fetchArticleBySlug(slug: string): Promise<Article> {
  const res = await client.get(`/articles/by-slug/${slug}`)
  return res.data.data
}

export async function fetchArticleById(id: number): Promise<Article> {
  const res = await client.get(`/articles/${id}`)
  return res.data.data
}
