#!/usr/bin/env bash
# Laravel Cloud has no root and no Chromium or qpdf, so both are unpacked into .cloud at build time.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CLOUD="$ROOT/.cloud"
WORK="$(mktemp -d)"
PLAYWRIGHT_VERSION=1.64.1
PACKAGES="qpdf libqpdf29 libatk1.0-0 libatk-bridge2.0-0 libatspi2.0-0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libxkbcommon0 libasound2 libwayland-server0 libdrm2 libxcb-randr0 libxi6 libxrender1 libxext6 libnss3 libnspr4 libcups2 libdbus-1-3 libavahi-client3 libavahi-common3 libxshmfence1 fonts-liberation2"

rm -rf "$CLOUD"
mkdir -p "$CLOUD/sys" "$WORK/lists/partial" "$WORK/cache/archives/partial" "$WORK/debs"

APT=(-o "Dir::State::lists=$WORK/lists" -o "Dir::Cache=$WORK/cache" -o Debug::NoLocking=1)
apt-get "${APT[@]}" update 2>/dev/null
(cd "$WORK/debs" && apt-get "${APT[@]}" download $PACKAGES)
for deb in "$WORK"/debs/*.deb; do dpkg -x "$deb" "$CLOUD/sys"; done

PLAYWRIGHT_BROWSERS_PATH="$WORK/browsers" npx -y "playwright@$PLAYWRIGHT_VERSION" install chromium-headless-shell
mv "$(dirname "$(find "$WORK/browsers" -type f -name chrome-headless-shell | head -1)")" "$CLOUD/chromium"

export LD_LIBRARY_PATH="$CLOUD/sys/usr/lib/aarch64-linux-gnu:$CLOUD/sys/lib/aarch64-linux-gnu"
if ldd "$CLOUD/chromium/chrome-headless-shell" | grep 'not found'; then
    echo 'Chromium is missing shared libraries' >&2
    exit 1
fi
"$CLOUD/chromium/chrome-headless-shell" --version
"$CLOUD/sys/usr/bin/qpdf" --version | head -1

rm -rf "$WORK"
