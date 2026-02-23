<?php
/**
 * Certificate Generator
 *
 * Generates PDF certificates for course completion.
 *
 * @link       https://jameswelbes.com
 * @since      0.3.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/public
 */

/**
 * Certificate Generator functionality.
 *
 * @since      0.3.0
 * @package    LearnKit
 * @subpackage LearnKit/public
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Certificate_Generator {

	/**
	 * Register hooks.
	 *
	 * @since    0.3.0
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle_certificate_download' ) );
	}

	/**
	 * Handle certificate download requests.
	 *
	 * @since    0.3.0
	 */
	public function handle_certificate_download() {
		if ( ! isset( $_GET['download_certificate'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( get_permalink() ) );
			exit;
		}

		$course_id = intval( $_GET['download_certificate'] );

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'learnkit_certificate_' . $course_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'learnkit' ) );
		}

		$user_id = get_current_user_id();

		// Verify user is enrolled.
		if ( ! $this->is_user_enrolled( $user_id, $course_id ) ) {
			wp_die( esc_html__( 'You must be enrolled in this course to download a certificate.', 'learnkit' ) );
		}

		// Verify course is complete.
		$progress_data = learnkit_get_course_progress( $user_id, $course_id );
		if ( 100 !== $progress_data['progress_percent'] ) {
			wp_die( esc_html__( 'You must complete all lessons to download a certificate.', 'learnkit' ) );
		}

		// Generate and output certificate.
		$this->generate_certificate( $user_id, $course_id );
		exit;
	}

	/**
	 * Check if user is enrolled in course.
	 *
	 * @since    0.3.0
	 * @param    int $user_id User ID.
	 * @param    int $course_id Course ID.
	 * @return   bool True if enrolled.
	 */
	private function is_user_enrolled( $user_id, $course_id ) {
		global $wpdb;
		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$enrollment = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d AND course_id = %d AND status = \'active\'',
				$enrollments_table,
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return ! empty( $enrollment );
	}

	/**
	 * Generate PDF certificate.
	 *
	 * @since    0.3.0
	 * @param    int $user_id User ID.
	 * @param    int $course_id Course ID.
	 */
	private function generate_certificate( $user_id, $course_id ) {
		$user   = get_userdata( $user_id );
		$course = get_post( $course_id );

		if ( ! $user || ! $course ) {
			return;
		}

		$completion_date = $this->get_course_completion_date( $user_id, $course_id );
		$this->render_certificate_pdf( $user, $course, $completion_date );
	}

	/**
	 * Get the formatted completion date for a user's course.
	 *
	 * Looks up the most recent lesson completion timestamp for lessons
	 * belonging to the given course. Falls back to the user's most recent
	 * completion, then to today if no record is found.
	 *
	 * @since    0.3.0
	 * @param    int $user_id   WordPress user ID.
	 * @param    int $course_id Course post ID.
	 * @return   string         Human-readable date string, e.g. "January 1, 2025".
	 */
	private function get_course_completion_date( $user_id, $course_id ) {
		global $wpdb;
		$progress_table = $wpdb->prefix . 'learnkit_progress';

		// Get module IDs for this course.
		$module_ids = get_posts(
			array(
				'post_type'              => 'lk_module',
				'posts_per_page'         => -1,
				'meta_key'               => '_lk_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		// Get lesson IDs for those modules.
		$lesson_ids = array();
		if ( ! empty( $module_ids ) ) {
			$course_lessons = get_posts(
				array(
					'post_type'              => 'lk_lesson',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_lk_module_id',
							'value'   => $module_ids,
							'compare' => 'IN',
						),
					),
				)
			);
			$lesson_ids = $course_lessons;
		}

		$completion_date = null;

		if ( ! empty( $lesson_ids ) ) {
			$placeholders_date = implode( ',', array_fill( 0, count( $lesson_ids ), '%d' ) );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$completion_date = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT MAX(completed_at) FROM %i WHERE user_id = %d AND lesson_id IN (' . $placeholders_date . ')',
					array_merge( array( $progress_table, $user_id ), $lesson_ids )
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// Graceful degradation: no lessons found, fall back to user's most recent completion.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$completion_date = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(completed_at) FROM $progress_table WHERE user_id = %d",
					$user_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( ! $completion_date ) {
			$completion_date = gmdate( 'Y-m-d H:i:s' );
		}

		return gmdate( 'F j, Y', strtotime( $completion_date ) );
	}

	/**
	 * Build and output a PDF certificate for a user and course.
	 *
	 * Outputs binary PDF content directly to the browser with a
	 * Content-Disposition: attachment header, then returns.
	 *
	 * @since    0.3.0
	 * @param    WP_User $user             WordPress user object.
	 * @param    WP_Post $course           Course post object.
	 * @param    string  $completion_date  Formatted completion date string.
	 */
	private function render_certificate_pdf( $user, $course, $completion_date ) {
		// Create PDF.
		$pdf = new FPDF( 'L', 'mm', 'A4' );
		$pdf->AddPage();
		$pdf->SetAutoPageBreak( false );

		// Add decorative border.
		$pdf->SetLineWidth( 1 );
		$pdf->SetDrawColor( 41, 113, 177 );
		$pdf->Rect( 10, 10, 277, 190 );
		$pdf->SetLineWidth( 0.5 );
		$pdf->Rect( 12, 12, 273, 186 );

		// Title - Certificate of Completion.
		$pdf->SetFont( 'Arial', 'B', 36 );
		$pdf->SetTextColor( 41, 113, 177 );
		$pdf->SetXY( 10, 40 );
		$pdf->Cell( 277, 20, 'Certificate of Completion', 0, 1, 'C' );

		// Presented to.
		$pdf->SetFont( 'Arial', 'I', 16 );
		$pdf->SetTextColor( 100, 100, 100 );
		$pdf->SetXY( 10, 75 );
		$pdf->Cell( 277, 10, 'This certificate is proudly presented to', 0, 1, 'C' );

		// Student name.
		$pdf->SetFont( 'Arial', 'B', 28 );
		$pdf->SetTextColor( 29, 35, 39 );
		$pdf->SetXY( 10, 90 );
		$pdf->Cell( 277, 15, $user->display_name, 0, 1, 'C' );

		// For completing.
		$pdf->SetFont( 'Arial', 'I', 14 );
		$pdf->SetTextColor( 100, 100, 100 );
		$pdf->SetXY( 10, 115 );
		$pdf->Cell( 277, 8, 'for successfully completing', 0, 1, 'C' );

		// Course name.
		$pdf->SetFont( 'Arial', 'B', 20 );
		$pdf->SetTextColor( 29, 35, 39 );
		$pdf->SetXY( 10, 128 );
		$pdf->MultiCell( 277, 10, $course->post_title, 0, 'C' );

		// Completion date.
		$pdf->SetFont( 'Arial', '', 12 );
		$pdf->SetTextColor( 100, 100, 100 );
		$pdf->SetXY( 10, 165 );
		$pdf->Cell( 277, 8, 'Completed on ' . $completion_date, 0, 1, 'C' );

		// Site name footer.
		$pdf->SetFont( 'Arial', 'I', 10 );
		$pdf->SetXY( 10, 180 );
		$pdf->Cell( 277, 8, get_bloginfo( 'name' ), 0, 1, 'C' );

		// Output PDF.
		$filename = sanitize_file_name( $user->display_name . '-' . $course->post_title . '-certificate.pdf' );
		$pdf->Output( 'D', $filename );
	}
}
