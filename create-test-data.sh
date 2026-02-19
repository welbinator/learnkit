#!/bin/bash
#
# Create test data for LearnKit plugin
#
# Usage: ./create-test-data.sh
#

set -e

echo "🎓 Creating LearnKit test data..."

# Create 3 test courses
echo "Creating courses..."
COURSE1=$(lando wp post create --post_type=lk_course \
    --post_title="Introduction to WordPress Development" \
    --post_content="Learn the fundamentals of WordPress theme and plugin development." \
    --post_excerpt="Master WordPress development from scratch" \
    --post_status=publish \
    --porcelain)

COURSE2=$(lando wp post create --post_type=lk_course \
    --post_title="Advanced React Development" \
    --post_content="Build modern web applications with React, hooks, and state management." \
    --post_excerpt="Take your React skills to the next level" \
    --post_status=publish \
    --porcelain)

COURSE3=$(lando wp post create --post_type=lk_course \
    --post_title="REST API Design Best Practices" \
    --post_content="Learn how to design, build, and document professional REST APIs." \
    --post_excerpt="Master API architecture and security" \
    --post_status=publish \
    --porcelain)

echo "✓ Created 3 courses (IDs: $COURSE1, $COURSE2, $COURSE3)"

# Create modules for Course 1
echo "Creating modules for Course 1..."
MODULE1=$(lando wp post create --post_type=lk_module \
    --post_title="Getting Started with WordPress" \
    --post_content="Introduction to WordPress core concepts" \
    --post_parent=$COURSE1 \
    --post_status=publish \
    --porcelain)

MODULE2=$(lando wp post create --post_type=lk_module \
    --post_title="Theme Development Basics" \
    --post_content="Learn to build custom WordPress themes" \
    --post_parent=$COURSE1 \
    --post_status=publish \
    --porcelain)

echo "✓ Created 2 modules for Course 1"

# Create lessons for Module 1
echo "Creating lessons for Module 1..."
lando wp post create --post_type=lk_lesson \
    --post_title="What is WordPress?" \
    --post_content="WordPress is a free and open-source content management system..." \
    --post_parent=$MODULE1 \
    --post_status=publish > /dev/null

lando wp post create --post_type=lk_lesson \
    --post_title="Setting Up Your Development Environment" \
    --post_content="Learn how to install WordPress locally using Lando or Local..." \
    --post_parent=$MODULE1 \
    --post_status=publish > /dev/null

lando wp post create --post_type=lk_lesson \
    --post_title="Understanding the WordPress File Structure" \
    --post_content="Explore wp-content, wp-includes, and core WordPress files..." \
    --post_parent=$MODULE1 \
    --post_status=publish > /dev/null

echo "✓ Created 3 lessons for Module 1"

# Create lessons for Module 2
echo "Creating lessons for Module 2..."
lando wp post create --post_type=lk_lesson \
    --post_title="Theme Template Hierarchy" \
    --post_content="Understand how WordPress selects which template file to use..." \
    --post_parent=$MODULE2 \
    --post_status=publish > /dev/null

lando wp post create --post_type=lk_lesson \
    --post_title="Building Your First Theme" \
    --post_content="Create a basic WordPress theme from scratch..." \
    --post_parent=$MODULE2 \
    --post_status=publish > /dev/null

echo "✓ Created 2 lessons for Module 2"

# Summary
echo ""
echo "🎉 Test data created successfully!"
echo ""
echo "Summary:"
echo "  - 3 Courses"
echo "  - 2 Modules (Course 1)"
echo "  - 5 Lessons (Modules 1-2)"
echo ""
echo "View at: https://learnkit.lndo.site/wp-admin/edit.php?post_type=lk_course"
echo ""
