#!/bin/bash
#
# LearnKit Testing Infrastructure Verification Script
# Runs all quality checks and tests
#

set -e  # Exit on any error

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PLUGIN_DIR"

echo "============================================="
echo "LearnKit Testing Infrastructure Verification"
echo "============================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'  # No Color

echo -e "${YELLOW}1. Installing dependencies...${NC}"
if [ ! -d "vendor" ]; then
    composer install --quiet
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${GREEN}✓ Dependencies already installed${NC}"
fi
echo ""

echo -e "${YELLOW}2. Running PHPCS (WordPress Coding Standards)...${NC}"
if composer phpcs --quiet; then
    echo -e "${GREEN}✓ PHPCS passed${NC}"
else
    echo -e "${RED}✗ PHPCS failed${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}3. Running PHPMD (PHP Mess Detector)...${NC}"
if composer phpmd --quiet; then
    echo -e "${GREEN}✓ PHPMD passed${NC}"
else
    echo -e "${RED}✗ PHPMD failed${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}4. Running PHPUnit tests...${NC}"
if vendor/bin/phpunit --colors=never > /dev/null 2>&1; then
    TEST_COUNT=$(vendor/bin/phpunit --list-tests 2>/dev/null | wc -l)
    echo -e "${GREEN}✓ PHPUnit tests passed${NC}"
    echo "   (Test suite executed successfully)"
else
    echo -e "${RED}✗ PHPUnit tests failed${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}5. Checking pre-commit hook...${NC}"
if [ -x ".git/hooks/pre-commit" ]; then
    echo -e "${GREEN}✓ Pre-commit hook installed and executable${NC}"
else
    echo -e "${RED}✗ Pre-commit hook missing or not executable${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}6. Checking GitHub Actions workflow...${NC}"
if [ -f ".github/workflows/tests.yml" ]; then
    echo -e "${GREEN}✓ GitHub Actions workflow exists${NC}"
else
    echo -e "${RED}✗ GitHub Actions workflow missing${NC}"
    exit 1
fi
echo ""

echo "============================================="
echo -e "${GREEN}✅ ALL CHECKS PASSED${NC}"
echo "============================================="
echo ""
echo "Your testing infrastructure is fully functional!"
echo ""
echo "Available commands:"
echo "  composer phpcs    - Check coding standards"
echo "  composer phpcbf   - Auto-fix coding standards"
echo "  composer phpmd    - Check code complexity"
echo "  composer test     - Run test suite"
echo "  composer lint     - Run all quality checks"
echo ""
echo "Pre-commit hook will automatically check staged files."
echo "GitHub Actions will run full test matrix on push/PR."
echo ""
