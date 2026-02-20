<?php
/**
 * Quiz Reports Admin Page
 *
 * @package LearnKit
 * @since   0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check user capabilities.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'learnkit' ) );
}

global $wpdb;
$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';

// Get all quiz attempts.
$attempts = $wpdb->get_results(
	"SELECT 
		a.*, 
		u.display_name as user_name,
		q.post_title as quiz_title
	FROM $attempts_table a
	LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
	LEFT JOIN {$wpdb->posts} q ON a.quiz_id = q.ID
	ORDER BY a.completed_at DESC
	LIMIT 100"
);

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Quiz Reports', 'learnkit' ); ?></h1>
	<p><?php esc_html_e( 'View student quiz attempts and scores', 'learnkit' ); ?></p>

	<?php if ( empty( $attempts ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No quiz attempts yet.', 'learnkit' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Quiz', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Score', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Result', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Time Taken', 'learnkit' ); ?></th>
					<th><?php esc_html_e( 'Date', 'learnkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $attempts as $attempt ) : ?>
					<tr>
						<td><?php echo esc_html( $attempt->user_name ); ?></td>
						<td><?php echo esc_html( $attempt->quiz_title ); ?></td>
						<td><strong><?php echo esc_html( $attempt->score ); ?>%</strong></td>
						<td>
							<?php if ( $attempt->passed ) : ?>
								<span style="color: #00a32a; font-weight: 600;">✓ <?php esc_html_e( 'Passed', 'learnkit' ); ?></span>
							<?php else : ?>
								<span style="color: #d63638; font-weight: 600;">✗ <?php esc_html_e( 'Failed', 'learnkit' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( $attempt->time_taken ) {
								$minutes = floor( $attempt->time_taken / 60 );
								$seconds = $attempt->time_taken % 60;
								echo esc_html( sprintf( '%d:%02d', $minutes, $seconds ) );
							} else {
								echo '—';
							}
							?>
						</td>
						<td><?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $attempt->completed_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<style>
	.wp-list-table {
		margin-top: 20px;
	}
	.wp-list-table th {
		font-weight: 600;
	}
	.wp-list-table td {
		vertical-align: middle;
	}
</style>
