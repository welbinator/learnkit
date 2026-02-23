=== LearnKit ===
Contributors: welbinator
Tags: lms, courses, elearning, quizzes, learning management
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modern WordPress LMS plugin for course creators who value simplicity, performance, and fair pricing.

== Description ==

LearnKit is a lightweight, modern Learning Management System (LMS) plugin for WordPress. Built for course creators who want a clean, intuitive experience without the bloat or steep pricing of traditional LMS solutions.

**Features include:**

* Course, module, and lesson management
* Quiz builder with multiple question types
* Student enrollment and progress tracking
* Quiz attempt limits and pass/fail requirements
* CSV export of quiz reports
* REST API for headless or custom integrations
* Clean, performant frontend templates

== Installation ==

1. Upload the `learnkit` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **LearnKit** in the admin menu to begin creating courses.

== Frequently Asked Questions ==

= Does LearnKit support multiple instructors? =
Not in the current version. Multi-instructor support is planned for a future release.

= Can I restrict lessons to enrolled students only? =
Yes. Lessons are gated behind enrollment by default.

= Does LearnKit support certificates? =
Basic certificate infrastructure is included. A full certificate builder is planned for a future release.

== Changelog ==

= 0.5.3 =
* Fix release zip: include composer.json, exclude .gitkeep, add languages/learnkit.pot
* Fix multi-line phpcs disable/enable blocks in progress controller
* Suppress false-positive UnescapedDBParameter on safely-built SQL in quiz reports

= 0.5.2 =
* Additional plugin checker fixes: languages directory, tested-up-to 6.9, sanitize filter inputs, fix multi-line phpcs ignore blocks

= 0.5.1 =
* Code quality: resolved all WordPress Plugin Checker issues
* Removed deprecated load_plugin_textdomain() (auto-loaded since WP 4.6)
* Added readme.txt for WordPress.org compliance
* Added ABSPATH direct access protection to all PHP files
* Switched table name interpolation to use %i identifier placeholder (WP 6.2+)
* Bumped minimum WordPress requirement to 6.2

= 0.5.0 =
* Added quiz attempt limits and required quiz gating on lesson completion
* Added quiz answer review on results page
* Security and code quality improvements

= 0.4.0 =
* Added quiz reports admin page with CSV export
* Added quiz custom post type and question builder

= 0.3.0 =
* Added student-facing quiz UI and submission handling

= 0.2.0 =
* Added REST API controllers for enrollments, progress, lessons, modules, and quizzes

= 0.1.0 =
* Initial release: course, module, and lesson CPTs with enrollment and progress tracking

== Upgrade Notice ==

= 0.5.0 =
Includes security improvements. Update recommended for all users.
