<?php
/**
 * Migration script to move post_parent to meta fields
 *
 * Run this once to migrate existing modules and lessons from
 * hierarchical post_parent relationships to flat meta-based relationships.
 *
 * Usage: WP-CLI: wp eval-file includes/migrate-post-parent-to-meta.php
 * Or add to activator and run on plugin activation.
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- lk_ prefix is the plugin prefix; learnkit_ is not required.

/**
 * Migrate modules from post_parent to _lk_course_id meta.
 *
 * @return array Migration results.
 */
function lk_migrate_modules_to_meta() {
	$modules = get_posts(
		array(
			'post_type'      => 'lk_module',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'post_parent__not_in' => array( 0 ), // Only get modules with a parent set.
		)
	);

	$migrated = 0;
	$skipped  = 0;

	foreach ( $modules as $module ) {
		if ( $module->post_parent > 0 ) {
			// Check if parent is a course.
			$parent_post_type = get_post_type( $module->post_parent );
			if ( 'lk_course' === $parent_post_type ) {
				// Move post_parent to meta.
				update_post_meta( $module->ID, '_lk_course_id', $module->post_parent );

				// Clear post_parent.
				wp_update_post(
					array(
						'ID'          => $module->ID,
						'post_parent' => 0,
					)
				);

				$migrated++;
			} else {
				$skipped++;
			}
		}
	}

	return array(
		'type'     => 'modules',
		'migrated' => $migrated,
		'skipped'  => $skipped,
	);
}

/**
 * Migrate lessons from post_parent to _lk_module_id meta.
 *
 * @return array Migration results.
 */
function lk_migrate_lessons_to_meta() {
	$lessons = get_posts(
		array(
			'post_type'      => 'lk_lesson',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'post_parent__not_in' => array( 0 ), // Only get lessons with a parent set.
		)
	);

	$migrated = 0;
	$skipped  = 0;

	foreach ( $lessons as $lesson ) {
		if ( $lesson->post_parent > 0 ) {
			// Check if parent is a module.
			$parent_post_type = get_post_type( $lesson->post_parent );
			if ( 'lk_module' === $parent_post_type ) {
				// Move post_parent to meta.
				update_post_meta( $lesson->ID, '_lk_module_id', $lesson->post_parent );

				// Clear post_parent.
				wp_update_post(
					array(
						'ID'          => $lesson->ID,
						'post_parent' => 0,
					)
				);

				$migrated++;
			} else {
				$skipped++;
			}
		}
	}

	return array(
		'type'     => 'lessons',
		'migrated' => $migrated,
		'skipped'  => $skipped,
	);
}

/**
 * Run the migration.
 */
function lk_run_migration() {
	echo "LearnKit: Migrating post_parent to meta fields...\n\n";

	$module_results = lk_migrate_modules_to_meta();
	printf(
		"Modules: %d migrated, %d skipped\n",
		absint( $module_results['migrated'] ),
		absint( $module_results['skipped'] )
	);

	$lesson_results = lk_migrate_lessons_to_meta();
	printf(
		"Lessons: %d migrated, %d skipped\n",
		absint( $lesson_results['migrated'] ),
		absint( $lesson_results['skipped'] )
	);

	echo "\nMigration complete!\n";

	return array(
		'modules' => $module_results,
		'lessons' => $lesson_results,
	);
}

// Run if executed directly via WP-CLI.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	lk_run_migration();
}
