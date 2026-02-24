/**
 * LearnKit Public JavaScript
 *
 * Handles lesson progress tracking on the frontend.
 *
 * @package LearnKit
 * @since 0.2.14
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		// Handle mark complete button
		$('.lk-button-mark-complete').on('click', function (e) {
			e.preventDefault();

			const $button = $(this);
			const lessonId = $button.data('lesson-id');

			// Disable button during request
			$button.prop('disabled', true).text('Marking...');

			$.ajax({
				url: learnkitPublic.apiUrl + '/progress/' + lessonId,
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', learnkitPublic.nonce);
				},
				success: function (response) {
					$button
						.removeClass('lk-button-mark-complete')
						.addClass('lk-button-mark-complete--done')
						.prop('disabled', false)
						.html('<span class="checkmark">✓</span> Completed');

					// Update sidebar lesson status
					updateLessonStatus(lessonId, true);

					// Update module progress
					updateModuleProgress();
				},
				error: function (xhr) {
					let message = 'Failed to mark lesson complete';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						message = xhr.responseJSON.message;
					}
					alert(message);
					$button.prop('disabled', false).html('<span class="checkmark">✓</span> Mark as Complete');
				}
			});
		});

		// Update lesson status icon in sidebar
		function updateLessonStatus(lessonId, completed) {
			const $lessonItem = $('.lesson-item a[href*="' + lessonId + '"]').parent();
			const $statusIcon = $lessonItem.find('.status-icon');

			if (completed) {
				$statusIcon.removeClass('incomplete').addClass('complete').text('✓');
			} else {
				$statusIcon.removeClass('complete').addClass('incomplete').text('○');
			}
		}

		// Update module progress bar
		function updateModuleProgress() {
			const totalLessons = $('.lesson-item').length;
			const completedLessons = $('.lesson-item .status-icon.complete').length;
			const percent = totalLessons > 0 ? Math.round((completedLessons / totalLessons) * 100) : 0;

			$('.progress-fill').css('width', percent + '%');
			$('.progress-text').text(percent + '% Complete');
		}

		// Load progress on page load
		loadProgressData();

		function loadProgressData() {
			// Get lesson ID from the button (either state)
			const lessonId = $('.lk-button-mark-complete').data('lesson-id') || $('.lk-button-mark-complete--done').data('lesson-id');
			if (!lessonId) {
				return;
			}

			// Get module ID from page data
			const moduleId = $('.learnkit-lesson-sidebar').data('module-id');
			if (!moduleId) {
				return;
			}

			// Load module progress
			$.ajax({
				url: learnkitPublic.apiUrl + '/progress/user/' + learnkitPublic.currentUser + '/module/' + moduleId,
				method: 'GET',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', learnkitPublic.nonce);
				},
				success: function (response) {
					// Update progress bar
					$('.progress-fill').css('width', response.progress_percent + '%');
					$('.progress-text').text(response.progress_percent + '% Complete');

					// Update lesson status icons
					response.completed_lesson_ids.forEach(function (completedLessonId) {
						updateLessonStatus(completedLessonId, true);
					});

					// Check if current lesson is complete
					if (response.completed_lesson_ids.includes(parseInt(lessonId))) {
						$('.lk-button-mark-complete')
							.removeClass('lk-button-mark-complete')
							.addClass('lk-button-mark-complete--done')
							.html('<span class="checkmark">✓</span> Completed');
					}
				}
			});
		}
	});
})(jQuery);
