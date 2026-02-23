<?php
/**
 * Register meta boxes for course/module relationships
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * Register meta boxes for course/module relationships.
 *
 * Provides admin UI for setting parent course (for modules) and
 * parent module (for lessons) using post meta instead of hierarchical
 * post_parent to avoid URL conflicts across CPT boundaries.
 *
 * @since      0.1.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Meta_Boxes {

	/**
	 * Register meta boxes for modules and lessons.
	 *
	 * @since    0.1.0
	 */
	public function add_meta_boxes() {
		// Module -> Course relationship.
		add_meta_box(
			'lk_module_course',
			__( 'Parent Course', 'learnkit' ),
			array( $this, 'render_module_course_meta_box' ),
			'lk_module',
			'side',
			'high'
		);

		// Lesson -> Module relationship.
		add_meta_box(
			'lk_lesson_module',
			__( 'Parent Module', 'learnkit' ),
			array( $this, 'render_lesson_module_meta_box' ),
			'lk_lesson',
			'side',
			'high'
		);
	}

	/**
	 * Render the module course meta box.
	 *
	 * Displays a dropdown to select the parent course for a module.
	 *
	 * @since    0.1.0
	 * @param    WP_Post $post    Current post object.
	 */
	public function render_module_course_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'lk_save_module_course', 'lk_module_course_nonce' );

		// Get current course ID.
		$course_id = get_post_meta( $post->ID, '_lk_course_id', true );

		// Get all courses.
		$courses = get_posts(
			array(
				'post_type'      => 'lk_course',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'future', 'private' ),
				'no_found_rows'  => true,
			)
		);

		echo '<label for="lk_course_id">';
		esc_html_e( 'Select Parent Course:', 'learnkit' );
		echo '</label> ';
		echo '<select name="lk_course_id" id="lk_course_id" class="widefat">';
		echo '<option value="0">' . esc_html__( '— Select —', 'learnkit' ) . '</option>';

		foreach ( $courses as $course ) {
			printf(
				'<option value="%d" %s>%s</option>',
				esc_attr( $course->ID ),
				selected( $course_id, $course->ID, false ),
				esc_html( $course->post_title )
			);
		}

		echo '</select>';
	}

	/**
	 * Render the lesson module meta box.
	 *
	 * Displays a dropdown to select the parent module for a lesson.
	 *
	 * @since    0.1.0
	 * @param    WP_Post $post    Current post object.
	 */
	public function render_lesson_module_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'lk_save_lesson_module', 'lk_lesson_module_nonce' );

		// Get current module ID.
		$module_id = get_post_meta( $post->ID, '_lk_module_id', true );

		// Get all modules.
		$modules = get_posts(
			array(
				'post_type'      => 'lk_module',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'future', 'private' ),
				'no_found_rows'  => true,
			)
		);

		echo '<label for="lk_module_id">';
		esc_html_e( 'Select Parent Module:', 'learnkit' );
		echo '</label> ';
		echo '<select name="lk_module_id" id="lk_module_id" class="widefat">';
		echo '<option value="0">' . esc_html__( '— Select —', 'learnkit' ) . '</option>';

		foreach ( $modules as $module ) {
			printf(
				'<option value="%d" %s>%s</option>',
				esc_attr( $module->ID ),
				selected( $module_id, $module->ID, false ),
				esc_html( $module->post_title )
			);
		}

		echo '</select>';
	}

	/**
	 * Save module course meta box data.
	 *
	 * @since    0.1.0
	 * @param    int $post_id    Post ID.
	 */
	public function save_module_meta( $post_id ) {
		// Check if nonce is set.
		if ( ! isset( $_POST['lk_module_course_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_key( $_POST['lk_module_course_nonce'] ), 'lk_save_module_course' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save or delete course ID.
		if ( isset( $_POST['lk_course_id'] ) ) {
			$course_id = absint( $_POST['lk_course_id'] );
			if ( $course_id > 0 ) {
				update_post_meta( $post_id, '_lk_course_id', $course_id );
			} else {
				delete_post_meta( $post_id, '_lk_course_id' );
			}
		}
	}

	/**
	 * Save lesson module meta box data.
	 *
	 * @since    0.1.0
	 * @param    int $post_id    Post ID.
	 */
	public function save_lesson_meta( $post_id ) {
		// Check if nonce is set.
		if ( ! isset( $_POST['lk_lesson_module_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_key( $_POST['lk_lesson_module_nonce'] ), 'lk_save_lesson_module' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save or delete module ID.
		if ( isset( $_POST['lk_module_id'] ) ) {
			$module_id = absint( $_POST['lk_module_id'] );
			if ( $module_id > 0 ) {
				update_post_meta( $post_id, '_lk_module_id', $module_id );
			} else {
				delete_post_meta( $post_id, '_lk_module_id' );
			}
		}
	}

	/**
	 * Add custom columns to module list table.
	 *
	 * @since    0.1.0
	 * @param    array $columns    Existing columns.
	 * @return   array             Modified columns.
	 */
	public function add_module_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['course'] = __( 'Course', 'learnkit' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate custom module columns.
	 *
	 * @since    0.1.0
	 * @param    string $column     Column name.
	 * @param    int    $post_id    Post ID.
	 */
	public function populate_module_columns( $column, $post_id ) {
		if ( 'course' === $column ) {
			$course_id = get_post_meta( $post_id, '_lk_course_id', true );
			if ( $course_id ) {
				$course = get_post( $course_id );
				if ( $course ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( get_edit_post_link( $course_id ) ),
						esc_html( $course->post_title )
					);
				} else {
					esc_html_e( '(Course not found)', 'learnkit' );
				}
			} else {
				echo '—';
			}
		}
	}

	/**
	 * Add custom columns to lesson list table.
	 *
	 * @since    0.1.0
	 * @param    array $columns    Existing columns.
	 * @return   array             Modified columns.
	 */
	public function add_lesson_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['module'] = __( 'Module', 'learnkit' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate custom lesson columns.
	 *
	 * @since    0.1.0
	 * @param    string $column     Column name.
	 * @param    int    $post_id    Post ID.
	 */
	public function populate_lesson_columns( $column, $post_id ) {
		if ( 'module' === $column ) {
			$module_id = get_post_meta( $post_id, '_lk_module_id', true );
			if ( $module_id ) {
				$module = get_post( $module_id );
				if ( $module ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( get_edit_post_link( $module_id ) ),
						esc_html( $module->post_title )
					);
				} else {
					esc_html_e( '(Module not found)', 'learnkit' );
				}
			} else {
				echo '—';
			}
		}
	}
}
