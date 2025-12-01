#!/usr/bin/env bash

# Exit immediately on errors
set -e

# --- ARG CHECK ---------------------------------------------------------------

if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <workspace-name> <branch-name>"
  exit 1
fi

WORKSPACE_NAME="$1"
BRANCH_NAME="$2"

# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This directory is not a Git repository."
  exit 1
fi

REPO_ROOT=$(git rev-parse --show-toplevel)
cd "$REPO_ROOT"

# --- CHECK WORKING TREE CLEANLINESS (MAIN WORKTREE) -------------------------

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: Main working directory has uncommitted changes."
  echo "Commit, stash, or discard changes before creating a workspace."
  exit 1
fi

# --- CHECK IF BRANCH EXISTS --------------------------------------------------

if ! git show-ref --verify --quiet "refs/heads/$BRANCH_NAME"; then
  echo "Error: Branch \"$BRANCH_NAME\" does not exist."
  exit 1
fi

# --- PREPARE WORKSPACES DIRECTORY -------------------------------------------

WORKSPACES_DIR="$REPO_ROOT/.workspaces"
WORKSPACE_PATH="$WORKSPACES_DIR/$WORKSPACE_NAME"

mkdir -p "$WORKSPACES_DIR"

if [ -d "$WORKSPACE_PATH" ]; then
  echo "Error: Workspace directory \"$WORKSPACE_PATH\" already exists."
  echo "Choose a different workspace name or remove the existing one."
  exit 1
fi

# --- CREATE THE WORKTREE -----------------------------------------------------

echo "Creating workspace \"$WORKSPACE_NAME\" for branch \"$BRANCH_NAME\"..."
git worktree add "$WORKSPACE_PATH" "$BRANCH_NAME"

echo "Workspace created at:"
echo "  $WORKSPACE_PATH"
echo "You can now run agents inside that directory."
