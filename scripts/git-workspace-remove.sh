#!/usr/bin/env bash

# Exit immediately on errors
set -e

# --- ARG CHECK ---------------------------------------------------------------

if [ -z "$1" ]; then
  echo "Usage: $0 <workspace-name>"
  exit 1
fi

WORKSPACE_NAME="$1"

# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This directory is not a Git repository."
  exit 1
fi

REPO_ROOT=$(git rev-parse --show-toplevel)
cd "$REPO_ROOT"

WORKSPACES_DIR="$REPO_ROOT/.workspaces"
WORKSPACE_PATH="$WORKSPACES_DIR/$WORKSPACE_NAME"

if [ ! -d "$WORKSPACE_PATH" ]; then
  echo "Error: Workspace directory \"$WORKSPACE_PATH\" does not exist."
  exit 1
fi

# --- VERIFY THIS PATH IS A WORKTREE -----------------------------------------

if ! git worktree list --porcelain | grep -q "worktree $WORKSPACE_PATH"; then
  echo "Error: \"$WORKSPACE_PATH\" is not registered as a git worktree."
  echo "Refusing to delete in case it's just a normal directory."
  exit 1
fi

# --- CHECK THAT WORKSPACE ITSELF IS CLEAN -----------------------------------

echo "Checking workspace cleanliness: $WORKSPACE_PATH"

ORIG_DIR=$(pwd)
cd "$WORKSPACE_PATH"

if ! git diff --quiet || ! git diff --cached --quiet || [ -n "$(git ls-files --others --exclude-standard)" ]; then
  echo "Error: Workspace \"$WORKSPACE_NAME\" has uncommitted changes."
  echo "Commit, stash, or discard changes inside that workspace before removing it."
  cd "$ORIG_DIR"
  exit 1
fi

cd "$ORIG_DIR"

# --- REMOVE THE WORKTREE -----------------------------------------------------

echo "Removing workspace \"$WORKSPACE_NAME\" at \"$WORKSPACE_PATH\"..."
git worktree remove "$WORKSPACE_PATH"

echo "Workspace \"$WORKSPACE_NAME\" removed successfully."
