<?php
/**
 * WooCommerce Integration for LearnKit
 *
 * Handles automatic enrollment/unenrollment when orders are completed,
 * refunded, or cancelled, as well as product data panels for linking
 * courses to WooCommerce products.
 *
 * Only loaded when WooCommerce is active.
 *
 * @link       https://jameswelbes.com
 * @since      0.4.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * LearnKit WooCommerce integration class.
 *
 * @since 0.4.0
 */
class LearnKit_WooCommerce {

	/**
	 * Register all WooCommerce hooks.
	 *
	 * @since 0.4.0
	 */
	public function register() {
		// Product data tab.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );

		// Product data panel.
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );

		// Save product meta.
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

		// Order lifecycle hooks.
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_completed' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_order_completed' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'handle_order_unenroll' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_order_unenroll' ) );

		// Course products archive — query var + filter.
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'woocommerce_product_query', array( $this, 'filter_products_by_course' ) );
	}

	/**
	 * Register the learnkit_course query var so WooCommerce archives accept it.
	 *
	 * @since 0.4.0
	 *
	 * @param array $vars Existing public query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'learnkit_course';
		return $vars;
	}

	/**
	 * Filter WooCommerce product archive queries by learnkit_course query var.
	 *
	 * When ?learnkit_course=<course_id> is present on the shop archive,
	 * only products that have that course ID in _learnkit_course_ids are shown.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Query $q The product query.
	 */
	public function filter_products_by_course( $q ) {
		$course_id = (int) get_query_var( 'learnkit_course' );
		if ( ! $course_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$meta_query   = (array) $q->get( 'meta_query' );
		$meta_query[] = array(
			'key'     => '_learnkit_course_ids',
			'value'   => '"' . $course_id . '"',
			'compare' => 'LIKE',
		);
		$q->set( 'meta_query', $meta_query );
	}

	/**
	 * Add a "LearnKit Courses" tab to the WooCommerce product data metabox.
	 *
	 * @since 0.4.0
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array Modified tabs.
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['learnkit'] = array(
			'label'    => __( 'LearnKit Courses', 'learnkit' ),
			'target'   => 'learnkit_course_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 70,
		);

		return $tabs;
	}

	/**
	 * Render the LearnKit product data panel.
	 *
	 * @since 0.4.0
	 */
	public function render_product_data_panel() {
		global $post;

		// Fetch all published courses.
		$courses = get_posts(
			array(
				'post_type'      => 'lk_course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$linked_course_ids = get_post_meta( $post->ID, '_learnkit_course_ids', true );
		if ( ! is_array( $linked_course_ids ) ) {
			$linked_course_ids = array();
		}
		$linked_course_ids = array_map( 'intval', $linked_course_ids );

		$access_days = (int) get_post_meta( $post->ID, '_learnkit_access_days', true );

		?>
		<div id="learnkit_course_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field">
					<label><?php esc_html_e( 'Link courses to this product', 'learnkit' ); ?></label>
					<?php if ( empty( $courses ) ) : ?>
						<span class="description">
							<?php esc_html_e( 'No published courses found. Create a course first.', 'learnkit' ); ?>
						</span>
					<?php else : ?>
						<span class="description" style="display:block;margin-bottom:8px;">
							<?php esc_html_e( 'Students will be enrolled in the selected courses when an order is completed.', 'learnkit' ); ?>
						</span>
						<select name="_learnkit_course_ids[]" id="_learnkit_course_ids" multiple="multiple"
							style="width: 100%; min-height: 100px;">
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course->ID ); ?>"
									<?php echo in_array( (int) $course->ID, $linked_course_ids, true ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $course->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</p>

				<p class="form-field">
					<label for="_learnkit_access_days">
						<?php esc_html_e( 'Access duration (days)', 'learnkit' ); ?>
					</label>
					<input type="number" name="_learnkit_access_days" id="_learnkit_access_days"
						value="<?php echo esc_attr( $access_days ); ?>"
						min="0" step="1" style="width:80px;" />
					<span class="description">
						<?php esc_html_e( '0 = lifetime access. Enter a positive number to expire enrollment after that many days.', 'learnkit' ); ?>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save LearnKit product meta when a product is saved.
	 *
	 * @since 0.4.0
	 *
	 * @param int $post_id Product post ID.
	 */
	public function save_product_meta( $post_id ) {
		// WooCommerce verifies the nonce ('woocommerce_meta_nonce') before firing
		// woocommerce_process_product_meta, so we do not need to re-verify here.

		// Course IDs — sanitize each element as an integer.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_ids    = isset( $_POST['_learnkit_course_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['_learnkit_course_ids'] ) ) : array();
		$course_ids = array_map( 'intval', $raw_ids );
		$course_ids = array_filter( $course_ids ); // Remove zeros.
		update_post_meta( $post_id, '_learnkit_course_ids', $course_ids );

		// Access days.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$access_days = isset( $_POST['_learnkit_access_days'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['_learnkit_access_days'] ) ) : 0;
		if ( $access_days < 0 ) {
			$access_days = 0;
		}
		update_post_meta( $post_id, '_learnkit_access_days', $access_days );
	}

	/**
	 * Enroll the customer in all linked courses when an order is completed.
	 *
	 * @since 0.4.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function handle_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( ! $user_id ) {
			// Guest checkout — cannot enroll without a WP user.
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id  = (int) $item->get_product_id();
			$course_ids  = get_post_meta( $product_id, '_learnkit_course_ids', true );
			$access_days = (int) get_post_meta( $product_id, '_learnkit_access_days', true );

			if ( ! is_array( $course_ids ) || empty( $course_ids ) ) {
				continue;
			}

			foreach ( $course_ids as $course_id ) {
				$course_id = (int) $course_id;
				if ( ! $course_id ) {
					continue;
				}

				$expires_at = '';
				if ( $access_days > 0 ) {
					$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$access_days} days" ) );
				}

				learnkit_enroll_user( $user_id, $course_id, 'woocommerce', $expires_at );
			}
		}
	}

	/**
	 * Unenroll the customer from all linked courses when an order is refunded or cancelled.
	 *
	 * @since 0.4.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function handle_order_unenroll( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			$course_ids = get_post_meta( $product_id, '_learnkit_course_ids', true );

			if ( ! is_array( $course_ids ) || empty( $course_ids ) ) {
				continue;
			}

			foreach ( $course_ids as $course_id ) {
				$course_id = (int) $course_id;
				if ( ! $course_id ) {
					continue;
				}

				learnkit_unenroll_user( $user_id, $course_id );
			}
		}
	}

	/**
	 * Find WooCommerce products linked to a given course.
	 *
	 * Returns all published products that have the course ID in their
	 * `_learnkit_course_ids` meta array.
	 *
	 * @since 0.4.0
	 *
	 * @param int $course_id The lk_course post ID.
	 * @return WC_Product[] Array of product objects (may be empty).
	 */
	public static function get_products_for_course( $course_id ) {
		$course_id = (int) $course_id;

		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => '_learnkit_course_ids',
						'value'   => '"' . $course_id . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'wc_get_product', $posts ) ) );
	}

	/**
	 * Returns the first published product for a course, or null if none.
	 * Kept for backward compatibility.
	 *
	 * @since 0.4.0
	 *
	 * @param int $course_id The lk_course post ID.
	 * @return WC_Product|null Product object or null.
	 */
	public static function get_product_for_course( $course_id ) {
		$products = self::get_products_for_course( $course_id );
		return ! empty( $products ) ? $products[0] : null;
	}

	/**
	 * Returns the URL for the course CTA button.
	 *
	 * Single product → direct product permalink.
	 * Multiple products → shop archive filtered by ?learnkit_course=<id>.
	 *
	 * @since 0.4.0
	 *
	 * @param int   $course_id The lk_course post ID.
	 * @param array $products  Array of WC_Product objects for this course.
	 * @return string URL.
	 */
	public static function get_course_cta_url( $course_id, $products ) {
		if ( 1 === count( $products ) ) {
			return $products[0]->get_permalink();
		}

		return add_query_arg( 'learnkit_course', (int) $course_id, wc_get_page_permalink( 'shop' ) );
	}

	/**
	 * Render the Buy Now / Enrolled-via-purchase CTA on the course page.
	 *
	 * Hooked into `learnkit_course_enrollment_cta` which is fired from the
	 * single-lk-course.php template.
	 *
	 * @since 0.4.0
	 *
	 * @param int  $course_id   The course post ID.
	 * @param int  $user_id     The current user ID.
	 * @param bool $is_enrolled Whether the user is enrolled.
	 */
	public static function render_course_cta( $course_id, $user_id, $is_enrolled ) {
		$products = self::get_products_for_course( $course_id );
		if ( empty( $products ) ) {
			return;
		}

		if ( $is_enrolled ) {
			// Check whether the enrollment originated from WooCommerce.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$source = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT source FROM {$wpdb->prefix}learnkit_enrollments WHERE user_id = %d AND course_id = %d AND status = 'active'",
					$user_id,
					$course_id
				)
			);

			if ( 'woocommerce' === $source ) {
				echo '<span class="lk-enrolled-badge lk-enrolled-badge--woo">';
				esc_html_e( 'Enrolled (via purchase)', 'learnkit' );
				echo '</span>';
			}

			return;
		}

		// User is NOT enrolled.
		// Single product → link directly. Multiple → link to filtered shop archive.
		$cta_url    = self::get_course_cta_url( $course_id, $products );
		$price_html = $products[0]->get_price_html();

		echo '<div class="lk-woo-cta">';
		if ( $price_html ) {
			echo '<span class="lk-woo-price">' . wp_kses_post( $price_html ) . '</span>';
		}
		echo '<a href="' . esc_url( $cta_url ) . '" class="lk-buy-now-button btn--primary">';
		esc_html_e( 'Buy Now', 'learnkit' );
		echo '</a>';
		echo '</div>';
	}
}
