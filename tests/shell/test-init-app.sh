#!/usr/bin/env bash
# Regression tests for bin/init-app, .gitignore, and vite.config.js fixes.
# S1: sed placeholder strings in target files
# S2: docker compose </dev/null guards
# S3: .gitignore /.pnpm-store entry
# S4: vite.config.js server block

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

PASS=0
FAIL=0

pass() { echo "PASS: $1"; ((PASS++)); }
fail() { echo "FAIL: $1"; ((FAIL++)); }

# ---------------------------------------------------------------------------
# S1 — sed placeholder strings exist in target files
# ---------------------------------------------------------------------------

# .env.example must contain DB_DATABASE=skeleton_test (line-anchored)
if grep -qF "DB_DATABASE=skeleton_test" "$REPO_ROOT/.env.example"; then
    pass "S1a: .env.example contains DB_DATABASE=skeleton_test"
else
    fail "S1a: .env.example missing DB_DATABASE=skeleton_test — sed target gone"
fi

# compose.yml must contain POSTGRES_DB: skeleton_test
if grep -qF "POSTGRES_DB: skeleton_test" "$REPO_ROOT/compose.yml"; then
    pass "S1b: compose.yml contains POSTGRES_DB: skeleton_test"
else
    fail "S1b: compose.yml missing POSTGRES_DB: skeleton_test — sed target gone"
fi

# compose.yml must contain -d skeleton_test (healthcheck pg_isready flag)
if grep -qF -- "-d skeleton_test" "$REPO_ROOT/compose.yml"; then
    pass "S1c: compose.yml contains -d skeleton_test"
else
    fail "S1c: compose.yml missing -d skeleton_test — sed target gone"
fi

# compose.yml must contain DB_DATABASE: skeleton_test (app service env)
if grep -qF "DB_DATABASE: skeleton_test" "$REPO_ROOT/compose.yml"; then
    pass "S1d: compose.yml contains DB_DATABASE: skeleton_test"
else
    fail "S1d: compose.yml missing DB_DATABASE: skeleton_test — sed target gone"
fi

# composer.json must contain "name": "local/skeleton-test"
if grep -qF '"name": "local/skeleton-test"' "$REPO_ROOT/composer.json"; then
    pass "S1e: composer.json contains local/skeleton-test"
else
    fail "S1e: composer.json missing local/skeleton-test — sed target gone"
fi

# composer.json must contain "description": "Skeleton Test application."
if grep -qF '"description": "Skeleton Test application."' "$REPO_ROOT/composer.json"; then
    pass "S1f: composer.json contains Skeleton Test application."
else
    fail "S1f: composer.json missing Skeleton Test application. — sed target gone"
fi

# bin/init-app must reference the APP_NAME sed (targets ^APP_NAME=)
if grep -qF 'APP_NAME=' "$REPO_ROOT/bin/init-app"; then
    pass "S1g: bin/init-app references APP_NAME= sed pattern"
else
    fail "S1g: bin/init-app missing APP_NAME= sed pattern"
fi

# ---------------------------------------------------------------------------
# S1 guard — CLAUDE.md sed is guarded with [ -f CLAUDE.md ]
# ---------------------------------------------------------------------------

if grep -q '\[ -f CLAUDE\.md \]' "$REPO_ROOT/bin/init-app"; then
    pass "S1-guard: CLAUDE.md sed guarded with [ -f CLAUDE.md ]"
else
    fail "S1-guard: bin/init-app missing [ -f CLAUDE.md ] guard before CLAUDE.md sed"
fi

# ---------------------------------------------------------------------------
# S2 — all docker compose invocations redirect stdin
# ---------------------------------------------------------------------------

TOTAL_DC=$(grep -c "docker compose" "$REPO_ROOT/bin/init-app")
GUARDED_DC=$(grep -cE "docker compose.+</dev/null" "$REPO_ROOT/bin/init-app")

if [[ "$TOTAL_DC" -eq "$GUARDED_DC" && "$TOTAL_DC" -gt 0 ]]; then
    pass "S2: all $TOTAL_DC docker compose invocations have </dev/null"
else
    fail "S2: $GUARDED_DC of $TOTAL_DC docker compose invocations have </dev/null — $(( TOTAL_DC - GUARDED_DC )) missing redirect"
fi

# ---------------------------------------------------------------------------
# S3 — .gitignore contains /.pnpm-store
# ---------------------------------------------------------------------------

if grep -qF "/.pnpm-store" "$REPO_ROOT/.gitignore"; then
    pass "S3: .gitignore contains /.pnpm-store"
else
    fail "S3: .gitignore missing /.pnpm-store"
fi

# ---------------------------------------------------------------------------
# S4 — vite.config.js server block has host and hmr
# ---------------------------------------------------------------------------

if grep -qF "host: '0.0.0.0'" "$REPO_ROOT/vite.config.js"; then
    pass "S4a: vite.config.js server.host is '0.0.0.0'"
else
    fail "S4a: vite.config.js missing host: '0.0.0.0'"
fi

if grep -qF "hmr: { host: 'localhost' }" "$REPO_ROOT/vite.config.js"; then
    pass "S4b: vite.config.js server.hmr.host is 'localhost'"
else
    fail "S4b: vite.config.js missing hmr: { host: 'localhost' }"
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

echo
echo "Results: $PASS passed, $FAIL failed"

if [[ "$FAIL" -gt 0 ]]; then
    exit 1
fi
exit 0
