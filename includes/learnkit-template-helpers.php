<?php
/**
 * LearnKit Template Helpers
 *
 * Utility functions available in frontend templates.
 *
 * @package LearnKit
 * @since   0.6.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the CSS classes for a LearnKit frontend button.
 *
 * Returns the base classes for the button plus any ACSS classes configured
 * in Settings → LearnKit → Settings (only when Automatic CSS is active).
 *
 * @param string $button_key  The button identifier key (e.g. 'enroll_button').
 * @param string $base_classes Space-separated base classes always applied (e.g. 'btn--lk-enroll').
 * @return string
 */
function learnkit_button_classes( $button_key, $base_classes = '' ) {
	$classes = array_filter( explode( ' ', $base_classes ) );

	if ( defined( 'ACSS_PLUGIN_FILE' ) ) {
		$defaults = array(
			'enroll_button_class'            => '',
			'start_course_button_class'      => '',
			'continue_learning_button_class' => '',
			'next_lesson_button_class'          => '',
			'next_lesson_button_disabled_class' => '',
			'prev_lesson_button_class'          => '',
			'prev_lesson_button_disabled_class' => '',
			'mark_complete_button_class'     => '',
			'take_quiz_button_class'         => '',
			'start_quiz_button_class'        => '',
			'submit_quiz_button_class'       => '',
			'retake_quiz_button_class'       => '',
			'back_to_lesson_button_class'    => '',
			'back_to_course_button_class'    => '',
			'login_button_class'             => '',
		);
		$acss_settings = get_option( 'learnkit_acss_settings', $defaults );
		$acss_class    = $acss_settings[ $button_key . '_class' ] ?? ( $defaults[ $button_key . '_class' ] ?? '' );
		$outline       = ! empty( $acss_settings[ $button_key . '_outline' ] );

		if ( $acss_class ) {
			$classes[] = sanitize_html_class( $acss_class );
		}
		if ( $outline ) {
			$classes[] = 'btn--outline';
		}
	}

	return apply_filters( 'learnkit_button_classes', implode( ' ', array_unique( $classes ) ), $button_key, $base_classes );
}

/**
 * Get the front-end URL for a quiz post.
 *
 * When a quiz template page is configured (LearnKit → Settings → Template Pages),
 * returns the rewrite-based URL (e.g. /quiz/quiz-slug/) so users land on the
 * theme-wrapped page rather than the raw CPT URL.
 * Falls back to get_permalink() when no template page is configured.
 *
 * @since  0.8.0
 * @param  int $quiz_id Quiz post ID.
 * @return string       Absolute URL.
 */
function learnkit_quiz_url( $quiz_id ) {
	$quiz_page_id = get_option( 'learnkit_quiz_page' );
	if ( $quiz_page_id ) {
		static $quiz_base = null;
		if ( null === $quiz_base ) {
			$quiz_base = LearnKit_Rewrite::get_base( 'learnkit_quiz_page' ) ?? '';
		}
		if ( $quiz_base ) {
			$slug = get_post_field( 'post_name', $quiz_id );
			return home_url( $quiz_base . '/' . $slug . '/' );
		}
	}
	return get_permalink( $quiz_id );
}

/**
 * Get the front-end URL for a lesson post.
 *
 * @since  0.8.0
 * @param  int $lesson_id Lesson post ID.
 * @return string         Absolute URL.
 */
function learnkit_lesson_url( $lesson_id ) {
	$lesson_page_id = get_option( 'learnkit_lesson_page' );
	if ( $lesson_page_id ) {
		static $lesson_base = null;
		if ( null === $lesson_base ) {
			$lesson_base = LearnKit_Rewrite::get_base( 'learnkit_lesson_page' ) ?? '';
		}
		if ( $lesson_base ) {
			$slug = get_post_field( 'post_name', $lesson_id );
			return home_url( $lesson_base . '/' . $slug . '/' );
		}
	}
	return get_permalink( $lesson_id );
}

/**
 * Get the front-end URL for a course post.
 *
 * @since  0.8.0
 * @param  int $course_id Course post ID.
 * @return string         Absolute URL.
 */
function learnkit_course_url( $course_id ) {
	$course_page_id = get_option( 'learnkit_course_page' );
	if ( $course_page_id ) {
		static $course_base = null;
		if ( null === $course_base ) {
			$course_base = LearnKit_Rewrite::get_base( 'learnkit_course_page' ) ?? '';
		}
		if ( $course_base ) {
			$slug = get_post_field( 'post_name', $course_id );
			return home_url( $course_base . '/' . $slug . '/' );
		}
	}
	return get_permalink( $course_id );
}
