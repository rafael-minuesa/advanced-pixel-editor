#!/bin/bash

# Version Bump Script for Advanced Image Editor
# Usage: ./version-bump.sh [major|minor|patch] [description]

set -e

# Check if version type is provided
if [ $# -lt 1 ]; then
    echo "Usage: $0 [major|minor|patch] [optional description]"
    echo "Example: $0 minor 'Added new feature'"
    exit 1
fi

VERSION_TYPE=$1
DESCRIPTION=${2:-"Version bump"}

# Get current version from plugin file
CURRENT_VERSION=$(grep "Version:" advanced-image-editor.php | head -1 | sed 's/.*Version: //' | tr -d ' ')
echo "Current version: $CURRENT_VERSION"

# Parse version numbers
IFS='.' read -ra VERSION_PARTS <<< "$CURRENT_VERSION"
MAJOR=${VERSION_PARTS[0]}
MINOR=${VERSION_PARTS[1]}
PATCH=${VERSION_PARTS[2]}

# Bump version based on type
case $VERSION_TYPE in
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    patch)
        PATCH=$((PATCH + 1))
        ;;
    *)
        echo "Invalid version type. Use: major, minor, or patch"
        exit 1
        ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"
echo "New version: $NEW_VERSION"

# Update version in plugin file
sed -i "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" advanced-image-editor.php
sed -i "s/AIE_VERSION', '$CURRENT_VERSION'/AIE_VERSION', '$NEW_VERSION'/" advanced-image-editor.php

# Update version in readme.txt
sed -i "s/Stable tag: $CURRENT_VERSION/Stable tag: $NEW_VERSION/" readme.txt

# Update CHANGELOG.md
DATE=$(date +%Y-%m-%d)
sed -i "s/## \[Unreleased\]/## [$NEW_VERSION] - $DATE\n\n$DESCRIPTION\n\n## [Unreleased]/" CHANGELOG.md

# Update readme.txt changelog
sed -i "s/== Changelog ==/== Changelog ==\n\n= $NEW_VERSION =\n* $DESCRIPTION/" readme.txt

echo "Version bumped to $NEW_VERSION"
echo "Updated files:"
echo "  - advanced-image-editor.php"
echo "  - readme.txt"
echo "  - CHANGELOG.md"
echo ""
echo "Next steps:"
echo "1. Review the changes"
echo "2. Commit: git add . && git commit -m 'Release v$NEW_VERSION'"
echo "3. Tag: git tag v$NEW_VERSION"
echo "4. Push: git push && git push --tags"