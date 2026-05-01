import { createRouter, createWebHistory } from 'vue-router'
import HomePage from '../views/HomePage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomePage },
    { path: '/c/:slug', name: 'category', component: () => import('../views/CategoryPage.vue') },
    { path: '/articles', name: 'articles', component: () => import('../views/ArticlesPage.vue') },
    { path: '/articles/:slug', name: 'article-detail', component: () => import('../views/ArticleDetailPage.vue') },
    { path: '/article/:slug', redirect: to => ({ name: 'article-detail', params: { slug: to.params.slug } }) },
  ],
  scrollBehavior(_to, _from, savedPosition) {
    return savedPosition || { top: 0 }
  }
})

export default router
