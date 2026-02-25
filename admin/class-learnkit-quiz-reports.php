<?php
/**
 * Quiz Reports Admin Page
 *
 * @link       https://jameswelbes.com
 * @since      0.4.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/admin
 */

/**
 * LearnKit Quiz Reports class.
 *
 * Displays quiz analytics and student attempts.
 *
 * @since      0.4.0
 * @package    LearnKit
 * @subpackage LearnKit/admin
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Quiz_Reports {

	/**
	 * Register admin menu page.
	 *
	 * @since    0.4.0
	 */
	public function add_menu_page() {
		add_submenu_page(
			'learnkit',
			__( 'Quiz Reports', 'learnkit' ),
			__( 'Quiz Reports', 'learnkit' ),
			'manage_options',
			'learnkit-quiz-reports',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render quiz reports page.
	 *
	 * @since    0.4.0
	 */
	public function render_page() {
		// Get filter params. These are read-only display filters for an admin report page — no data is modified, so nonce verification is not required here.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$quiz_id    = isset( $_GET['quiz_id'] ) ? absint( $_GET['quiz_id'] ) : 0;
		$user_id    = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$passed     = isset( $_GET['passed'] ) ? sanitize_text_field( wp_unslash( $_GET['passed'] ) ) : '';
		$export_csv = isset( $_GET['export'] ) && 'csv' === $_GET['export'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$filters  = array(
			'quiz_id' => $quiz_id,
			'user_id' => $user_id,
			'passed'  => $passed,
		);
		$attempts = $this->get_quiz_attempts( $filters );

		// Export CSV.
		if ( $export_csv ) {
			$this->export_csv( $attempts );
			exit;
		}

		$this->render_page_header();

		// Get all quizzes for filter dropdown.
		$quizzes = get_posts(
			array(
				'post_type'      => 'lk_quiz',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$this->render_filters_form( $filters, $quizzes );
		$this->render_summary_stats( $attempts );
		$this->render_attempts_table( $attempts );

		echo '</div>'; // Close .wrap.
	}

	/**
	 * Fetch quiz attempts from the database using the given filters.
	 *
	 * Builds a parameterized query based on quiz_id, user_id, and passed
	 * filter values, then returns up to 500 rows ordered by completed_at DESC.
	 *
	 * @since    0.4.0
	 * @param    array $filters  Associative array with keys: quiz_id, user_id, passed.
	 * @return   array           Array of attempt objects.
	 */
	private function get_quiz_attempts( $filters ) {
		global $wpdb;

		$quiz_id = (int) $filters['quiz_id'];
		$user_id = (int) $filters['user_id'];
		$passed  = sanitize_text_field( $filters['passed'] );
		$table   = esc_sql( $wpdb->prefix . 'learnkit_quiz_attempts' );

		// Build query with proper parameterized WHERE clause.
		if ( $quiz_id && $user_id ) {
			if ( 'yes' === $passed ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
				$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE quiz_id = %d AND user_id = %d AND passed = 1 ORDER BY completed_at DESC LIMIT 500", $quiz_id, $user_id );
			} elseif ( 'no' === $passed ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
				$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE quiz_id = %d AND user_id = %d AND passed = 0 ORDER BY completed_at DESC LIMIT 500", $quiz_id, $user_id );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
				$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE quiz_id = %d AND user_id = %d ORDER BY completed_at DESC LIMIT 500", $quiz_id, $user_id );
			}
		} elseif ( $quiz_id ) {
			$sql = $this->build_single_filter_query( $table, 'quiz_id', $quiz_id, $passed );
		} elseif ( $user_id ) {
			$sql = $this->build_single_filter_query( $table, 'user_id', $user_id, $passed );
		} elseif ( 'yes' === $passed ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
			$sql = "SELECT * FROM {$table} WHERE passed = 1 ORDER BY completed_at DESC LIMIT 500";
		} elseif ( 'no' === $passed ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
			$sql = "SELECT * FROM {$table} WHERE passed = 0 ORDER BY completed_at DESC LIMIT 500";
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
			$sql = "SELECT * FROM {$table} ORDER BY completed_at DESC LIMIT 500";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built from esc_sql()'d table name and wpdb->prepare() calls only; $passed is never interpolated into SQL.
		return $wpdb->get_results( $sql );
	}

	/**
	 * Build a prepared SQL query filtering on a single column plus optional passed flag.
	 *
	 * @since    0.4.0
	 * @param    string $table   Prefixed table name.
	 * @param    string $column  Column name: 'quiz_id' or 'user_id'.
	 * @param    int    $value   Column value to filter by.
	 * @param    string $passed  Passed filter: 'yes', 'no', or '' for all.
	 * @return   string          Prepared SQL string.
	 */
	private function build_single_filter_query( $table, $column, $value, $passed ) {
		global $wpdb;

		if ( 'yes' === $passed ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and column are safely set.
			return $wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = %d AND passed = 1 ORDER BY completed_at DESC LIMIT 500", $value );
		}
		if ( 'no' === $passed ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and column are safely set.
			return $wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = %d AND passed = 0 ORDER BY completed_at DESC LIMIT 500", $value );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and column are safely set.
		return $wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = %d ORDER BY completed_at DESC LIMIT 500", $value );
	}

	/**
	 * Render page header.
	 *
	 * @since    0.4.0
	 */
	private function render_page_header() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Quiz Reports', 'learnkit' ) . '</h1>';
	}

	/**
	 * Render the filters form.
	 *
	 * @since    0.4.0
	 * @param    array $filters  Current filter values (quiz_id, user_id, passed).
	 * @param    array $quizzes  Array of quiz WP_Post objects for the dropdown.
	 */
	private function render_filters_form( $filters, $quizzes ) {
		$quiz_id = $filters['quiz_id'];
		$passed  = sanitize_text_field( $filters['passed'] );
		?>
		<!-- Filters -->
		<form method="get" class="lk-reports-filters" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px;">
			<input type="hidden" name="page" value="learnkit-quiz-reports">

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
				<div>
					<label for="quiz_id"><strong><?php esc_html_e( 'Quiz', 'learnkit' ); ?></strong></label><br>
					<select name="quiz_id" id="quiz_id" style="width: 100%;">
						<option value=""><?php esc_html_e( 'All Quizzes', 'learnkit' ); ?></option>
						<?php foreach ( $quizzes as $quiz ) : ?>
							<option value="<?php echo esc_attr( $quiz->ID ); ?>" <?php selected( $quiz_id, $quiz->ID ); ?>>
								<?php echo esc_html( $quiz->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="passed"><strong><?php esc_html_e( 'Result', 'learnkit' ); ?></strong></label><br>
					<select name="passed" id="passed" style="width: 100%;">
						<option value=""><?php esc_html_e( 'All Results', 'learnkit' ); ?></option>
						<option value="yes" <?php selected( $passed, 'yes' ); ?>><?php esc_html_e( 'Passed', 'learnkit' ); ?></option>
						<option value="no" <?php selected( $passed, 'no' ); ?>><?php esc_html_e( 'Failed', 'learnkit' ); ?></option>
					</select>
				</div>

				<div>
					<label><strong>&nbsp;</strong></label><br>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'learnkit' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=learnkit-quiz-reports' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'learnkit' ); ?></a>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Render summary statistics cards.
	 *
	 * @since    0.4.0
	 * @param    array $attempts  Array of attempt objects.
	 */
	private function render_summary_stats( $attempts ) {
		$total_attempts  = count( $attempts );
		$passed_attempts = 0;
		$total_score     = 0;

		foreach ( $attempts as $attempt ) {
			if ( $attempt->passed ) {
				$passed_attempts++;
			}
			$total_score += ( $attempt->score / $attempt->max_score ) * 100;
		}

		$pass_rate = $total_attempts > 0 ? round( ( $passed_attempts / $total_attempts ) * 100 ) : 0;
		$avg_score = $total_attempts > 0 ? round( $total_score / $total_attempts ) : 0;
		?>
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
			<div style="background: #fff; padding: 20px; border-radius: 8px; text-align: center;">
				<div style="font-size: 14px; color: #757575; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">
					<?php esc_html_e( 'Total Attempts', 'learnkit' ); ?>
				</div>
				<div style="font-size: 32px; font-weight: 700; color: #1d2327;">
					<?php echo esc_html( $total_attempts ); ?>
				</div>
			</div>

			<div style="background: #fff; padding: 20px; border-radius: 8px; text-align: center;">
				<div style="font-size: 14px; color: #757575; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">
					<?php esc_html_e( 'Pass Rate', 'learnkit' ); ?>
				</div>
				<div style="font-size: 32px; font-weight: 700; color: #1d2327;">
					<?php echo esc_html( $pass_rate ); ?>%
				</div>
			</div>

			<div style="background: #fff; padding: 20px; border-radius: 8px; text-align: center;">
				<div style="font-size: 14px; color: #757575; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">
					<?php esc_html_e( 'Average Score', 'learnkit' ); ?>
				</div>
				<div style="font-size: 32px; font-weight: 700; color: #1d2327;">
					<?php echo esc_html( $avg_score ); ?>%
				</div>
			</div>

			<div style="background: #fff; padding: 20px; border-radius: 8px; text-align: center;">
				<div style="font-size: 14px; color: #757575; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">
					<?php esc_html_e( 'Passed', 'learnkit' ); ?>
				</div>
				<div style="font-size: 32px; font-weight: 700; color: #28a745;">
					<?php echo esc_html( $passed_attempts ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the attempts table.
	 *
	 * @since    0.4.0
	 * @param    array $attempts  Array of attempt objects.
	 */
	private function render_attempts_table( $attempts ) {
		?>
		<!-- Export Button -->
		<div style="margin: 20px 0;">
			<a href="<?php echo esc_url( add_query_arg( 'export', 'csv' ) ); ?>" class="button">
				<?php esc_html_e( 'Export to CSV', 'learnkit' ); ?>
			</a>
		</div>

		<!-- Attempts Table -->
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Quiz', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Score', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Percentage', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Result', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Date', 'learnkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $attempts ) ) : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 40px; color: #757575;">
							<?php esc_html_e( 'No quiz attempts found.', 'learnkit' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $attempts as $attempt ) : ?>
						<?php
						$user       = get_user_by( 'id', $attempt->user_id );
						$quiz       = get_post( $attempt->quiz_id );
						$percentage = ( $attempt->score / $attempt->max_score ) * 100;
						?>
						<tr>
							<td>
								<?php echo $user ? esc_html( $user->display_name ) : 'Unknown User'; ?>
								<br><small><?php echo $user ? esc_html( $user->user_email ) : ''; ?></small>
							</td>
							<td><?php echo $quiz ? esc_html( $quiz->post_title ) : 'Deleted Quiz'; ?></td>
							<td><?php echo esc_html( $attempt->score ); ?> / <?php echo esc_html( $attempt->max_score ); ?></td>
							<td><?php echo esc_html( round( $percentage ) ); ?>%</td>
							<td>
								<?php if ( $attempt->passed ) : ?>
									<span style="color: #28a745; font-weight: 600;">✅ <?php esc_html_e( 'Passed', 'learnkit' ); ?></span>
								<?php else : ?>
									<span style="color: #dc3545; font-weight: 600;">❌ <?php esc_html_e( 'Failed', 'learnkit' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( wp_date( 'F j, Y g:i a', strtotime( $attempt->completed_at ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( count( $attempts ) >= 500 ) : ?>
			<p style="margin-top: 20px; color: #757575;">
				<?php esc_html_e( 'Showing first 500 attempts. Use filters to narrow results.', 'learnkit' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Export attempts to CSV.
	 *
	 * @since    0.4.0
	 * @param    array $attempts    Array of attempt objects.
	 */
	private function export_csv( $attempts ) {
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="quiz-reports-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		// Headers.
		fputcsv( $output, array( 'Student Name', 'Student Email', 'Quiz', 'Score', 'Max Score', 'Percentage', 'Passed', 'Date' ) );

		// Data.
		foreach ( $attempts as $attempt ) {
			$user       = get_user_by( 'id', $attempt->user_id );
			$quiz       = get_post( $attempt->quiz_id );
			$percentage = ( $attempt->score / $attempt->max_score ) * 100;

			fputcsv(
				$output,
				array(
					$user ? $user->display_name : 'Unknown',
					$user ? $user->user_email : '',
					$quiz ? $quiz->post_title : 'Deleted Quiz',
					$attempt->score,
					$attempt->max_score,
					round( $percentage ) . '%',
					$attempt->passed ? 'Yes' : 'No',
					wp_date( 'F j, Y g:i a', strtotime( $attempt->completed_at ) ),
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output is a PHP stream, not a filesystem file; WP_Filesystem is not applicable here.
	}
}
