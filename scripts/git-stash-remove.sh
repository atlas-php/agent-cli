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

# --- FIND THE STASH ----------------------------------------------------------

STASH_REF=$(git stash list | grep "$STASH_NAME" | head -n 1 | awk -F: '{print $1}')

if [ -z "$STASH_REF" ]; then
  echo "No stash found with name: \"$STASH_NAME\""
  exit 1
fi

echo "Found stash: $STASH_REF (\"$STASH_NAME\")"
echo "Removing stash..."

# --- DROP THE STASH ----------------------------------------------------------

git stash drop "$STASH_REF"

echo "Stash \"$STASH_NAME\" removed successfully."
