#!/bin/sh
# sync-push.sh - Safe auto-push for the Haley Yachts repo.
#
# WHY THIS EXISTS
#   Terry (and any agent) commits to the local repo. Those commits used to sit
#   local-only until a human pushed them. Worse, the local clone frequently
#   falls BEHIND origin/main because the browser-based admin tools (Article
#   Manager, Featured Yacht editor) commit directly to GitHub via the Contents
#   API. So a blind `git push` gets rejected for divergence.
#
# WHAT IT DOES (in order)
#   1. git fetch origin                - learn the true remote state. NEVER
#                                        reason about the remote from a stale
#                                        local clone.
#   2. git rebase origin/main          - replay local commits on top of remote.
#                                        On a real conflict it aborts cleanly,
#                                        leaves the tree untouched, and FAILS
#                                        LOUDLY so a human can resolve.
#   3. git push origin main            - publish.
#
# IT FAILS LOUDLY. It never swallows a rejection or a conflict. Non-zero exit
# on any problem so the caller (agent or hook) knows it must stop and report.
#
# IT DOES NOT DEPLOY. This pushes to GitHub only. The live site is updated by
# Clark running the cPanel pull himself. Do not add deploy steps here.
#
# USAGE
#   scripts/sync-push.sh
#   (run from anywhere inside the repo, immediately after every commit)

set -eu

say()  { printf '%s\n' "sync-push: $*"; }
die()  { printf '%s\n' "sync-push: ERROR: $*" >&2; exit 1; }

# --- Locate the repo root so this works from any cwd ---------------------------
REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null) \
  || die "not inside a git repository"
cd "$REPO_ROOT"

# Mark that we are inside sync-push so the optional post-commit hook does not
# recurse on rebase-created commits (see scripts/post-commit.sample).
export SYNC_PUSH_RUNNING=1

BRANCH=$(git rev-parse --abbrev-ref HEAD)
[ "$BRANCH" = "main" ] \
  || die "expected to be on 'main', but on '$BRANCH'. Refusing to auto-push."

# --- Refuse to run with a dirty tree ------------------------------------------
# Auto-rebase with uncommitted changes is asking for trouble. Commit first.
if [ -n "$(git status --porcelain)" ]; then
  git status --short
  die "working tree is not clean. Commit (or stash) before sync-push."
fi

# --- 1. Fetch: learn the real remote state ------------------------------------
say "fetching origin ..."
git fetch origin \
  || die "git fetch failed (network / auth?). Nothing pushed."

# --- 2. Rebase local commits onto origin/main ---------------------------------
LOCAL=$(git rev-parse @)
REMOTE=$(git rev-parse origin/main)
BASE=$(git merge-base @ origin/main)

if [ "$LOCAL" = "$REMOTE" ]; then
  say "already in sync with origin/main. Nothing to push."
  exit 0
fi

if [ "$REMOTE" != "$BASE" ]; then
  # Remote has commits we don't have (the admin-tool case). Rebase onto it.
  say "local has diverged from origin/main; rebasing onto origin/main ..."
  if ! git rebase origin/main; then
    git rebase --abort 2>/dev/null || true
    die "REAL CONFLICT while rebasing onto origin/main. Rebase aborted; tree restored. A human must reconcile local commits with the admin-tool commits on GitHub, then re-run sync-push."
  fi
fi

# --- 3. Push ------------------------------------------------------------------
say "pushing to origin/main ..."
git push origin main \
  || die "git push REJECTED after rebase. Do NOT assume it pushed. Re-run sync-push (origin may have moved again); if it keeps failing, a human must investigate."

say "done. Local main is pushed to origin/main."
