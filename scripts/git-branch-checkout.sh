#!/usr/bin/env bash

# Exit immediately on errors
set -e

# --- ARG CHECK ---------------------------------------------------------------

if [ -z "$1" ]; then
  echo "Usage: $0 <branch-name>"
  exit 1
fi

BRANCH_NAME="$1"

# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This directory is not a Git repository."
  exit 1
fi

# --- CHECK WORKING TREE CLEANLINESS -----------------------------------------

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: Working directory has uncommitted changes."
  echo "Commit, stash, or discard changes before switching branches."
  exit 1
fi

# --- CHECK IF BRANCH EXISTS -------------------------------------------------

if ! git show-ref --verify --quiet "refs/heads/$BRANCH_NAME"; then
  echo "Error: Branch \"$BRANCH_NAME\" does not exist."
  exit 1
fi

# --- CHECKOUT THE BRANCH ----------------------------------------------------

echo "Checking out existing branch: \"$BRANCH_NAME\""

git checkout "$BRANCH_NAME"

echo "Switched to branch \"$BRANCH_NAME\" successfully."
