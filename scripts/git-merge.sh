#!/usr/bin/env bash

set -e

# --- ARG CHECK ---------------------------------------------------------------

if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <source-branch> <target-branch>"
  echo "Example: $0 agent/TASK-123-planner feature/TASK-123"
  exit 1
fi

SRC_BRANCH="$1"
TARGET_BRANCH="$2"


# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This directory is not a Git repository."
  exit 1
fi


# --- ENSURE CLEAN WORKING TREE ----------------------------------------------

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: Working directory has uncommitted changes."
  echo "Commit, stash, or discard changes before merging."
  exit 1
fi


# --- CONFIRM BOTH BRANCHES EXIST ---------------------------------------------

if ! git show-ref --verify --quiet "refs/heads/$SRC_BRANCH"; then
  echo "Error: Source branch \"$SRC_BRANCH\" does not exist."
  exit 1
fi

if ! git show-ref --verify --quiet "refs/heads/$TARGET_BRANCH"; then
  echo "Error: Target branch \"$TARGET_BRANCH\" does not exist."
  exit 1
fi


# --- CHECKOUT TARGET BRANCH --------------------------------------------------

echo "Switching to target branch \"$TARGET_BRANCH\"..."
git checkout "$TARGET_BRANCH"


# --- PERFORM MERGE -----------------------------------------------------------

echo "Merging \"$SRC_BRANCH\" into \"$TARGET_BRANCH\"..."

set +e
git merge --no-ff "$SRC_BRANCH"
MERGE_EXIT=$?
set -e


# --- HANDLE MERGE RESULT -----------------------------------------------------

if [ $MERGE_EXIT -ne 0 ]; then
  echo ""
  echo "❌ Merge conflict detected!"
  echo "Resolve conflicts manually OR run: git merge --abort"
  echo ""
  exit 1
fi

echo ""
echo "✔ Merge completed cleanly."
echo "You are now on \"$TARGET_BRANCH\" with merged changes."
