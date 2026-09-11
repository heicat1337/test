<template>
  <div class="legal-page">
    <h1>{{ copy.title }}</h1>
    <p v-for="(p, i) in copy.paragraphs" :key="i">{{ p }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useMeta } from '../composables/useMeta'

const pages: Record<string, { title: string; description: string; paragraphs: string[] }> = {
  about: {
    title: '关于我们',
    description: '玄猫Web3 是面向中文用户的 Web3 导航与资讯站点。',
    paragraphs: [
      '玄猫Web3（xuaweb3.com）提供 Web3 项目导航、项目资料与行业资讯，帮助读者更快找到交易所、DeFi、钱包、L2 与安全工具。',
      '导航条目由编辑人工整理并持续更新；资讯内容包含编辑撰稿与经审核的 AI 辅助稿，发布前需核对事实、标题与摘要。',
      '本站不提供投资建议，亦不托管数字资产。访问外部协议或交易所前，请自行核验域名与合约地址。',
    ],
  },
  contact: {
    title: '联系我们',
    description: '通过邮箱联系玄猫Web3 编辑团队。',
    paragraphs: [
      '内容纠错、导航更新或合作请发送邮件至 hello@xuaweb3.com，并注明页面 URL 与具体问题。',
      '我们通常在 5 个工作日内回复。请勿在邮件中发送助记词、私钥或验证码。',
      '如需下架或版权沟通，请使用相同邮箱并附权属说明。',
    ],
  },
  privacy: {
    title: '隐私政策',
    description: '玄猫Web3 隐私政策。',
    paragraphs: [
      '访问本站时，服务器可能记录 IP、User-Agent 与请求路径，用于故障排查、防滥用与访问统计。',
      '本地收藏/访问次数保存在你的浏览器 localStorage，不会上传到我们的服务器。',
      '我们可能使用匿名化流量统计。本站不出售个人数据。如使用 Cloudflare 等 CDN，其日志政策以其官网为准。',
      '外链跳转至第三方站点后，适用该站自己的隐私政策。',
    ],
  },
  terms: {
    title: '使用条款',
    description: '玄猫Web3 使用条款：内容仅供参考，不构成投资建议。',
    paragraphs: [
      '本站内容仅供信息参考，不构成投资、法律或税务建议。数字资产存在损失本金的风险。',
      '部分外链可能包含推荐/联盟参数。我们会在相关页面标明推广披露；你仍应独立判断。',
      '禁止利用本站从事非法活动、批量抓取未授权数据或干扰服务。我们可限制滥用访问。',
      '本条款自发布之日起生效，更新后以本页最新文本为准。',
    ],
  },
}

const route = useRoute()
const copy = computed(() => pages[String(route.name)] || pages.about)

useMeta(() => ({
  title: `${copy.value.title} - 玄猫Web3`,
  description: copy.value.description,
  canonical: `https://xuaweb3.com/${String(route.name)}`,
}))
</script>

<style scoped lang="scss">
.legal-page {
  max-width: 760px;
  margin: 0 auto;
  padding: 32px 24px 64px;
  h1 { font-size: 28px; margin-bottom: 20px; }
  p { color: var(--text-secondary); line-height: 1.8; margin-bottom: 16px; }
}
</style>
