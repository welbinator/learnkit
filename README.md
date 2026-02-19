# LearnKit - WordPress LMS Plugin

![Tests](https://github.com/welbinator/learnkit/workflows/Tests/badge.svg)

> Modern WordPress LMS plugin for course creators who value simplicity, performance, and fair pricing.

## Overview

LearnKit is a lightweight, performant WordPress Learning Management System (LMS) plugin built with modern standards and developer-friendly architecture. Create, deliver, and monetize online courses with a beautiful, intuitive interface.

## Features (Sprint 1 - Foundation)

- ✅ Custom Post Types: Courses, Modules, Lessons
- ✅ Custom Database Tables: Enrollments, Progress, Certificates
- ✅ REST API: Complete API-first architecture (`/wp-json/learnkit/v1/`)
- ✅ React Admin Interface: Modern course builder UI
- ✅ WordPress Standards: WPCS compliant, security-focused
- ✅ Mobile-First: Responsive design throughout

## Installation

### Development Setup

1. **Clone repository:**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/welbinator/learnkit.git
   ```

2. **Install React dependencies:**
   ```bash
   cd learnkit/admin/react/
   npm install
   npm run build
   ```

3. **Activate plugin:**
   - Go to WordPress Admin → Plugins
   - Activate "LearnKit"
   - Database tables will be created automatically

### Production Installation

1. Download latest release from GitHub
2. Upload to `wp-content/plugins/`
3. Activate via WordPress admin
4. Configure settings (coming in Sprint 5)

## Requirements

- **WordPress:** 6.0 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher
- **Node.js:** 18+ (for development)

## Architecture

### Plugin Structure

```
learnkit/
├── learnkit.php              # Main plugin file
├── includes/                  # Core classes
│   ├── class-learnkit.php            # Main plugin class
│   ├── class-learnkit-activator.php  # Activation hooks
│   ├── class-learnkit-loader.php     # Hook loader
│   ├── class-learnkit-post-types.php # CPT registration
│   └── class-learnkit-rest-api.php   # REST API endpoints
├── admin/                     # Admin functionality
│   ├── class-learnkit-admin.php      # Admin class
│   └── react/                         # React app source
│       ├── src/
│       ├── package.json
│       └── webpack.config.js
├── public/                    # Public-facing functionality
│   └── class-learnkit-public.php
├── assets/                    # Compiled assets
│   ├── css/
│   └── js/
└── README.md
```

### Custom Post Types

- **`lk_course`**: Course content (hierarchical)
- **`lk_module`**: Module organization within courses
- **`lk_lesson`**: Individual lesson content (uses Gutenberg)

### Custom Database Tables

```sql
-- Enrollment tracking
wp_lk_enrollments (user_id, course_id, status, enrolled_date, completed_date)

-- Progress tracking
wp_lk_progress (user_id, lesson_id, course_id, completed, completed_date)

-- Certificate management
wp_lk_certificates (user_id, course_id, certificate_code, issued_date)
```

### REST API Endpoints

**Namespace:** `/wp-json/learnkit/v1/`

**Courses:**
- `GET /courses` - List all courses
- `GET /courses/:id` - Get single course
- `POST /courses` - Create course (requires `edit_posts`)
- `PUT /courses/:id` - Update course
- `DELETE /courses/:id` - Delete course

**Modules:**
- `GET /modules` - List modules (Sprint 2)

**Lessons:**
- `GET /lessons` - List lessons (Sprint 2)

**Enrollments:**
- `GET /enrollments` - List enrollments (Sprint 3, admin only)

**Progress:**
- `GET /progress` - Get user progress (Sprint 3, authenticated)

### React Admin Interface

- **Build Tool:** @wordpress/scripts (webpack + babel)
- **UI Components:** @wordpress/components
- **State Management:** React hooks
- **API Integration:** WordPress REST API with nonce authentication

## Development

### Code Quality Tools

```bash
# Install dependencies
composer install

# Run PHP Code Sniffer
composer phpcs

# Auto-fix PHP Code Sniffer issues
composer phpcbf

# Run PHP Mess Detector
composer phpmd

# Run all linting tools
composer lint

# Run PHPUnit tests
composer test
```

The project includes:
- **PHPCS**: WordPress Coding Standards enforcement
- **PHPMD**: Code complexity and quality analysis
- **PHPUnit**: Automated testing suite
- **Pre-commit hooks**: Automatic code quality checks

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed development workflow.

### React Development Workflow

```bash
# Navigate to React app
cd admin/react/

# Install dependencies
npm install

# Start development server (hot reload)
npm start

# Build for production
npm run build

# Lint code
npm run lint:js
npm run lint:css

# Format code
npm run format
```

### WordPress Development

The plugin follows WordPress Coding Standards (WPCS):

- **PSR-4 Autoloading:** Class-based architecture
- **Hook System:** Loader pattern for actions/filters
- **Security:** Nonces, capability checks, sanitization, escaping
- **Performance:** Efficient queries, custom tables for scale
- **i18n Ready:** All strings translatable

### Testing the Plugin

1. **Activate plugin** in WordPress admin
2. **Check database tables:**
   ```sql
   SHOW TABLES LIKE 'wp_lk_%';
   ```
3. **Test REST API:**
   ```bash
   curl https://learnkit.lndo.site/wp-json/learnkit/v1/courses
   ```
4. **Access admin interface:**
   - Go to WordPress Admin → LearnKit
   - Should see React app with API connection test

## Sprint Roadmap

### ✅ Sprint 1: Foundation & Plugin Architecture (Complete)
- Plugin structure and activation
- Custom post types (courses, modules, lessons)
- Custom database tables (enrollments, progress, certificates)
- REST API foundation
- React build pipeline
- Admin menu structure

### 🔄 Sprint 2: Course Builder Basics (Next)
- Course CRUD interface
- Module management
- Lesson management
- Course structure tree view
- Drag-and-drop reordering

### 📋 Sprint 3: Content Delivery
- Lesson viewer (student-facing)
- Progress tracking
- Module sidebar navigation
- Mark Complete functionality
- Enrollment system

### 📋 Sprint 4: Student Experience Basics
- Student dashboard
- Course catalog
- Self-enrollment flow
- Certificate generator
- Progress indicators

### 📋 Sprint 5+: Advanced Features
- Quizzes and assessments
- WooCommerce integration
- Email notifications
- Drip content scheduling
- Reporting and analytics

## Code Quality

### Security Best Practices
- ✅ Input sanitization (`sanitize_text_field`, `wp_kses_post`)
- ✅ Output escaping (`esc_html`, `esc_url`, `esc_attr`)
- ✅ Nonce verification for forms and AJAX
- ✅ Capability checks (`current_user_can`)
- ✅ Prepared SQL statements (`$wpdb->prepare`)

### Performance Optimizations
- ✅ Custom tables for scalable data (enrollments, progress)
- ✅ Efficient queries (indexed columns)
- ✅ Conditional asset loading (only on relevant pages)
- ✅ Lazy loading for React components (Sprint 2+)

## Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/my-feature`
3. Commit changes: `git commit -m "Add feature"`
4. Push to branch: `git push origin feature/my-feature`
5. Submit pull request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use `npm run format` for JavaScript
- Document all functions with PHPDoc
- Write unit tests for new features (Sprint 2+)

## Support

- **Documentation:** [Coming in Sprint 4]
- **Issues:** [GitHub Issues](https://github.com/welbinator/learnkit/issues)
- **Email:** james.welbes@gmail.com

## License

GPL v2 or later

## Credits

Built by [James Welbes](https://jameswelbes.com)

---

**Status:** Sprint 1 Complete ✅ | Next: Sprint 2 Course Builder
