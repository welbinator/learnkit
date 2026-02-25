<?php
/**
 * Rewrite rules for the template page system.
 *
 * Registers custom rewrite rules that route course/lesson/quiz slug URLs
 * to user-configured WordPress pages, allowing themes and page-builders
 * to control the wrapping template while LearnKit shortcodes inject content.
 *
 * @link       https://jameswelbes.com
 * @since      0.8.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// URL prefixes for the template page rewrite rules.
define( 'LEARNKIT_COURSE_REWRITE_BASE', 'course' );
define( 'LEARNKIT_LESSON_REWRITE_BASE', 'lesson' );
define( 'LEARNKIT_QUIZ_REWRITE_BASE', 'quiz' );

/**
 * Handles rewrite rules for the LearnKit template page system.
 *
 * @since 0.8.0
 */
class LearnKit_Rewrite {

	/**
	 * Register hooks.
	 *
	 * Call this once from the main plugin file. Runs on init priority 1
	 * so that our rules are flushed before CPT registration fires.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 1 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	/**
	 * Add custom rewrite rules for course, lesson, and quiz template pages.
	 *
	 * Rules are only added when the corresponding template page option is set,
	 * so that unconfigured post types fall back to the existing CPT behavior.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function add_rewrite_rules() {
		$course_page_id = get_option( 'learnkit_course_page' );
		if ( $course_page_id ) {
			add_rewrite_rule(
				LEARNKIT_COURSE_REWRITE_BASE . '/([^/]+)/?$',
				'index.php?page_id=' . absint( $course_page_id ) . '&lk_course_slug=$matches[1]',
				'top'
			);
		}

		$lesson_page_id = get_option( 'learnkit_lesson_page' );
		if ( $lesson_page_id ) {
			add_rewrite_rule(
				LEARNKIT_LESSON_REWRITE_BASE . '/([^/]+)/?$',
				'index.php?page_id=' . absint( $lesson_page_id ) . '&lk_lesson_slug=$matches[1]',
				'top'
			);
		}

		$quiz_page_id = get_option( 'learnkit_quiz_page' );
		if ( $quiz_page_id ) {
			add_rewrite_rule(
				LEARNKIT_QUIZ_REWRITE_BASE . '/([^/]+)/?$',
				'index.php?page_id=' . absint( $quiz_page_id ) . '&lk_quiz_slug=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * Register LearnKit query vars with WordPress.
	 *
	 * @since 0.8.0
	 * @param array $vars Existing public query vars.
	 * @return array Modified query vars.
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'lk_course_slug';
		$vars[] = 'lk_lesson_slug';
		$vars[] = 'lk_quiz_slug';
		return $vars;
	}

	/**
	 * Flush WordPress rewrite rules.
	 *
	 * Called on settings save so that newly-configured template pages
	 * take effect without requiring a manual flush from Settings → Permalinks.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function flush() {
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules once when the rewrite rule version changes.
	 *
	 * This ensures that changes to rewrite base constants (e.g. lk_quiz → quiz)
	 * are picked up automatically on existing sites without requiring a manual
	 * Settings → Permalinks save.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function maybe_flush() {
		$rewrite_version = LEARNKIT_COURSE_REWRITE_BASE . '|' . LEARNKIT_LESSON_REWRITE_BASE . '|' . LEARNKIT_QUIZ_REWRITE_BASE;
		$stored_version  = get_option( 'learnkit_rewrite_version', '' );

		if ( $stored_version !== $rewrite_version ) {
			flush_rewrite_rules();
			update_option( 'learnkit_rewrite_version', $rewrite_version );
		}
	}
}
