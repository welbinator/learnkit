#!/bin/bash
# Final verification script for LearnKit CPT architecture fix

echo "=================================="
echo "LearnKit CPT Architecture Verification"
echo "=================================="
echo ""

echo "1. Checking module post_parent values..."
cd /home/highprrrr/lando/learnkit
lando wp post list --post_type=lk_module --fields=ID,post_title,post_parent --format=table
echo ""

echo "2. Checking module meta (_lk_course_id)..."
lando wp post meta get 12 _lk_course_id
lando wp post meta get 11 _lk_course_id
echo ""

echo "3. Checking lesson post_parent values..."
lando wp post list --post_type=lk_lesson --fields=ID,post_title,post_parent --format=table
echo ""

echo "4. Checking lesson meta (_lk_module_id)..."
lando wp post meta get 16 _lk_module_id
lando wp post meta get 13 _lk_module_id
echo ""

echo "5. Testing module URL (should be 200)..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://learnkit.lndo.site/module/theme-development-basics/")
echo "HTTP Status: $HTTP_CODE"
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ PASS"
else
    echo "❌ FAIL"
fi
echo ""

echo "6. Testing lesson URL (should be 200)..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://learnkit.lndo.site/lesson/theme-template-hierarchy/")
echo "HTTP Status: $HTTP_CODE"
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ PASS"
else
    echo "❌ FAIL"
fi
echo ""

echo "7. Checking REST API meta exposure..."
COURSE_ID=$(curl -s "https://learnkit.lndo.site/wp-json/wp/v2/lk-modules/12" | jq -r '.meta._lk_course_id')
echo "Module 12 _lk_course_id: $COURSE_ID"
if [ "$COURSE_ID" = "8" ]; then
    echo "✅ PASS"
else
    echo "❌ FAIL"
fi
echo ""

MODULE_ID=$(curl -s "https://learnkit.lndo.site/wp-json/wp/v2/lk-lessons/16" | jq -r '.meta._lk_module_id')
echo "Lesson 16 _lk_module_id: $MODULE_ID"
if [ "$MODULE_ID" = "12" ]; then
    echo "✅ PASS"
else
    echo "❌ FAIL"
fi
echo ""

echo "=================================="
echo "Verification Complete!"
echo "=================================="
