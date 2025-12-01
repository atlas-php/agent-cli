#!/usr/bin/env bash

set -e

# --- ARG CHECK ---------------------------------------------------------------

if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <branch-to-rebase> <onto-branch>"
  echo "Example: $0 feature/TASK-123 main"
  exit 1
fi

REBASE_BRANCH="$1"
ONTO_BRANCH="$2"


# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This directory is not a Git repository."
  exit 1
fi


# --- ENSURE CLEAN WORKING TREE ----------------------------------------------

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: Working directory has uncommitted changes."
  echo "Commit, stash, or discard changes before rebasing."
  exit 1
fi


# --- CONFIRM BOTH BRANCHES EXIST ---------------------------------------------

if ! git show-ref --verify --quiet "refs/heads/$REBASE_BRANCH"; then
  echo "Error: Branch-to-rebase \"$REBASE_BRANCH\" does not exist."
  exit 1
fi

if ! git show-ref --verify --quiet "refs/heads/$ONTO_BRANCH"; then
  echo "Error: Onto-branch \"$ONTO_BRANCH\" does not exist."
  exit 1
fi


# --- CHECKOUT BRANCH TO REBASE ----------------------------------------------

echo "Switching to branch \"$REBASE_BRANCH\"..."
git checkout "$REBASE_BRANCH"


# --- PERFORM REBASE ----------------------------------------------------------

echo "Rebasing \"$REBASE_BRANCH\" onto \"$ONTO_BRANCH\"..."

set +e
git rebase "$ONTO_BRANCH"
REBASE_EXIT=$?
set -e


# --- HANDLE REBASE RESULT ----------------------------------------------------

if [ $REBASE_EXIT -ne 0 ]; then
  echo ""
  echo "❌ Rebase encountered conflicts."
  echo "Fix conflicts and run:  git rebase --continue"
  echo "Or abort rebase with:   git rebase --abort"
  echo ""
  exit 1
fi

echo ""
echo "✔ Rebase completed cleanly."
echo "Branch \"$REBASE_BRANCH\" is now based on \"$ONTO_BRANCH\"."
