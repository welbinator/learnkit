/**
 * LearnKit Public JavaScript
 * 
 * Frontend interactions for student experience.
 * 
 * @package LearnKit
 * @since 0.1.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize LearnKit public functionality.
	 */
	$(document).ready(function() {
		// Mark lesson as complete (Sprint 3).
		$('.learnkit-mark-complete').on('click', function(e) {
			e.preventDefault();
			
			const $btn = $(this);
			const lessonId = $btn.data('lesson-id');
			
			// Show loading state.
			$btn.prop('disabled', true).text('Saving...');
			
			// API call to mark complete.
			$.ajax({
				url: learnkitPublic.apiUrl + '/progress',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', learnkitPublic.nonce);
				},
				data: {
					lesson_id: lessonId,
					completed: true
				},
				success: function(response) {
					$btn.text('✓ Completed').addClass('completed');
					// Update progress bar if present.
					updateProgressBar();
				},
				error: function(xhr) {
					$btn.prop('disabled', false).text('Mark Complete');
					alert('Failed to save progress. Please try again.');
				}
			});
		});
	});

	/**
	 * Update course progress bar.
	 */
	function updateProgressBar() {
		const $progressBar = $('.learnkit-progress-fill');
		if (!$progressBar.length) return;

		// Recalculate progress based on completed lessons.
		const totalLessons = $('.learnkit-lesson-item').length;
		const completedLessons = $('.learnkit-lesson-item.completed').length;
		const percentage = (completedLessons / totalLessons) * 100;

		$progressBar.css('width', percentage + '%');
	}

})(jQuery);
