# test/scripts/

Phase 0 ~ Phase 1 一次性脚本。

## scaffold-laravel.sh

用 Docker composer 镜像把 Laravel 12 + Filament 3 scaffold 到 `backend-laravel/`。

**前置**：Docker Desktop 已运行。装 Docker：

```bash
brew install --cask docker-desktop
open -a Docker          # 启动 Docker.app（首次需要 GUI 授权 helper）
# 等右上角 🐳 图标变成 "Docker Desktop is running"
docker info             # 验证
```

**跑 scaffold**：

```bash
cd /Users/heicat/xuaweb3/test
bash scripts/scaffold-laravel.sh
```

跑完会有：
- `backend-laravel/`（Laravel 12 + Filament 3 + Sanctum + Pest）
- `backend-laravel/.env.local.example`（合并进 `.env`）

## 后续

- Phase 0 schema 升级（独立 SQL）：见 `migrations/README.md`
- Phase 1 起点：导航模块（nav-categories + nav-sites + 4 公共 API）
