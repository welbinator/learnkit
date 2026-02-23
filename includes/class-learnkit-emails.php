<?php
/**
 * Email notification system for LearnKit
 *
 * @link       https://jameswelbes.com
 * @since      0.5.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnKit Emails class.
 *
 * Handles all outgoing email notifications using wp_mail().
 * Supports HTML + plain text multipart, per-user opt-out preferences,
 * and unsubscribe token validation.
 *
 * @since      0.5.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Emails {

	/**
	 * Whether a LearnKit email is currently being sent.
	 *
	 * Used to scope wp_mail_from / wp_mail_from_name filters so they only
	 * apply to outgoing LearnKit emails, not all site emails.
	 *
	 * @since 0.5.1
	 * @var   bool
	 */
	private static $is_sending = false;

	/**
	 * Set up hooks for unsubscribe handling and from-name/email overrides.
	 *
	 * @since 0.5.0
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_unsubscribe' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'filter_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_mail_from_name' ) );
	}

	/**
	 * Override the from email using plugin settings.
	 *
	 * @since  0.5.0
	 * @param  string $from Default from email.
	 * @return string       Filtered from email.
	 */
	public static function filter_mail_from( $from ) {
		if ( ! self::$is_sending ) {
			return $from;
		}
		$settings = get_option( 'learnkit_email_settings', array() );
		if ( ! empty( $settings['from_email'] ) ) {
			return sanitize_email( $settings['from_email'] );
		}
		return $from;
	}

	/**
	 * Override the from name using plugin settings.
	 *
	 * @since  0.5.0
	 * @param  string $name Default from name.
	 * @return string       Filtered from name.
	 */
	public static function filter_mail_from_name( $name ) {
		if ( ! self::$is_sending ) {
			return $name;
		}
		$settings = get_option( 'learnkit_email_settings', array() );
		if ( ! empty( $settings['from_name'] ) ) {
			return sanitize_text_field( $settings['from_name'] );
		}
		return $name;
	}

	// -------------------------------------------------------------------------
	// Queue helpers
	// -------------------------------------------------------------------------

	/**
	 * Queue a welcome email on enrollment.
	 *
	 * @since  0.5.0
	 * @param  int $user_id   WordPress user ID.
	 * @param  int $course_id Course post ID.
	 */
	public static function schedule_welcome_email( $user_id, $course_id ) {
		$settings = get_option( 'learnkit_email_settings', array() );
		if ( isset( $settings['welcome_enabled'] ) && ! $settings['welcome_enabled'] ) {
			return;
		}
		self::queue_email( $user_id, $course_id, 'welcome', gmdate( 'Y-m-d H:i:s' ), array() );
	}

	/**
	 * Queue a completion email when a course is finished.
	 *
	 * @since  0.5.0
	 * @param  int $user_id   WordPress user ID.
	 * @param  int $course_id Course post ID.
	 */
	public static function schedule_completion_email( $user_id, $course_id ) {
		self::queue_email( $user_id, $course_id, 'completion', gmdate( 'Y-m-d H:i:s' ), array() );
	}

	/**
	 * Insert a row into wp_learnkit_email_queue.
	 *
	 * @since  0.5.0
	 * @param  int    $user_id      WordPress user ID.
	 * @param  int    $course_id    Course post ID.
	 * @param  string $email_type   One of: welcome|lesson_unlock|reminder|completion.
	 * @param  string $scheduled_at MySQL datetime string.
	 * @param  array  $payload      Extra data (lesson_id, etc.).
	 */
	public static function queue_email( $user_id, $course_id, $email_type, $scheduled_at, $payload = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_email_queue';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'user_id'      => (int) $user_id,
				'course_id'    => (int) $course_id,
				'email_type'   => sanitize_key( $email_type ),
				'scheduled_at' => $scheduled_at,
				'status'       => 'pending',
				'payload'      => wp_json_encode( $payload ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	// -------------------------------------------------------------------------
	// Send dispatcher
	// -------------------------------------------------------------------------

	/**
	 * Send an email from a queue item object.
	 *
	 * @since  0.5.0
	 * @param  object $queue_item Row from wp_learnkit_email_queue.
	 * @return bool               True on success, false on failure.
	 */
	public static function send( $queue_item ) {
		$user_id    = (int) $queue_item->user_id;
		$course_id  = (int) $queue_item->course_id;
		$email_type = $queue_item->email_type;
		$payload    = json_decode( $queue_item->payload, true );

		// Check opt-out preference.
		if ( ! self::is_email_enabled_for_user( $user_id, $email_type ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$course = get_post( $course_id );
		if ( ! $course ) {
			return false;
		}

		$course_name = $course->post_title;

		switch ( $email_type ) {
			case 'welcome':
				return self::send_welcome( $user, $course_name, $course_id );
			case 'lesson_unlock':
				$lesson_id = isset( $payload['lesson_id'] ) ? (int) $payload['lesson_id'] : 0;
				return self::send_lesson_unlock( $user, $course_name, $course_id, $lesson_id );
			case 'reminder':
				return self::send_reminder( $user, $course_name, $course_id );
			case 'completion':
				return self::send_completion( $user, $course_name, $course_id );
			default:
				return false;
		}
	}

	// -------------------------------------------------------------------------
	// Individual send methods
	// -------------------------------------------------------------------------

	/**
	 * Send welcome email.
	 *
	 * @since  0.5.0
	 * @param  WP_User $user        Recipient.
	 * @param  string  $course_name Course title.
	 * @param  int     $course_id   Course post ID.
	 * @return bool
	 */
	private static function send_welcome( $user, $course_name, $course_id ) {
		$subject = sprintf(
			/* translators: %s: course name */
			__( 'Welcome to %s!', 'learnkit' ),
			$course_name
		);

		// Get first lesson.
		$first_lesson_url = self::get_first_lesson_url( $course_id );

		$html_body = self::wrap_html(
			$course_name,
			sprintf(
				/* translators: %s: user display name */
				'<p>' . esc_html__( 'Hi %s,', 'learnkit' ) . '</p>
				<p>' . sprintf(
					/* translators: %s: course name */
					esc_html__( 'Welcome to %s! We\'re so glad you\'re here.', 'learnkit' ),
					esc_html( $course_name )
				) . '</p>
				<p>' . esc_html__( 'Get started with your first lesson:', 'learnkit' ) . '</p>
				<p><a href="' . esc_url( $first_lesson_url ? $first_lesson_url : get_permalink( $course_id ) ) . '" class="button">'
					. esc_html__( 'Start Learning', 'learnkit' ) . '</a></p>',
				esc_html( $user->display_name )
			),
			$user->ID,
			'welcome'
		);

		return self::mail( $user->user_email, $subject, $html_body );
	}

	/**
	 * Send lesson unlock notification.
	 *
	 * @since  0.5.0
	 * @param  WP_User $user        Recipient.
	 * @param  string  $course_name Course title.
	 * @param  int     $course_id   Course post ID.
	 * @param  int     $lesson_id   Lesson post ID.
	 * @return bool
	 */
	private static function send_lesson_unlock( $user, $course_name, $course_id, $lesson_id ) {
		$lesson      = $lesson_id ? get_post( $lesson_id ) : null;
		$lesson_name = $lesson ? $lesson->post_title : __( 'New Lesson', 'learnkit' );
		$lesson_url  = $lesson ? get_permalink( $lesson_id ) : get_permalink( $course_id );

		$subject = sprintf(
			/* translators: %s: lesson name */
			__( 'New lesson available: %s', 'learnkit' ),
			$lesson_name
		);

		$html_body = self::wrap_html(
			$course_name,
			'<p>' . sprintf(
				/* translators: %s: user display name */
				esc_html__( 'Hi %s,', 'learnkit' ),
				esc_html( $user->display_name )
			) . '</p>
			<p>' . esc_html__( 'A new lesson is now available for you:', 'learnkit' ) . '</p>
			<h3>' . esc_html( $lesson_name ) . '</h3>
			<p><a href="' . esc_url( $lesson_url ) . '" class="button">'
				. esc_html__( 'View Lesson', 'learnkit' ) . '</a></p>',
			$user->ID,
			'lesson_unlock'
		);

		return self::mail( $user->user_email, $subject, $html_body );
	}

	/**
	 * Send reminder email.
	 *
	 * @since  0.5.0
	 * @param  WP_User $user        Recipient.
	 * @param  string  $course_name Course title.
	 * @param  int     $course_id   Course post ID.
	 * @return bool
	 */
	private static function send_reminder( $user, $course_name, $course_id ) {
		$subject = __( 'Continue your learning journey', 'learnkit' );

		$first_lesson_url = self::get_first_lesson_url( $course_id );

		$html_body = self::wrap_html(
			$course_name,
			'<p>' . sprintf(
				/* translators: %s: user display name */
				esc_html__( 'Hi %s,', 'learnkit' ),
				esc_html( $user->display_name )
			) . '</p>
			<p>' . sprintf(
				/* translators: %s: course name */
				esc_html__( "We noticed you haven't been active in %s lately. Ready to continue?", 'learnkit' ),
				esc_html( $course_name )
			) . '</p>
			<p><a href="' . esc_url( $first_lesson_url ? $first_lesson_url : get_permalink( $course_id ) ) . '" class="button">'
				. esc_html__( 'Continue Learning', 'learnkit' ) . '</a></p>',
			$user->ID,
			'reminder'
		);

		return self::mail( $user->user_email, $subject, $html_body );
	}

	/**
	 * Send course completion email.
	 *
	 * @since  0.5.0
	 * @param  WP_User $user        Recipient.
	 * @param  string  $course_name Course title.
	 * @param  int     $course_id   Course post ID.
	 * @return bool
	 */
	private static function send_completion( $user, $course_name, $course_id ) {
		$subject = sprintf(
			/* translators: %s: course name */
			__( 'Congratulations! You completed %s', 'learnkit' ),
			$course_name
		);

		$html_body = self::wrap_html(
			$course_name,
			'<p>' . sprintf(
				/* translators: %s: user display name */
				esc_html__( 'Hi %s,', 'learnkit' ),
				esc_html( $user->display_name )
			) . '</p>
			<p><strong>' . sprintf(
				/* translators: %s: course name */
				esc_html__( 'Congratulations on completing %s! 🎉', 'learnkit' ),
				esc_html( $course_name )
			) . '</strong></p>
			<p>' . esc_html__( 'A certificate of completion is available for download from your student dashboard.', 'learnkit' ) . '</p>
			<p><a href="' . esc_url( get_permalink( $course_id ) ) . '" class="button">'
				. esc_html__( 'View Course', 'learnkit' ) . '</a></p>',
			$user->ID,
			'completion'
		);

		return self::mail( $user->user_email, $subject, $html_body );
	}

	// -------------------------------------------------------------------------
	// Preferences / unsubscribe
	// -------------------------------------------------------------------------

	/**
	 * Handle the unsubscribe query var on init.
	 *
	 * @since 0.5.0
	 */
	public static function handle_unsubscribe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['learnkit_unsubscribe'] ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$user_id    = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$email_type = isset( $_GET['email_type'] ) ? sanitize_key( $_GET['email_type'] ) : '';
		$token      = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $user_id || ! $email_type || ! $token ) {
			return;
		}

		// Validate token.
		if ( ! hash_equals( wp_hash( $user_id . $email_type ), $token ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'learnkit_email_preferences';

		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'user_id'    => $user_id,
				'email_type' => $email_type,
				'enabled'    => 0,
			),
			array( '%d', '%s', '%d' )
		);

		wp_die(
			esc_html__( 'You have been unsubscribed from this notification. You can manage your email preferences from your profile.', 'learnkit' ),
			esc_html__( 'Unsubscribed', 'learnkit' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Check whether a given email type is enabled for a user.
	 *
	 * @since  0.5.0
	 * @param  int    $user_id    WordPress user ID.
	 * @param  string $email_type Email type key.
	 * @return bool               True if not opted-out, false if opted-out.
	 */
	public static function is_email_enabled_for_user( $user_id, $email_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_email_preferences';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT enabled FROM %i WHERE user_id = %d AND email_type = %s LIMIT 1',
				$table,
				$user_id,
				$email_type
			)
		);

		if ( null === $row ) {
			// No record means email is enabled by default.
			return true;
		}

		return (bool) $row->enabled;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build an unsubscribe URL for a user / email type.
	 *
	 * @since  0.5.0
	 * @param  int    $user_id    WordPress user ID.
	 * @param  string $email_type Email type key.
	 * @return string             URL.
	 */
	public static function get_unsubscribe_url( $user_id, $email_type ) {
		return add_query_arg(
			array(
				'learnkit_unsubscribe' => 1,
				'email_type'           => rawurlencode( $email_type ),
				'user_id'              => rawurlencode( (string) $user_id ),
				'token'                => rawurlencode( wp_hash( $user_id . $email_type ) ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Get URL to the first lesson of a course.
	 *
	 * @since  0.5.0
	 * @param  int $course_id Course post ID.
	 * @return string|null    URL or null.
	 */
	private static function get_first_lesson_url( $course_id ) {
		$modules = get_posts(
			array(
				'post_type'      => 'lk_module',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_key'       => '_lk_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (int) $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		if ( empty( $modules ) ) {
			return null;
		}

		$lessons = get_posts(
			array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_key'       => '_lk_module_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $modules[0]->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		if ( empty( $lessons ) ) {
			return null;
		}

		return get_permalink( $lessons[0]->ID );
	}

	/**
	 * Wrap HTML body content in a standard email template.
	 *
	 * @since  0.5.0
	 * @param  string $course_name   Course title for the header.
	 * @param  string $body_html     Inner HTML (already escaped).
	 * @param  int    $user_id       WordPress user ID.
	 * @param  string $email_type    Email type key.
	 * @return string                Full HTML email.
	 */
	private static function wrap_html( $course_name, $body_html, $user_id, $email_type ) {
		$unsubscribe_url = self::get_unsubscribe_url( $user_id, $email_type );

		$unsubscribe_text = sprintf(
			/* translators: %s: unsubscribe URL */
			__( '<a href="%s">Unsubscribe</a> from this notification.', 'learnkit' ),
			esc_url( $unsubscribe_url )
		);

		return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f4f5;margin:0;padding:0}
.wrap{max-width:600px;margin:32px auto;background:#ffffff;border-radius:8px;overflow:hidden}
.header{background:#2271b1;padding:24px 32px;color:#ffffff}
.header h2{margin:0;font-size:20px}
.body{padding:32px}
.body p{line-height:1.6;color:#333}
.button{display:inline-block;background:#2271b1;color:#ffffff;padding:12px 24px;border-radius:4px;text-decoration:none;font-weight:600}
.footer{padding:16px 32px;background:#f4f4f5;text-align:center;font-size:12px;color:#888}
.footer a{color:#888}
</style>
</head>
<body>
<div class="wrap">
<div class="header"><h2>' . esc_html( $course_name ) . '</h2></div>
<div class="body">' . $body_html . '</div>
<div class="footer">
<p>' . $unsubscribe_text . '</p>
</div>
</div>
</body>
</html>';
	}

	/**
	 * Send an email with an HTML body.
	 *
	 * @since  0.5.0
	 * @param  string $to        Recipient address.
	 * @param  string $subject   Email subject.
	 * @param  string $html_body Full HTML email.
	 * @return bool
	 */
	private static function mail( $to, $subject, $html_body ) {
		// Set content type to HTML for this message.
		$content_type_filter = static function () {
			return 'text/html';
		};
		add_filter( 'wp_mail_content_type', $content_type_filter );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		self::$is_sending = true;
		try {
			$result = wp_mail( $to, $subject, $html_body, $headers );
		} finally {
			self::$is_sending = false;
		}

		remove_filter( 'wp_mail_content_type', $content_type_filter );

		return $result;
	}
}
