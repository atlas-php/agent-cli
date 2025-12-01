#!/usr/bin/env bash

# Exit immediately on errors
set -e

# --- ARG CHECK ---------------------------------------------------------------

if [ -z "$1" ]; then
  echo "Usage: $0 <stash-name>"
  exit 1
fi

STASH_NAME="$1"

# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This directory is not a Git repository."
  exit 1
fi

# --- CHECK FOR CHANGES -------------------------------------------------------

# Check if there are ANY changes (tracked or untracked)
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard)" ]; then
  echo "No changes to stash."
  exit 0
fi

# --- SAVE THE STASH ----------------------------------------------------------

echo "Stashing current workspace as: \"$STASH_NAME\""

# Stash everything (tracked + untracked)
git stash push -u -m "$STASH_NAME"

echo "Done."
echo "Saved stash:"
git stash list | grep "$STASH_NAME" || true


#chmod +x ./scripts/git-stash-remove.sh