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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		// Load on LearnKit CPT pages.
		$is_learnkit_page = is_singular( array( 'lk_course', 'lk_module', 'lk_lesson', 'lk_quiz' ) );

		// Also load on pages containing LearnKit shortcodes.
		if ( ! $is_learnkit_page && is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'learnkit_catalog' ) ) {
				$is_learnkit_page = true;
			}
			if ( $post && has_shortcode( $post->post_content, 'learnkit_dashboard' ) ) {
				$is_learnkit_page = true;
			}
		}

		if ( ! $is_learnkit_page ) {
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
		// Load on LearnKit CPT pages.
		$is_learnkit_page = is_singular( array( 'lk_course', 'lk_module', 'lk_lesson', 'lk_quiz' ) );

		// Also load on pages containing LearnKit shortcodes.
		if ( ! $is_learnkit_page && is_singular() ) {
			$post = get_post();
			if ( $post && ( has_shortcode( $post->post_content, 'learnkit_catalog' ) || has_shortcode( $post->post_content, 'learnkit_dashboard' ) ) ) {
				$is_learnkit_page = true;
			}
		}

		if ( ! $is_learnkit_page ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			LEARNKIT_PLUGIN_URL . 'assets/js/learnkit-public.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Pass data to JavaScript for API calls.
		wp_localize_script(
			$this->plugin_name,
			'learnkitPublic',
			array(
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

		$quiz_data     = $this->get_quiz_questions_and_settings( $quiz_id );
		$questions     = $quiz_data['questions'];
		$passing_score = $quiz_data['passing_score'];

		// Enforce attempt limits server-side (form POST path).
		global $wpdb;
		$attempts_allowed = (int) get_post_meta( $quiz_id, '_lk_attempts_allowed', true );
		if ( $attempts_allowed > 0 ) {
			$attempt_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API equivalent.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
					"SELECT COUNT(*) FROM {$wpdb->prefix}learnkit_quiz_attempts WHERE user_id = %d AND quiz_id = %d",
					$user_id,
					$quiz_id
				)
			);
			if ( $attempt_count >= $attempts_allowed ) {
				wp_die( esc_html__( 'You have reached the maximum number of attempts for this quiz.', 'learnkit' ) );
			}
		}

		$answers    = $this->collect_answers( $questions );
		$score_data = $this->grade_quiz( $questions, $answers, $passing_score );

		$this->save_quiz_attempt( $user_id, $quiz_id, $score_data, $answers );

		$result_token = wp_generate_password( 16, false );
		set_transient(
			'lk_quiz_result_' . $user_id . '_' . $quiz_id . '_' . $result_token,
			array(
				'score'         => $score_data['score_percentage'],
				'passed'        => $score_data['passed'],
				'correct_count' => isset( $score_data['correct_count'] ) ? $score_data['correct_count'] : 0,
				'total'         => isset( $score_data['total_questions'] ) ? $score_data['total_questions'] : count( $questions ),
			),
			300 // 5 minutes.
		);
		$redirect_url = add_query_arg(
			array(
				'quiz_result'  => 'submitted',
				'result_token' => $result_token,
			),
			get_permalink( $quiz_id )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Fetch quiz questions and passing score from post meta.
	 *
	 * @since    0.4.0
	 * @param    int $quiz_id Quiz post ID.
	 * @return   array { questions: array, passing_score: int }
	 */
	private function get_quiz_questions_and_settings( $quiz_id ) {
		$questions_json = get_post_meta( $quiz_id, '_lk_questions', true );
		$questions      = json_decode( $questions_json, true );
		$passing_score  = (int) get_post_meta( $quiz_id, '_lk_passing_score', true );
		$passing_score  = $passing_score > 0 ? $passing_score : 70;

		// Normalize questions: assign id and resolve correctAnswer index from string if needed.
		if ( is_array( $questions ) ) {
			foreach ( $questions as $idx => &$q ) {
				if ( ! isset( $q['id'] ) ) {
					$q['id'] = $idx;
				}
				if ( ! isset( $q['correctAnswer'] ) && ! isset( $q['correct'] ) ) {
					if ( isset( $q['correct_answer'] ) && isset( $q['options'] ) ) {
						$q['correctAnswer'] = (int) array_search( $q['correct_answer'], $q['options'], true );
					}
				}
			}
			unset( $q );
		}

		return array(
			'questions'     => $questions,
			'passing_score' => $passing_score,
		);
	}

	/**
	 * Collect user's answers from $_POST.
	 *
	 * @since    0.4.0
	 * @param    array $questions Array of question data.
	 * @return   array Map of question_id => selected answer index.
	 */
	private function collect_answers( $questions ) {
		$answers = array();
		foreach ( $questions as $question ) {
			$field_name = 'question_' . $question['id'];
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handle_quiz_submission() before this method is called.
			if ( isset( $_POST[ $field_name ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handle_quiz_submission() before this method is called.
				$answers[ $question['id'] ] = (int) $_POST[ $field_name ];
			}
		}
		return $answers;
	}

	/**
	 * Grade the quiz and return score data.
	 *
	 * @since    0.4.0
	 * @param    array $questions     Array of question data.
	 * @param    array $answers       Map of question_id => selected answer index.
	 * @param    int   $passing_score Passing score percentage threshold.
	 * @return   array { score_percentage: int, max_score: int, passed: bool }
	 */
	private function grade_quiz( $questions, $answers, $passing_score ) {
		$score     = 0;
		$max_score = 0;

		foreach ( $questions as $question ) {
			$max_score += (int) $question['points'];

			$user_answer    = isset( $answers[ $question['id'] ] ) ? $answers[ $question['id'] ] : -1;
			$correct_answer = isset( $question['correctAnswer'] ) ? (int) $question['correctAnswer'] : (int) $question['correct'];

			if ( $user_answer === $correct_answer ) {
				$score += (int) $question['points'];
			}
		}

		$score_percentage = $max_score > 0 ? round( ( $score / $max_score ) * 100 ) : 0;

		return array(
			'score_percentage' => $score_percentage,
			'max_score'        => $max_score,
			'passed'           => $score_percentage >= $passing_score,
		);
	}

	/**
	 * Save a quiz attempt to the database.
	 *
	 * @since    0.4.0
	 * @param    int   $user_id    User ID.
	 * @param    int   $quiz_id    Quiz post ID.
	 * @param    array $score_data Score data from grade_quiz().
	 * @param    array $answers    Collected answers from collect_answers().
	 */
	private function save_quiz_attempt( $user_id, $quiz_id, $score_data, $answers ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'learnkit_quiz_attempts';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table, no WP API equivalent.
			$table_name,
			array(
				'user_id'      => $user_id,
				'quiz_id'      => $quiz_id,
				'score'        => $score_data['score_percentage'],
				'max_score'    => $score_data['max_score'],
				'passed'       => $score_data['passed'] ? 1 : 0,
				'answers'      => wp_json_encode( $answers ),
				'completed_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Render a Purchase button for paid courses on the course page and catalog.
	 * If one WooCommerce product is linked, links directly to it.
	 * If multiple, links to the shop archive filtered by course.
	 *
	 * @since 0.7.0
	 * @param int  $course_id   The course post ID.
	 * @param int  $user_id     The current user ID (0 if not logged in).
	 * @param bool $is_enrolled Whether the user is enrolled.
	 * @return void
	 */
	public function render_purchase_cta( $course_id, $user_id, $is_enrolled ) {
		if ( $is_enrolled ) {
			return;
		}

		// Only run if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$access_type = get_post_meta( $course_id, '_lk_access_type', true );
		if ( 'paid' !== $access_type ) {
			return;
		}

		// Find WooCommerce products linked to this course.
		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_learnkit_course_id',
						'value' => $course_id,
					),
				),
			)
		);

		if ( empty( $product_ids ) ) {
			return;
		}

		if ( 1 === count( $product_ids ) ) {
			$url   = get_permalink( $product_ids[0] );
			$label = __( 'Purchase Course', 'learnkit' );
		} else {
			$url   = add_query_arg( 'learnkit_course', $course_id, get_permalink( wc_get_page_id( 'shop' ) ) );
			$label = __( 'View Purchase Options', 'learnkit' );
		}

		if ( ! $user_id ) {
			$url = wp_login_url( get_permalink( $course_id ) );
			$label = __( 'Login to Purchase', 'learnkit' );
		}

		echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( learnkit_button_classes( 'enroll_button', 'btn--lk-enroll' ) ) . '">' . esc_html( $label ) . '</a>';
	}
}
