#!/bin/bash
# 构建共享主机部署包（public/ 展平到网站根目录）
# 用法：bash build/deploy-shared.sh [输出zip路径]
set -e
SRC="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$HOME/workspace/zjmf-deploy-v1.1.zip}"
STAGE=$(mktemp -d)
trap "rm -rf $STAGE" EXIT

# 1. 拷贝项目（排除 git / 本地配置 / 日志）
rsync -a --exclude='.git' --exclude='config/config.php' --exclude='storage/logs/*' "$SRC/" "$STAGE/"

# 2. public 展平到根：index.php / .htaccess / assets / install.php
cp "$SRC/public/index.php" "$STAGE/index.php"
cp "$SRC/public/.htaccess" "$STAGE/.htaccess" 2>/dev/null || true
cp -r "$SRC/public/assets" "$STAGE/assets"
[ -f "$SRC/public/install.php" ] && cp "$SRC/public/install.php" "$STAGE/install.php" || true
rm -rf "$STAGE/public"

# 3. 适配根目录 index.php 的 bootstrap 路径
sed -i "s#require __DIR__ . '/../app/bootstrap.php';#require __DIR__ . '/app/bootstrap.php';#" "$STAGE/index.php"
grep -q "require __DIR__ . '/app/bootstrap.php';" "$STAGE/index.php" || { echo "index.php 适配失败"; exit 1; }

# 4. 打包
rm -f "$OUT"
(cd "$STAGE" && zip -qr "$OUT" .)
echo "OK: $OUT ($(unzip -l "$OUT" | tail -1 | awk '{print $2}') files)"
