import type { Category } from '../types'

export const categories: Category[] = [
  {
    id: 'cex',
    name: '交易所',
    icon: '🏛️',
    sites: [
      { name: 'Binance', url: 'https://binance.com', description: '全球最大加密货币交易所，交易量第一', icon: '🟡' },
      { name: 'OKX', url: 'https://okx.com', description: '全球领先交易所，Web3 钱包生态', icon: '⚫' },
      { name: 'Coinbase', url: 'https://coinbase.com', description: '美国合规上市交易所（NASDAQ: COIN）', icon: '🔵' },
      { name: 'Bybit', url: 'https://bybit.com', description: '衍生品交易领先平台，合约交易', icon: '🟠' },
      { name: 'Bitget', url: 'https://bitget.com', description: '跟单交易平台，社交化交易', icon: '🔷' },
      { name: 'Gate.io', url: 'https://gate.io', description: '老牌交易所，上币种类丰富', icon: '🚪' },
      { name: 'KuCoin', url: 'https://kucoin.com', description: '小币种丰富的交易所', icon: '🟢' },
      { name: 'HTX', url: 'https://htx.com', description: '火必（原火币），亚洲老牌交易所', icon: '🔥' },
      { name: 'Kraken', url: 'https://kraken.com', description: '欧美合规交易所，安全性强', icon: '🐙' },
      { name: 'MEXC', url: 'https://mexc.com', description: '新币上线速度快，零 Maker 手续费', icon: 'Ⓜ️' },
    ]
  },
  {
    id: 'defi',
    name: 'DeFi',
    icon: '🏦',
    sites: [
      { name: 'Uniswap', url: 'https://app.uniswap.org', description: '最大的去中心化交易所，支持多链代币兑换', icon: '🦄' },
      { name: 'Aave', url: 'https://aave.com', description: '去中心化借贷协议，支持闪电贷', icon: '👻' },
      { name: 'Compound', url: 'https://compound.finance', description: '算法驱动的去中心化借贷市场', icon: '🏛️' },
      { name: 'MakerDAO', url: 'https://makerdao.com', description: 'DAI 稳定币发行协议，DeFi 基石', icon: '🏗️' },
      { name: 'Curve', url: 'https://curve.fi', description: '稳定币和同类资产低滑点兑换', icon: '🌀' },
      { name: 'Lido', url: 'https://lido.fi', description: '最大的流动性质押协议，支持 ETH 质押', icon: '🌊' },
      { name: '1inch', url: 'https://1inch.io', description: 'DEX 聚合器，自动寻找最优交易路径', icon: '🦊' },
      { name: 'Pendle', url: 'https://pendle.finance', description: '收益代币化协议，交易未来收益', icon: '📊' },
      { name: 'EigenLayer', url: 'https://eigenlayer.xyz', description: 'ETH 再质押协议，共享安全性', icon: '🔄' },
      { name: 'Ethena', url: 'https://ethena.fi', description: '合成美元协议 USDe，Delta 中性策略', icon: '💎' },
    ]
  },
  {
    id: 'dex',
    name: 'DEX',
    icon: '🔄',
    sites: [
      { name: 'Jupiter', url: 'https://jup.ag', description: 'Solana 上最大的 DEX 聚合器', icon: '🪐' },
      { name: 'PancakeSwap', url: 'https://pancakeswap.finance', description: 'BNB Chain 上最大的 DEX', icon: '🥞' },
      { name: 'SushiSwap', url: 'https://sushi.com', description: '多链 DEX，支持 Trident AMM', icon: '🍣' },
      { name: 'Raydium', url: 'https://raydium.io', description: 'Solana AMM，集成 Serum 订单簿', icon: '☀️' },
      { name: 'Trader Joe', url: 'https://traderjoexyz.com', description: 'Avalanche 上的 DEX，Liquidity Book 模型', icon: '🏔️' },
      { name: 'Orca', url: 'https://orca.so', description: 'Solana 上用户友好的 DEX', icon: '🐋' },
      { name: 'Balancer', url: 'https://balancer.fi', description: '可编程流动性协议，加权池', icon: '⚖️' },
      { name: 'Hyperliquid', url: 'https://hyperliquid.xyz', description: '去中心化永续合约交易所，链上订单簿', icon: '⚡' },
    ]
  },
  {
    id: 'nft',
    name: 'NFT',
    icon: '🎨',
    sites: [
      { name: 'OpenSea', url: 'https://opensea.io', description: '最大的 NFT 交易市场', icon: '🌊' },
      { name: 'Blur', url: 'https://blur.io', description: '专业 NFT 交易平台，零手续费', icon: '💨' },
      { name: 'Magic Eden', url: 'https://magiceden.io', description: '多链 NFT 市场，支持 BTC Ordinals', icon: '🧙' },
      { name: 'Zora', url: 'https://zora.co', description: '创作者友好的 NFT 铸造和交易平台', icon: '✨' },
      { name: 'Foundation', url: 'https://foundation.app', description: '高品质数字艺术 NFT 平台', icon: '🎭' },
      { name: 'Tensor', url: 'https://tensor.trade', description: 'Solana NFT 专业交易平台', icon: '📐' },
    ]
  },
  {
    id: 'wallets',
    name: '钱包',
    icon: '👛',
    sites: [
      { name: 'MetaMask', url: 'https://metamask.io', description: '最流行的以太坊浏览器钱包', icon: '🦊' },
      { name: 'Phantom', url: 'https://phantom.app', description: 'Solana 生态首选钱包，支持多链', icon: '👻' },
      { name: 'Rainbow', url: 'https://rainbow.me', description: '设计精美的以太坊钱包', icon: '🌈' },
      { name: 'Rabby', url: 'https://rabby.io', description: '安全至上的多链钱包，交易预检', icon: '🐰' },
      { name: 'OKX Wallet', url: 'https://okx.com/web3', description: 'OKX Web3 钱包，支持 70+ 链', icon: '⭕' },
      { name: 'Ledger', url: 'https://ledger.com', description: '最受信赖的硬件钱包', icon: '🔐' },
      { name: 'Backpack', url: 'https://backpack.app', description: 'Solana 生态钱包，支持 xNFT', icon: '🎒' },
      { name: 'Safe', url: 'https://safe.global', description: '多签钱包，机构级资产管理', icon: '🏰' },
    ]
  },
  {
    id: 'l2',
    name: 'L2 & 扩容',
    icon: '🚀',
    sites: [
      { name: 'Arbitrum', url: 'https://arbitrum.io', description: 'Optimistic Rollup 领军者，最大 L2', icon: '🔵' },
      { name: 'Optimism', url: 'https://optimism.io', description: 'OP Stack 生态，Superchain 愿景', icon: '🔴' },
      { name: 'Base', url: 'https://base.org', description: 'Coinbase 推出的 L2，OP Stack 构建', icon: '🔷' },
      { name: 'zkSync', url: 'https://zksync.io', description: 'ZK Rollup，原生账户抽象', icon: '🟣' },
      { name: 'StarkNet', url: 'https://starknet.io', description: 'ZK-STARK 扩容方案，Cairo 语言', icon: '⭐' },
      { name: 'Scroll', url: 'https://scroll.io', description: 'zkEVM L2，字节码级兼容', icon: '📜' },
      { name: 'Linea', url: 'https://linea.build', description: 'ConsenSys 推出的 zkEVM L2', icon: '📐' },
      { name: 'Polygon', url: 'https://polygon.technology', description: 'AggLayer 聚合层，多种扩容方案', icon: '💜' },
    ]
  },
  {
    id: 'bridges',
    name: '跨链桥',
    icon: '🌉',
    sites: [
      { name: 'Across', url: 'https://across.to', description: '基于意图的跨链桥，速度快费用低', icon: '🌀' },
      { name: 'Stargate', url: 'https://stargate.finance', description: 'LayerZero 跨链桥，原生资产转移', icon: '🌟' },
      { name: 'Hop Protocol', url: 'https://hop.exchange', description: 'L2 到 L2 快速跨链桥', icon: '🐰' },
      { name: 'Orbiter Finance', url: 'https://orbiter.finance', description: '去中心化跨 Rollup 桥', icon: '🛸' },
      { name: 'LayerZero', url: 'https://layerzero.network', description: '全链互操作性协议', icon: '0️⃣' },
      { name: 'Wormhole', url: 'https://wormhole.com', description: '多链消息传递协议', icon: '🕳️' },
    ]
  },
  {
    id: 'analytics',
    name: '数据分析',
    icon: '📊',
    sites: [
      { name: 'Dune', url: 'https://dune.com', description: '链上数据查询和可视化平台', icon: '📈' },
      { name: 'DefiLlama', url: 'https://defillama.com', description: 'DeFi TVL 追踪和数据聚合', icon: '🦙' },
      { name: 'Nansen', url: 'https://nansen.ai', description: '链上分析平台，聪明钱追踪', icon: '🧠' },
      { name: 'Arkham', url: 'https://arkham.ai', description: '链上情报平台，实体标签', icon: '🕵️' },
      { name: 'Token Terminal', url: 'https://tokenterminal.com', description: '协议财务数据和链上指标', icon: '💹' },
      { name: 'Etherscan', url: 'https://etherscan.io', description: '以太坊区块浏览器', icon: '🔍' },
      { name: 'CoinGecko', url: 'https://coingecko.com', description: '加密货币价格和市值追踪', icon: '🦎' },
      { name: 'L2Beat', url: 'https://l2beat.com', description: 'L2 项目分析和风险评估', icon: '📉' },
    ]
  },
  {
    id: 'dev-tools',
    name: '开发工具',
    icon: '🛠️',
    sites: [
      { name: 'Hardhat', url: 'https://hardhat.org', description: '以太坊开发环境，测试和部署', icon: '⛑️' },
      { name: 'Foundry', url: 'https://getfoundry.sh', description: 'Rust 编写的高速 Solidity 开发工具', icon: '🔨' },
      { name: 'Remix', url: 'https://remix.ethereum.org', description: '浏览器端 Solidity IDE', icon: '💻' },
      { name: 'Alchemy', url: 'https://alchemy.com', description: 'Web3 开发平台，RPC 节点服务', icon: '⚗️' },
      { name: 'Infura', url: 'https://infura.io', description: '以太坊和 IPFS API 基础设施', icon: '🌐' },
      { name: 'The Graph', url: 'https://thegraph.com', description: '去中心化索引协议，链上数据查询', icon: '📡' },
      { name: 'Chainlink', url: 'https://chain.link', description: '去中心化预言机网络', icon: '🔗' },
      { name: 'OpenZeppelin', url: 'https://openzeppelin.com', description: '安全智能合约库和审计工具', icon: '🛡️' },
    ]
  },
  {
    id: 'dao',
    name: 'DAO & 治理',
    icon: '🏛️',
    sites: [
      { name: 'Snapshot', url: 'https://snapshot.org', description: '去中心化投票平台，链下治理', icon: '📸' },
      { name: 'Tally', url: 'https://tally.xyz', description: '链上治理仪表盘和投票', icon: '🗳️' },
      { name: 'Aragon', url: 'https://aragon.org', description: 'DAO 创建和管理框架', icon: '🦅' },
      { name: 'Nouns', url: 'https://nouns.wtf', description: '创新 DAO 治理模型，每日拍卖', icon: '⌐◨-◨' },
      { name: 'DAOhaus', url: 'https://daohaus.club', description: '无代码 DAO 启动平台', icon: '🏠' },
    ]
  },
  {
    id: 'security',
    name: '安全',
    icon: '🔒',
    sites: [
      { name: 'CertiK', url: 'https://certik.com', description: '智能合约审计和安全评分', icon: '✅' },
      { name: 'Immunefi', url: 'https://immunefi.com', description: 'Web3 漏洞赏金平台', icon: '🐛' },
      { name: 'Slither', url: 'https://github.com/crytic/slither', description: 'Solidity 静态分析框架', icon: '🐍' },
      { name: 'Revoke.cash', url: 'https://revoke.cash', description: '查看和撤销代币授权', icon: '🚫' },
      { name: 'De.Fi', url: 'https://de.fi', description: 'DeFi 安全仪表盘，风险扫描', icon: '🛡️' },
      { name: 'Tenderly', url: 'https://tenderly.co', description: '智能合约调试和模拟平台', icon: '🔬' },
    ]
  },
  {
    id: 'news',
    name: '新闻资讯',
    icon: '📰',
    sites: [
      { name: 'The Block', url: 'https://theblock.co', description: '加密行业深度研究和新闻', icon: '📋' },
      { name: 'Bankless', url: 'https://bankless.com', description: 'DeFi 和 Web3 教育与分析', icon: '🏴' },
      { name: 'CoinDesk', url: 'https://coindesk.com', description: '领先的加密货币新闻媒体', icon: '📰' },
      { name: 'Decrypt', url: 'https://decrypt.co', description: 'Web3 新闻和教育平台', icon: '🔓' },
      { name: 'Foresight News', url: 'https://foresightnews.pro', description: '中文 Web3 深度资讯', icon: '🔭' },
      { name: 'BlockBeats', url: 'https://theblockbeats.info', description: '律动 BlockBeats，中文加密媒体', icon: '🎵' },
    ]
  },
]
