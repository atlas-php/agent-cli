#!/usr/bin/env bash

# Exit immediately on errors
set -e

# --- ARG CHECK ---------------------------------------------------------------

if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <new-branch-name> <source-branch>"
  exit 1
fi

NEW_BRANCH="$1"
SOURCE_BRANCH="$2"

# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This directory is not a Git repository."
  exit 1
fi

# --- CHECK WORKING TREE CLEANLINESS -----------------------------------------

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: Working directory has uncommitted changes."
  echo "Commit, stash, or discard changes before creating a new branch."
  exit 1
fi

# --- CHECK IF SOURCE BRANCH EXISTS ------------------------------------------

if ! git show-ref --verify --quiet "refs/heads/$SOURCE_BRANCH"; then
  echo "Error: Source branch \"$SOURCE_BRANCH\" does not exist."
  exit 1
fi

# --- CHECK IF NEW BRANCH ALREADY EXISTS --------------------------------------

if git show-ref --verify --quiet "refs/heads/$NEW_BRANCH"; then
  echo "Error: Branch \"$NEW_BRANCH\" already exists."
  exit 1
fi

# --- CREATE THE NEW BRANCH ---------------------------------------------------

echo "Creating branch \"$NEW_BRANCH\" from \"$SOURCE_BRANCH\"..."

git checkout "$SOURCE_BRANCH"
git checkout -b "$NEW_BRANCH"

echo "Branch \"$NEW_BRANCH\" created successfully and now checked out."
