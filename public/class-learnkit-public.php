<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueuing
 * the public-facing stylesheet and JavaScript.
 *
 * @package    LearnKit
 * @subpackage LearnKit/public
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @param    string $plugin_name    The name of the plugin.
	 * @param    string $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Hook template loading.
		add_filter( 'single_template', array( $this, 'load_lesson_template' ) );

		// Hook quiz submission.
		add_action( 'template_redirect', array( $this, 'handle_quiz_submission' ) );
	}

	/**
	 * Load custom template for single lessons and courses.
	 *
	 * @since    0.2.13
	 * @param    string $template    The path to the template.
	 * @return   string             Modified template path.
	 */
	public function load_lesson_template( $template ) {
		if ( is_singular( 'lk_lesson' ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-lk-lesson.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		if ( is_singular( 'lk_course' ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-lk-course.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		if ( is_singular( 'lk_quiz' ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-lk-quiz.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_styles() {
		// Only load on LearnKit pages (courses, lessons).
		if ( ! is_singular( array( 'lk_course', 'lk_module', 'lk_lesson' ) ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			LEARNKIT_PLUGIN_URL . 'assets/css/learnkit-public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {
		// Only load on LearnKit pages.
		if ( ! is_singular( array( 'lk_course', 'lk_module', 'lk_lesson' ) ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			LEARNKIT_PLUGIN_URL . 'assets/js/learnkit-public.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Pass data to JavaScript for AJAX and API calls.
		wp_localize_script(
			$this->plugin_name,
			'learnkitPublic',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'apiUrl'      => rest_url( 'learnkit/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentUser' => get_current_user_id(),
				'isLoggedIn'  => is_user_logged_in(),
			)
		);
	}

	/**
	 * Handle quiz submission form POST.
	 *
	 * @since    0.4.0
	 * @return   void
	 */
	public function handle_quiz_submission() {
		if ( ! is_singular( 'lk_quiz' ) || ! isset( $_POST['learnkit_quiz_nonce'] ) ) {
			return;
		}

		$quiz_id = get_the_ID();

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['learnkit_quiz_nonce'] ) ), 'learnkit_submit_quiz_' . $quiz_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'learnkit' ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( esc_html__( 'You must be logged in to submit a quiz', 'learnkit' ) );
		}

		// Get quiz data.
		$questions_json = get_post_meta( $quiz_id, '_lk_questions', true );
		$questions      = json_decode( $questions_json, true );
		$passing_score  = (int) get_post_meta( $quiz_id, '_lk_passing_score', true );
		$passing_score  = $passing_score > 0 ? $passing_score : 70;
		$start_time     = isset( $_POST['start_time'] ) ? (int) $_POST['start_time'] : time();
		$time_taken     = time() - $start_time;

		// Collect answers.
		$answers = array();
		foreach ( $questions as $question ) {
			$field_name = 'question_' . $question['id'];
			if ( isset( $_POST[ $field_name ] ) ) {
				$answers[ $question['id'] ] = (int) $_POST[ $field_name ];
			}
		}

		// Grade the quiz.
		$score         = 0;
		$max_score     = 0;
		$correct_count = 0;

		foreach ( $questions as $question ) {
			$max_score += (int) $question['points'];

			$user_answer    = isset( $answers[ $question['id'] ] ) ? $answers[ $question['id'] ] : -1;
			$correct_answer = (int) $question['correctAnswer'];

			if ( $user_answer === $correct_answer ) {
				$score += (int) $question['points'];
				++$correct_count;
			}
		}

		$score_percentage = $max_score > 0 ? round( ( $score / $max_score ) * 100 ) : 0;
		$passed           = $score_percentage >= $passing_score;

		// Save attempt to database.
		global $wpdb;
		$table_name = $wpdb->prefix . 'learnkit_quiz_attempts';

		$wpdb->insert(
			$table_name,
			array(
				'user_id'      => $user_id,
				'quiz_id'      => $quiz_id,
				'score'        => $score_percentage,
				'passed'       => $passed ? 1 : 0,
				'answers'      => wp_json_encode( $answers ),
				'completed_at' => current_time( 'mysql' ),
				'time_taken'   => $time_taken,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d' )
		);

		// Redirect to results page with query params.
		$redirect_url = add_query_arg(
			array(
				'quiz_result' => 'submitted',
				'score'       => $score_percentage,
				'passed'      => $passed ? '1' : '0',
			),
			get_permalink( $quiz_id )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
