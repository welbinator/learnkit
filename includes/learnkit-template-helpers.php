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

	return implode( ' ', array_unique( $classes ) );
}
