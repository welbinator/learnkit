# LearnKit - Quick Start Guide

## Sprint 1 is Complete! 🎉

The foundation is built and ready. Here's what you need to know:

## ✅ What's Done

- Plugin activates cleanly
- 3 Custom Post Types registered (courses, modules, lessons)
- 3 Custom database tables created (enrollments, progress, certificates)
- REST API working at `/wp-json/learnkit/v1/`
- React admin interface built and functional
- Test data script included

## 🚀 Getting Started

### 1. Activate the Plugin

The plugin is already activated on your dev site:
```bash
cd ~/lando/learnkit
lando wp plugin list | grep learnkit
# Status: active ✓
```

### 2. View the Admin Interface

Go to: **https://learnkit.lndo.site/wp-admin/admin.php?page=learnkit**

You should see:
- React-powered admin interface
- API connection test (should show ✓ Connected)
- Sprint 1 completion checklist
- Test buttons

### 3. Check Custom Post Types

**Courses:** https://learnkit.lndo.site/wp-admin/edit.php?post_type=lk_course  
**Modules:** https://learnkit.lndo.site/wp-admin/edit.php?post_type=lk_module  
**Lessons:** https://learnkit.lndo.site/wp-admin/edit.php?post_type=lk_lesson

Test data is already created (3 courses, 2 modules, 5 lessons).

### 4. Test the REST API

```bash
# From within Lando
cd ~/lando/learnkit
lando ssh -c "curl -s 'http://localhost/index.php?rest_route=/learnkit/v1/courses'"

# Should return JSON with course data
# Note: Authentication required for write operations
```

### 5. View Database Tables

```bash
cd ~/lando/learnkit
lando wp db query "SHOW TABLES LIKE 'wp_lk_%';"

# Should show:
# - wp_lk_certificates
# - wp_lk_enrollments
# - wp_lk_progress
```

## 🔧 Development Workflow

### React Development

If you need to modify the admin interface:

```bash
cd ~/lando/learnkit/wp-content/plugins/learnkit/admin/react

# Start development server (hot reload)
npm start

# Build for production
npm run build
```

### Creating More Test Data

```bash
cd ~/lando/learnkit/wp-content/plugins/learnkit
./create-test-data.sh
```

### Git Status

Current branch: `feature/sprint-1-foundation`

Commits:
1. Plugin foundation (CPTs, DB, REST API)
2. React build pipeline
3. Documentation
4. Test data script
5. Sprint 1 summary

**Ready for review!** Do NOT push to GitHub yet (per your instructions).

## 📋 Definition of Done - All Met ✅

- [x] Plugin activates without errors
- [x] Custom post types registered
- [x] Database tables created
- [x] REST API responding
- [x] React admin interface working
- [x] No console errors
- [x] Code committed with clear messages
- [x] Documentation complete

## 📖 Documentation Files

- **README.md** - Complete plugin documentation
- **CHANGELOG.md** - Version history
- **SPRINT1-SUMMARY.md** - Detailed sprint retrospective (read this!)
- **admin/react/README.md** - React development guide

## 🐛 Known Issues / TODOs

1. **REST API Authentication:** Currently returns 401 for unauthenticated requests (expected behavior). Sprint 2 will add authenticated user testing.

2. **Permalinks:** REST API works via `index.php?rest_route=` format. Pretty permalinks may need `.htaccess` configuration.

3. **React Hot Reload:** May need to refresh WordPress admin after React changes during development.

## 🚀 Next Steps: Sprint 2

When you're ready to start Sprint 2:

1. Review this code
2. Test the admin interface
3. Test the REST API
4. Check database tables
5. Read SPRINT1-SUMMARY.md
6. Merge to main if approved
7. Start Sprint 2 (Course Builder CRUD)

## 💬 Questions?

Everything you need to know is in:
- **SPRINT1-SUMMARY.md** (comprehensive review)
- **README.md** (general documentation)
- Code comments (detailed explanations)

## 🎯 Sprint 1 Status

**COMPLETE ✅**

All features implemented, tested, and documented. Foundation is solid and ready for Sprint 2.

---

**Built by:** Carl (Subagent)  
**Date:** February 19, 2026  
**Branch:** feature/sprint-1-foundation  
**Status:** Ready for James's review
