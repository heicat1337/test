-- Web3 Navigation Tables
-- These tables extend GEOFlow's schema for nav site management

CREATE TABLE IF NOT EXISTS nav_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT '',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS nav_sites (
    id SERIAL PRIMARY KEY,
    category_id INT REFERENCES nav_categories(id) ON DELETE CASCADE,
    name VARCHAR(200) NOT NULL,
    url VARCHAR(500) NOT NULL,
    description TEXT DEFAULT '',
    icon VARCHAR(50) DEFAULT '',
    sort_order INT DEFAULT 0,
    is_recommended BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_nav_sites_category ON nav_sites(category_id);
CREATE INDEX IF NOT EXISTS idx_nav_sites_recommended ON nav_sites(is_recommended) WHERE is_recommended = TRUE;

-- Seed data
INSERT INTO nav_categories (name, icon, sort_order) VALUES
('交易所', '🏛️', 1),
('DeFi', '🏦', 2),
('DEX', '🔄', 3),
('NFT', '🎨', 4),
('钱包', '👛', 5),
('L2 & 扩容', '🚀', 6),
('跨链桥', '🌉', 7),
('数据分析', '📊', 8),
('开发工具', '🛠️', 9),
('DAO & 治理', '🏛️', 10),
('安全', '🔒', 11),
('新闻资讯', '📰', 12);

-- 交易所
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='交易所'), 'Binance', 'https://binance.com', '全球最大加密货币交易所，交易量第一', '🟡', 1),
((SELECT id FROM nav_categories WHERE name='交易所'), 'OKX', 'https://okx.com', '全球领先交易所，Web3 钱包生态', '⚫', 2),
((SELECT id FROM nav_categories WHERE name='交易所'), 'Coinbase', 'https://coinbase.com', '美国合规上市交易所（NASDAQ: COIN）', '🔵', 3),
((SELECT id FROM nav_categories WHERE name='交易所'), 'Bybit', 'https://bybit.com', '衍生品交易领先平台，合约交易', '🟠', 4),
((SELECT id FROM nav_categories WHERE name='交易所'), 'Bitget', 'https://bitget.com', '跟单交易平台，社交化交易', '🔷', 5),
((SELECT id FROM nav_categories WHERE name='交易所'), 'Gate.io', 'https://gate.io', '老牌交易所，上币种类丰富', '🚪', 6),
((SELECT id FROM nav_categories WHERE name='交易所'), 'KuCoin', 'https://kucoin.com', '小币种丰富的交易所', '🟢', 7),
((SELECT id FROM nav_categories WHERE name='交易所'), 'HTX', 'https://htx.com', '火必（原火币），亚洲老牌交易所', '🔥', 8),
((SELECT id FROM nav_categories WHERE name='交易所'), 'Kraken', 'https://kraken.com', '欧美合规交易所，安全性强', '🐙', 9),
((SELECT id FROM nav_categories WHERE name='交易所'), 'MEXC', 'https://mexc.com', '新币上线速度快，零 Maker 手续费', 'Ⓜ️', 10);

-- DeFi
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='DeFi'), 'Uniswap', 'https://app.uniswap.org', '最大的去中心化交易所，支持多链代币兑换', '🦄', 1),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'Aave', 'https://aave.com', '去中心化借贷协议，支持闪电贷', '👻', 2),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'Compound', 'https://compound.finance', '算法驱动的去中心化借贷市场', '🏛️', 3),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'MakerDAO', 'https://makerdao.com', 'DAI 稳定币发行协议，DeFi 基石', '🏗️', 4),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'Curve', 'https://curve.fi', '稳定币和同类资产低滑点兑换', '🌀', 5),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'Lido', 'https://lido.fi', '最大的流动性质押协议，支持 ETH 质押', '🌊', 6),
((SELECT id FROM nav_categories WHERE name='DeFi'), '1inch', 'https://1inch.io', 'DEX 聚合器，自动寻找最优交易路径', '🦊', 7),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'Pendle', 'https://pendle.finance', '收益代币化协议，交易未来收益', '📊', 8),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'EigenLayer', 'https://eigenlayer.xyz', 'ETH 再质押协议，共享安全性', '🔄', 9),
((SELECT id FROM nav_categories WHERE name='DeFi'), 'Ethena', 'https://ethena.fi', '合成美元协议 USDe，Delta 中性策略', '💎', 10);

-- DEX
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='DEX'), 'Jupiter', 'https://jup.ag', 'Solana 上最大的 DEX 聚合器', '🪐', 1),
((SELECT id FROM nav_categories WHERE name='DEX'), 'PancakeSwap', 'https://pancakeswap.finance', 'BNB Chain 上最大的 DEX', '🥞', 2),
((SELECT id FROM nav_categories WHERE name='DEX'), 'SushiSwap', 'https://sushi.com', '多链 DEX，支持 Trident AMM', '🍣', 3),
((SELECT id FROM nav_categories WHERE name='DEX'), 'Raydium', 'https://raydium.io', 'Solana AMM，集成 Serum 订单簿', '☀️', 4),
((SELECT id FROM nav_categories WHERE name='DEX'), 'Trader Joe', 'https://traderjoexyz.com', 'Avalanche 上的 DEX，Liquidity Book 模型', '🏔️', 5),
((SELECT id FROM nav_categories WHERE name='DEX'), 'Orca', 'https://orca.so', 'Solana 上用户友好的 DEX', '🐋', 6),
((SELECT id FROM nav_categories WHERE name='DEX'), 'Balancer', 'https://balancer.fi', '可编程流动性协议，加权池', '⚖️', 7),
((SELECT id FROM nav_categories WHERE name='DEX'), 'Hyperliquid', 'https://hyperliquid.xyz', '去中心化永续合约交易所，链上订单簿', '⚡', 8);

-- NFT
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='NFT'), 'OpenSea', 'https://opensea.io', '最大的 NFT 交易市场', '🌊', 1),
((SELECT id FROM nav_categories WHERE name='NFT'), 'Blur', 'https://blur.io', '专业 NFT 交易平台，零手续费', '💨', 2),
((SELECT id FROM nav_categories WHERE name='NFT'), 'Magic Eden', 'https://magiceden.io', '多链 NFT 市场，支持 BTC Ordinals', '🧙', 3),
((SELECT id FROM nav_categories WHERE name='NFT'), 'Zora', 'https://zora.co', '创作者友好的 NFT 铸造和交易平台', '✨', 4),
((SELECT id FROM nav_categories WHERE name='NFT'), 'Foundation', 'https://foundation.app', '高品质数字艺术 NFT 平台', '🎭', 5),
((SELECT id FROM nav_categories WHERE name='NFT'), 'Tensor', 'https://tensor.trade', 'Solana NFT 专业交易平台', '📐', 6);

-- 钱包
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='钱包'), 'MetaMask', 'https://metamask.io', '最流行的以太坊浏览器钱包', '🦊', 1),
((SELECT id FROM nav_categories WHERE name='钱包'), 'Phantom', 'https://phantom.app', 'Solana 生态首选钱包，支持多链', '👻', 2),
((SELECT id FROM nav_categories WHERE name='钱包'), 'Rainbow', 'https://rainbow.me', '设计精美的以太坊钱包', '🌈', 3),
((SELECT id FROM nav_categories WHERE name='钱包'), 'Rabby', 'https://rabby.io', '安全至上的多链钱包，交易预检', '🐰', 4),
((SELECT id FROM nav_categories WHERE name='钱包'), 'OKX Wallet', 'https://okx.com/web3', 'OKX Web3 钱包，支持 70+ 链', '⭕', 5),
((SELECT id FROM nav_categories WHERE name='钱包'), 'Ledger', 'https://ledger.com', '最受信赖的硬件钱包', '🔐', 6),
((SELECT id FROM nav_categories WHERE name='钱包'), 'Backpack', 'https://backpack.app', 'Solana 生态钱包，支持 xNFT', '🎒', 7),
((SELECT id FROM nav_categories WHERE name='钱包'), 'Safe', 'https://safe.global', '多签钱包，机构级资产管理', '🏰', 8);

-- L2 & 扩容
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'Arbitrum', 'https://arbitrum.io', 'Optimistic Rollup 领军者，最大 L2', '🔵', 1),
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'Optimism', 'https://optimism.io', 'OP Stack 生态，Superchain 愿景', '🔴', 2),
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'Base', 'https://base.org', 'Coinbase 推出的 L2，OP Stack 构建', '🔷', 3),
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'zkSync', 'https://zksync.io', 'ZK Rollup，原生账户抽象', '🟣', 4),
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'StarkNet', 'https://starknet.io', 'ZK-STARK 扩容方案，Cairo 语言', '⭐', 5),
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'Scroll', 'https://scroll.io', 'zkEVM L2，字节码级兼容', '📜', 6),
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'Linea', 'https://linea.build', 'ConsenSys 推出的 zkEVM L2', '📐', 7),
((SELECT id FROM nav_categories WHERE name='L2 & 扩容'), 'Polygon', 'https://polygon.technology', 'AggLayer 聚合层，多种扩容方案', '💜', 8);

-- 跨链桥
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='跨链桥'), 'Across', 'https://across.to', '基于意图的跨链桥，速度快费用低', '🌀', 1),
((SELECT id FROM nav_categories WHERE name='跨链桥'), 'Stargate', 'https://stargate.finance', 'LayerZero 跨链桥，原生资产转移', '🌟', 2),
((SELECT id FROM nav_categories WHERE name='跨链桥'), 'Hop Protocol', 'https://hop.exchange', 'L2 到 L2 快速跨链桥', '🐰', 3),
((SELECT id FROM nav_categories WHERE name='跨链桥'), 'Orbiter Finance', 'https://orbiter.finance', '去中心化跨 Rollup 桥', '🛸', 4),
((SELECT id FROM nav_categories WHERE name='跨链桥'), 'LayerZero', 'https://layerzero.network', '全链互操作性协议', '0️⃣', 5),
((SELECT id FROM nav_categories WHERE name='跨链桥'), 'Wormhole', 'https://wormhole.com', '多链消息传递协议', '🕳️', 6);

-- 数据分析
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='数据分析'), 'Dune', 'https://dune.com', '链上数据查询和可视化平台', '📈', 1),
((SELECT id FROM nav_categories WHERE name='数据分析'), 'DefiLlama', 'https://defillama.com', 'DeFi TVL 追踪和数据聚合', '🦙', 2),
((SELECT id FROM nav_categories WHERE name='数据分析'), 'Nansen', 'https://nansen.ai', '链上分析平台，聪明钱追踪', '🧠', 3),
((SELECT id FROM nav_categories WHERE name='数据分析'), 'Arkham', 'https://arkham.ai', '链上情报平台，实体标签', '🕵️', 4),
((SELECT id FROM nav_categories WHERE name='数据分析'), 'Token Terminal', 'https://tokenterminal.com', '协议财务数据和链上指标', '💹', 5),
((SELECT id FROM nav_categories WHERE name='数据分析'), 'Etherscan', 'https://etherscan.io', '以太坊区块浏览器', '🔍', 6),
((SELECT id FROM nav_categories WHERE name='数据分析'), 'CoinGecko', 'https://coingecko.com', '加密货币价格和市值追踪', '🦎', 7),
((SELECT id FROM nav_categories WHERE name='数据分析'), 'L2Beat', 'https://l2beat.com', 'L2 项目分析和风险评估', '📉', 8);

-- 开发工具
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='开发工具'), 'Hardhat', 'https://hardhat.org', '以太坊开发环境，测试和部署', '⛑️', 1),
((SELECT id FROM nav_categories WHERE name='开发工具'), 'Foundry', 'https://getfoundry.sh', 'Rust 编写的高速 Solidity 开发工具', '🔨', 2),
((SELECT id FROM nav_categories WHERE name='开发工具'), 'Remix', 'https://remix.ethereum.org', '浏览器端 Solidity IDE', '💻', 3),
((SELECT id FROM nav_categories WHERE name='开发工具'), 'Alchemy', 'https://alchemy.com', 'Web3 开发平台，RPC 节点服务', '⚗️', 4),
((SELECT id FROM nav_categories WHERE name='开发工具'), 'Infura', 'https://infura.io', '以太坊和 IPFS API 基础设施', '🌐', 5),
((SELECT id FROM nav_categories WHERE name='开发工具'), 'The Graph', 'https://thegraph.com', '去中心化索引协议，链上数据查询', '📡', 6),
((SELECT id FROM nav_categories WHERE name='开发工具'), 'Chainlink', 'https://chain.link', '去中心化预言机网络', '🔗', 7),
((SELECT id FROM nav_categories WHERE name='开发工具'), 'OpenZeppelin', 'https://openzeppelin.com', '安全智能合约库和审计工具', '🛡️', 8);

-- DAO & 治理
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='DAO & 治理'), 'Snapshot', 'https://snapshot.org', '去中心化投票平台，链下治理', '📸', 1),
((SELECT id FROM nav_categories WHERE name='DAO & 治理'), 'Tally', 'https://tally.xyz', '链上治理仪表盘和投票', '🗳️', 2),
((SELECT id FROM nav_categories WHERE name='DAO & 治理'), 'Aragon', 'https://aragon.org', 'DAO 创建和管理框架', '🦅', 3),
((SELECT id FROM nav_categories WHERE name='DAO & 治理'), 'Nouns', 'https://nouns.wtf', '创新 DAO 治理模型，每日拍卖', '⌐◨-◨', 4),
((SELECT id FROM nav_categories WHERE name='DAO & 治理'), 'DAOhaus', 'https://daohaus.club', '无代码 DAO 启动平台', '🏠', 5);

-- 安全
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='安全'), 'CertiK', 'https://certik.com', '智能合约审计和安全评分', '✅', 1),
((SELECT id FROM nav_categories WHERE name='安全'), 'Immunefi', 'https://immunefi.com', 'Web3 漏洞赏金平台', '🐛', 2),
((SELECT id FROM nav_categories WHERE name='安全'), 'Slither', 'https://github.com/crytic/slither', 'Solidity 静态分析框架', '🐍', 3),
((SELECT id FROM nav_categories WHERE name='安全'), 'Revoke.cash', 'https://revoke.cash', '查看和撤销代币授权', '🚫', 4),
((SELECT id FROM nav_categories WHERE name='安全'), 'De.Fi', 'https://de.fi', 'DeFi 安全仪表盘，风险扫描', '🛡️', 5),
((SELECT id FROM nav_categories WHERE name='安全'), 'Tenderly', 'https://tenderly.co', '智能合约调试和模拟平台', '🔬', 6);

-- 新闻资讯
INSERT INTO nav_sites (category_id, name, url, description, icon, sort_order) VALUES
((SELECT id FROM nav_categories WHERE name='新闻资讯'), 'The Block', 'https://theblock.co', '加密行业深度研究和新闻', '📋', 1),
((SELECT id FROM nav_categories WHERE name='新闻资讯'), 'Bankless', 'https://bankless.com', 'DeFi 和 Web3 教育与分析', '🏴', 2),
((SELECT id FROM nav_categories WHERE name='新闻资讯'), 'CoinDesk', 'https://coindesk.com', '领先的加密货币新闻媒体', '📰', 3),
((SELECT id FROM nav_categories WHERE name='新闻资讯'), 'Decrypt', 'https://decrypt.co', 'Web3 新闻和教育平台', '🔓', 4),
((SELECT id FROM nav_categories WHERE name='新闻资讯'), 'Foresight News', 'https://foresightnews.pro', '中文 Web3 深度资讯', '🔭', 5),
((SELECT id FROM nav_categories WHERE name='新闻资讯'), 'BlockBeats', 'https://theblockbeats.info', '律动 BlockBeats，中文加密媒体', '🎵', 6);
