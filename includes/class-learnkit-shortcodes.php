<?php
/**
 * Shortcodes for the LearnKit template page system.
 *
 * Provides [learnkit_course], [learnkit_lesson], and [learnkit_quiz]
 * shortcodes that render the corresponding plugin template inside any
 * WordPress page, allowing themes and page-builders to own the wrapper.
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
 * Registers and renders LearnKit content shortcodes.
 *
 * Each shortcode loads the corresponding CPT post by slug (taken from the
 * custom rewrite query vars or the `name` fallback), sets up global post
 * context, sets a flag so the existing plugin templates skip their own
 * get_header() / get_footer() calls, and returns the captured output.
 *
 * @since 0.8.0
 */
class LearnKit_Shortcodes {

	/**
	 * Track which shortcodes have already rendered on this page load
	 * to prevent accidental double-output when a page contains the
	 * shortcode more than once.
	 *
	 * @since 0.8.0
	 * @var array
	 */
	private static $rendered = array();

	/**
	 * Register hooks.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
	}

	/**
	 * Register the three LearnKit content shortcodes.
	 *
	 * @since 0.8.0
	 * @return void
	 */
	public static function register_shortcodes() {
		add_shortcode( 'learnkit_course', array( __CLASS__, 'render_course' ) );
		add_shortcode( 'learnkit_lesson', array( __CLASS__, 'render_lesson' ) );
		add_shortcode( 'learnkit_quiz',   array( __CLASS__, 'render_quiz' ) );
	}

	/**
	 * Render the [learnkit_course] shortcode.
	 *
	 * @since  0.8.0
	 * @param  array $atts Shortcode attributes (unused).
	 * @return string      Rendered HTML output.
	 */
	public static function render_course( $atts ) {
		$slug = get_query_var( 'lk_course_slug' );
		if ( ! $slug ) {
			$slug = get_query_var( 'name' );
		}
		if ( ! $slug ) {
			$slug = self::slug_from_uri( LEARNKIT_COURSE_REWRITE_BASE );
		}
		if ( ! $slug ) {
			return '';
		}

		return self::render_template( $slug, 'lk_course', 'single-lk-course.php' );
	}

	/**
	 * Render the [learnkit_lesson] shortcode.
	 *
	 * @since  0.8.0
	 * @param  array $atts Shortcode attributes (unused).
	 * @return string      Rendered HTML output.
	 */
	public static function render_lesson( $atts ) {
		$slug = get_query_var( 'lk_lesson_slug' );
		if ( ! $slug ) {
			$slug = get_query_var( 'name' );
		}
		if ( ! $slug ) {
			$slug = self::slug_from_uri( LEARNKIT_LESSON_REWRITE_BASE );
		}
		if ( ! $slug ) {
			return '';
		}

		return self::render_template( $slug, 'lk_lesson', 'single-lk-lesson.php' );
	}

	/**
	 * Render the [learnkit_quiz] shortcode.
	 *
	 * @since  0.8.0
	 * @param  array $atts Shortcode attributes (unused).
	 * @return string      Rendered HTML output.
	 */
	public static function render_quiz( $atts ) {
		$slug = get_query_var( 'lk_quiz_slug' );
		if ( ! $slug ) {
			$slug = get_query_var( 'name' );
		}
		// Last resort: parse slug from URI (handles Etch block render timing issues).
		if ( ! $slug ) {
			$slug = self::slug_from_uri( LEARNKIT_QUIZ_REWRITE_BASE );
		}
		if ( ! $slug ) {
			return '';
		}

		return self::render_template( $slug, 'lk_quiz', 'single-lk-quiz.php' );
	}

	/**
	 * Parse the CPT slug directly from the request URI as a fallback.
	 *
	 * Some page builders (e.g. Etch) render shortcodes during block rendering
	 * before WordPress has populated custom query vars. This parses the slug
	 * directly from $_SERVER['REQUEST_URI'] using the known rewrite base prefix.
	 *
	 * @since  0.8.0
	 * @param  string $base Rewrite base (e.g. 'quiz', 'lesson', 'course').
	 * @return string       Slug, or empty string if not found.
	 */
	private static function slug_from_uri( $base ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitified -- sanitized below via sanitize_title
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		// Strip query string.
		$path = strtok( $uri, '?' );
		// Match /<base>/<slug>/ pattern.
		if ( preg_match( '#/' . preg_quote( $base, '#' ) . '/([^/]+)/?#', $path, $matches ) ) {
			return sanitize_title( $matches[1] );
		}
		return '';
	}

	/**
	 * Load a CPT post by slug, set up global post data, and include the
	 * corresponding plugin template file, capturing output via output buffering.
	 *
	 * Before the include, $GLOBALS['learnkit_shortcode_context'] is set to true
	 * so that the plugin templates (which normally call get_header() / get_footer()
	 * for the standalone CPT URL case) skip those calls when rendering as a
	 * shortcode inside a page.
	 *
	 * @since  0.8.0
	 * @access private
	 * @param  string $slug      Post slug.
	 * @param  string $post_type CPT name (e.g. 'lk_lesson').
	 * @param  string $template  Filename inside public/templates/.
	 * @return string            Captured HTML.
	 */
	private static function render_template( $slug, $post_type, $template ) {
		// Fetch the CPT post by slug.
		$posts = get_posts( array(
			'name'           => sanitize_title( $slug ),
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		) );

		if ( empty( $posts ) ) {
			return '<p>' . esc_html__( 'Content not found.', 'learnkit' ) . '</p>';
		}

		$cpt_post = $posts[0];

		// Override global $post and WP_Query so get_the_ID() and is_singular()
		// return the CPT post (not the template page) inside the included file.
		global $post, $wp_query;

		$saved_post     = $post;
		$saved_wp_query = clone $wp_query;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional: shortcode must fake single-post context for the included template.
		$post = $cpt_post;
		setup_postdata( $post );

		$wp_query->queried_object    = $cpt_post;
		$wp_query->queried_object_id = $cpt_post->ID;
		$wp_query->posts             = array( $cpt_post );
		$wp_query->post              = $cpt_post;
		$wp_query->post_count        = 1;
		$wp_query->found_posts       = 1;
		$wp_query->is_single         = true;
		$wp_query->is_singular       = true;
		$wp_query->is_page           = false;

		// Signal to the plugin templates that they are running inside a shortcode.
		// The templates check $GLOBALS['learnkit_shortcode_context'] before calling
		// get_header() / get_footer() so they are skipped in this context.
		$GLOBALS['learnkit_shortcode_context'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional flag for template context detection.

		$template_file = plugin_dir_path( __DIR__ ) . 'public/templates/' . $template;

		ob_start();

		if ( file_exists( $template_file ) ) {
			include $template_file;
		}

		$output = ob_get_clean();

		// Clear the shortcode context flag.
		unset( $GLOBALS['learnkit_shortcode_context'] );

		// Restore original global state.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring previously saved state.
		$post     = $saved_post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring previously saved state.
		$wp_query = $saved_wp_query;
		wp_reset_postdata();

		return $output;
	}
}
