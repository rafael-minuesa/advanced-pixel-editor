#!/bin/bash

# Build WordPress.org Submission ZIP for Advanced Pixel Editor
# This script creates a clean zip file excluding development files

set -e  # Exit on error

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$SCRIPT_DIR"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"
PLUGIN_NAME="advanced-pixel-editor"
ZIP_NAME="${PLUGIN_NAME}.zip"
ZIP_PATH="$PARENT_DIR/$ZIP_NAME"

echo -e "${GREEN}Building WordPress.org submission ZIP for Advanced Pixel Editor...${NC}"
echo ""

# Check if we're in the right directory
if [ ! -f "$PLUGIN_DIR/advanced-pixel-editor.php" ]; then
    echo -e "${RED}Error: advanced-pixel-editor.php not found. Please run this script from the plugin directory.${NC}"
    exit 1
fi

# Remove old zip if it exists
if [ -f "$ZIP_PATH" ]; then
    echo -e "${YELLOW}Removing existing $ZIP_NAME...${NC}"
    rm "$ZIP_PATH"
fi

# Change to parent directory so the ZIP contains a single top-level folder
cd "$PARENT_DIR"

echo -e "${GREEN}Creating ZIP file...${NC}"
echo ""

# Create zip excluding development files
echo "Excluding development files:"
echo "  - .gitignore"
echo "  - .git/ folder"
echo "  - .claude/ folder"
echo "  - CLAUDE.md"
echo "  - README.md (GitHub readme)"
echo "  - CHANGELOG.md"
echo "  - .wordpress-org/ folder (screenshots, banners)"
echo "  - dev-tools/ folder"
echo "  - editor/workspace files"
echo "  - build scripts"
echo "  - node_modules/"
echo ""

zip -r "$ZIP_PATH" "$PLUGIN_NAME/" \
    -x "$PLUGIN_NAME/.git/*" \
    -x "$PLUGIN_NAME/.gitignore" \
    -x "$PLUGIN_NAME/.claude/*" \
    -x "$PLUGIN_NAME/CLAUDE.md" \
    -x "$PLUGIN_NAME/README.md" \
    -x "$PLUGIN_NAME/CHANGELOG.md" \
    -x "$PLUGIN_NAME/.wordpress-org/*" \
    -x "$PLUGIN_NAME/dev-tools/*" \
    -x "$PLUGIN_NAME/build-wp-org-zip.sh" \
    -x "$PLUGIN_NAME/build-wp-org-zip.bat" \
    -x "$PLUGIN_NAME/*.code-workspace" \
    -x "$PLUGIN_NAME/*.DS_Store" \
    -x "$PLUGIN_NAME/*__MACOSX*" \
    -x "$PLUGIN_NAME/*.zip" \
    -x "$PLUGIN_NAME/*.log" \
    -x "$PLUGIN_NAME/node_modules/*" \
    -x "$PLUGIN_NAME/.vscode/*" \
    -x "$PLUGIN_NAME/.idea/*" \
    -x "$PLUGIN_NAME/*.swp" \
    -x "$PLUGIN_NAME/*.swo" \
    -x "$PLUGIN_NAME/*~" \
    -x "$PLUGIN_NAME/.env" \
    -x "$PLUGIN_NAME/.env.*" \
    -x "$PLUGIN_NAME/phpcs.xml" \
    -x "$PLUGIN_NAME/phpunit.xml" \
    -x "$PLUGIN_NAME/tests/*" \
    -x "$PLUGIN_NAME/vendor/*" \
    -x "$PLUGIN_NAME/bower_components/*" \
    -x "$PLUGIN_NAME/grunt/*" \
    -x "$PLUGIN_NAME/gulp/*"

# Verify zip was created
if [ -f "$ZIP_PATH" ]; then
    ZIP_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
    FILE_COUNT=$(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}')
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}ZIP file created successfully!${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo -e "  Location: ${YELLOW}$ZIP_PATH${NC}"
    echo -e "  Size: ${YELLOW}$ZIP_SIZE${NC}"
    echo -e "  Files: ${YELLOW}$FILE_COUNT${NC}"
    echo ""
    echo -e "${GREEN}Files included:${NC}"
    unzip -l "$ZIP_PATH" | tail -n +4 | head -n -2 | awk '{print "  " $4}'
    echo ""
    echo -e "${GREEN}Ready for WordPress.org submission!${NC}"
    echo ""
    echo -e "Next steps:"
    echo "  1. Test the plugin by installing the ZIP in a WordPress test site"
    echo "  2. Run Plugin Check (PCP) on your test site"
    echo "  3. Submit at: https://wordpress.org/plugins/developers/add/"
else
    echo -e "${RED}Error: ZIP file was not created.${NC}"
    exit 1
fi
