<?php
/**
 * Rewrite rules for the template page system.
 *
 * Registers custom rewrite rules that route content-slug URLs to
 * user-configured WordPress pages, allowing themes and page-builders
 * to control the wrapping template while LearnKit shortcodes inject content.
 *
 * URL prefixes are derived from each template page's own slug, so renaming
 * the page automatically updates the URL structure after a settings save.
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

/**
 * Handles rewrite rules for the LearnKit template page system.
 *
 * @since 0.8.0
 */
class LearnKit_Rewrite {

	/**
	 * Register hooks.
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
	 * Return the URL base (slug) for a given template page option.
	 *
	 * e.g. if the user's lesson page is a page with slug "learn", returns "learn".
	 * Returns null when no page is configured.
	 *
	 * @since 0.8.0
	 * @param  string $option_name Option key, e.g. 'learnkit_lesson_page'.
	 * @return string|null
	 */
	public static function get_base( $option_name ) {
		$page_id = get_option( $option_name );
		if ( ! $page_id ) {
			return null;
		}
		$slug = get_post_field( 'post_name', absint( $page_id ) );
		return $slug ?: null;
	}

	/**
	 * Add custom rewrite rules for course, lesson, and quiz template pages.
	 *
	 * The URL prefix for each type is the slug of the configured template page,
	 * so it follows whatever the user names the page.
	 *
	 * Rules are only added when the corresponding template page option is set.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function add_rewrite_rules() {
		$course_page_id = get_option( 'learnkit_course_page' );
		if ( $course_page_id ) {
			$base = self::get_base( 'learnkit_course_page' );
			if ( $base ) {
				add_rewrite_rule(
					$base . '/([^/]+)/?$',
					'index.php?page_id=' . absint( $course_page_id ) . '&lk_course_slug=$matches[1]',
					'top'
				);
			}
		}

		$lesson_page_id = get_option( 'learnkit_lesson_page' );
		if ( $lesson_page_id ) {
			$base = self::get_base( 'learnkit_lesson_page' );
			if ( $base ) {
				add_rewrite_rule(
					$base . '/([^/]+)/?$',
					'index.php?page_id=' . absint( $lesson_page_id ) . '&lk_lesson_slug=$matches[1]',
					'top'
				);
			}
		}

		$quiz_page_id = get_option( 'learnkit_quiz_page' );
		if ( $quiz_page_id ) {
			$base = self::get_base( 'learnkit_quiz_page' );
			if ( $base ) {
				add_rewrite_rule(
					$base . '/([^/]+)/?$',
					'index.php?page_id=' . absint( $quiz_page_id ) . '&lk_quiz_slug=$matches[1]',
					'top'
				);
			}
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
	 * @since 0.8.0
	 * @return void
	 */
	public static function flush() {
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules once when the configured template page slugs change.
	 *
	 * Stores a hash of the current page slugs; if it differs from the stored
	 * value, flushes and updates so stale rules don't persist.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function maybe_flush() {
		$course_base = self::get_base( 'learnkit_course_page' ) ?? '';
		$lesson_base = self::get_base( 'learnkit_lesson_page' ) ?? '';
		$quiz_base   = self::get_base( 'learnkit_quiz_page' ) ?? '';

		$rewrite_version = $course_base . '|' . $lesson_base . '|' . $quiz_base;
		$stored_version  = get_option( 'learnkit_rewrite_version', '' );

		if ( $stored_version !== $rewrite_version ) {
			flush_rewrite_rules();
			update_option( 'learnkit_rewrite_version', $rewrite_version );
		}
	}
}
