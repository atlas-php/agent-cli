#!/usr/bin/env bash

set -e

# --- ENSURE WE ARE IN A GIT REPO --------------------------------------------

if ! git rev-parse --is-inside-work-tree > /dev/null 2>&1; then
  echo "Error: Not inside a Git repository."
  exit 1
fi

REPO_ROOT=$(git rev-parse --show-toplevel)
GITIGNORE="$REPO_ROOT/.gitignore"
ENTRY=".workspaces/"

# --- CREATE .gitignore IF MISSING -------------------------------------------

if [ ! -f "$GITIGNORE" ]; then
  echo "Creating .gitignore..."
  touch "$GITIGNORE"
fi

# --- CHECK IF ENTRY EXISTS ---------------------------------------------------

if grep -Fxq "$ENTRY" "$GITIGNORE"; then
  echo "'.workspaces/' is already in .gitignore"
  exit 0
fi

# --- ADD ENTRY ---------------------------------------------------------------

echo "Adding '.workspaces/' to .gitignore..."
echo "$ENTRY" >> "$GITIGNORE"

echo "Done."
