# LearnKit Architecture Notes

## Post Type Relationships

### Design Decision: Meta-Based vs Hierarchical

**Problem:** WordPress hierarchical post types (`hierarchical => true`) use `post_parent` to create parent-child relationships. However, when building hierarchical URLs across *different* post types (e.g., Course → Module → Lesson), WordPress cannot construct proper permalinks because it expects the parent to be the same post type.

**Example of the Issue:**
- Course: `/course/intro-to-wp/`
- Module: `/module/intro-to-wp/getting-started/` ❌ (WordPress tries to build this but fails)
- Lesson: `/lesson/intro-to-wp/getting-started/installation/` ❌❌ (Even worse)

This causes 404 errors because WordPress's rewrite rule system cannot resolve hierarchical slugs across CPT boundaries.

**Solution:** Use **flat post types with meta-based relationships**

### Current Architecture

1. **Courses** (`lk_course`):
   - `hierarchical => true` ✅ (Courses CAN have sub-courses)
   - URL: `/course/intro-to-wp/`
   - URL: `/course/intro-to-wp/advanced-topics/` (sub-course)

2. **Modules** (`lk_module`):
   - `hierarchical => false` ✅ (Flat structure)
   - Relationship: `_lk_course_id` post meta field
   - URL: `/module/getting-started/` (flat)
   - Queryable via REST API: `/wp-json/wp/v2/lk-modules?meta_key=_lk_course_id&meta_value=8`

3. **Lessons** (`lk_lesson`):
   - `hierarchical => false` ✅ (Flat structure)
   - Relationship: `_lk_module_id` post meta field
   - URL: `/lesson/installation/` (flat)
   - Queryable via REST API: `/wp-json/wp/v2/lk-lessons?meta_key=_lk_module_id&meta_value=12`

### Benefits

✅ **No 404 errors:** All URLs are flat and WordPress can resolve them  
✅ **REST API access:** Meta fields registered with `show_in_rest => true`  
✅ **Admin UI:** Meta boxes provide clear parent selection dropdowns  
✅ **Admin columns:** Parent relationships visible in list tables  
✅ **Performance:** Meta queries are fast, especially with proper indexing  
✅ **Flexibility:** Easy to query "all modules in this course" via meta_query

### Migration

When upgrading from hierarchical structure:
- Plugin activation automatically migrates `post_parent` → `_lk_course_id` / `_lk_module_id`
- Sets `post_parent = 0` after migration
- Safe to run multiple times (idempotent)

### Implementation Files

- **CPT Registration:** `includes/class-learnkit-post-types.php`
- **Meta Boxes:** `includes/class-learnkit-meta-boxes.php`
- **Migration:** `includes/class-learnkit-activator.php` → `migrate_relationships()`
- **Standalone Migration:** `includes/migrate-post-parent-to-meta.php` (WP-CLI compatible)

### Example Queries

**Get all modules for a course:**
```php
$modules = get_posts( array(
    'post_type'  => 'lk_module',
    'meta_key'   => '_lk_course_id',
    'meta_value' => $course_id,
    'orderby'    => 'menu_order',
    'order'      => 'ASC',
) );
```

**Get all lessons for a module:**
```php
$lessons = get_posts( array(
    'post_type'  => 'lk_lesson',
    'meta_key'   => '_lk_module_id',
    'meta_value' => $module_id,
    'orderby'    => 'menu_order',
    'order'      => 'ASC',
) );
```

**REST API:**
```bash
# Get modules for course ID 8
GET /wp-json/wp/v2/lk-modules?_lk_course_id=8

# Get lessons for module ID 12
GET /wp-json/wp/v2/lk-lessons?_lk_module_id=12
```

---

**Last Updated:** 2026-02-19  
**Decision Made By:** James Welbes  
**Sprint:** Sprint 1 - Foundation
