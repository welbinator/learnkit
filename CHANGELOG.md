# Changelog

All notable changes to LearnKit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Sprint 2 (Planned)
- Course CRUD interface in React
- Module management with drag-and-drop
- Lesson management with WordPress block editor integration
- Course structure tree view
- Reordering functionality

## [0.1.0] - 2026-02-19

### Sprint 1: Foundation & Plugin Architecture

#### Added
- **Plugin Foundation**
  - Main plugin file with proper WordPress headers
  - Activation and deactivation hooks
  - Internationalization support (i18n)
  - PSR-4 class autoloading with loader pattern

- **Custom Post Types**
  - `lk_course` - Hierarchical course structure
  - `lk_module` - Course modules
  - `lk_lesson` - Individual lessons with Gutenberg support

- **Custom Database Tables**
  - `wp_lk_enrollments` - User course enrollment tracking
  - `wp_lk_progress` - Lesson completion tracking per user
  - `wp_lk_certificates` - Certificate issuance records
  - Database versioning for future migrations

- **REST API** (Namespace: `/wp-json/learnkit/v1/`)
  - `GET /courses` - List all courses
  - `GET /courses/:id` - Get single course
  - `POST /courses` - Create course
  - `PUT /courses/:id` - Update course
  - `DELETE /courses/:id` - Delete course
  - Stub endpoints for modules, lessons, enrollments, progress
  - Permission callbacks for security
  - Input sanitization and validation

- **Admin Interface**
  - React-powered admin page (Hello World + API test)
  - WordPress components integration
  - Admin menu structure (LearnKit → Course Builder, All Courses, etc.)
  - Dev warning page when React bundle not built
  - Admin-specific CSS (mobile-first)

- **React Build Pipeline**
  - @wordpress/scripts setup for webpack + babel
  - React 18 + @wordpress/components
  - Hot reload for development (`npm start`)
  - Production build optimization
  - Webpack config outputting to `assets/js/`

- **Assets**
  - Admin CSS (`learnkit-admin.css`) - Course builder styles
  - Public CSS (`learnkit-public.css`) - Student-facing styles
  - Public JS (`learnkit-public.js`) - Frontend interactions
  - Dev warning JS (`learnkit-admin-dev.js`) - Build instructions

- **Documentation**
  - Comprehensive README with architecture overview
  - React app README with development workflow
  - Inline code documentation (PHPDoc)
  - CHANGELOG.md

#### Security
- Input sanitization for all user inputs
- Output escaping for all displays
- Nonce verification for REST API
- Capability checks for admin functions
- Prepared SQL statements for database queries

#### Performance
- Custom tables instead of post meta for scalable data
- Conditional asset loading (only on relevant pages)
- Indexed database columns for fast queries
- Efficient WordPress queries with proper arguments

#### Technical Decisions
- **CPT vs Custom Tables:** Courses/Modules/Lessons use CPTs (leverage WordPress), Enrollment/Progress use custom tables (performance)
- **Relationships:** Parent-child via `post_parent` field
- **API-First:** All features built on REST endpoints
- **React Admin:** Use @wordpress/components for native WordPress look
- **WordPress Block Editor:** Leverage Gutenberg for lesson content (don't reinvent)

#### Developer Experience
- Git repository initialized
- Feature branch workflow (`feature/sprint-1-foundation`)
- Clear commit messages with detailed descriptions
- Package.json with npm scripts for development
- .gitignore for node_modules and logs

### Changed
- N/A (initial release)

### Deprecated
- N/A (initial release)

### Removed
- N/A (initial release)

### Fixed
- N/A (initial release)

---

## Version History

- **0.1.0** - Sprint 1: Foundation (2026-02-19)
- **Next:** Sprint 2: Course Builder Basics
