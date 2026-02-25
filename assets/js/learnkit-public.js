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
		$(document).on('click', '.btn--lk-mark-complete:not([disabled])', function (e) {
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
						.addClass('btn--lk-mark-complete--done')
						.prop('disabled', true)
						.html('<span class="lk-icon"><svg aria-hidden="true" focusable="false" width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.341,12.247a1,1,0,0,0,1.317,1.505l4-3.5a1,1,0,0,0,.028-1.48l-9-8.5A1,1,0,0,0,.313,1.727l8.2,7.745Z" transform="translate(19 6.5) rotate(90)" fill="white"/></svg></span> Completed');

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
					$button.prop('disabled', false).html('<span class="lk-icon"><svg aria-hidden="true" focusable="false" width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.341,12.247a1,1,0,0,0,1.317,1.505l4-3.5a1,1,0,0,0,.028-1.48l-9-8.5A1,1,0,0,0,.313,1.727l8.2,7.745Z" transform="translate(19 6.5) rotate(90)" fill="white"/></svg></span> Mark as Complete');
				}
			});
		});

		// Update lesson status icon in sidebar
		function updateLessonStatus(lessonId, completed) {
			const $lessonItem = $('.lesson-item a[href*="/' + lessonId + '/"], .lesson-item a[href$="/' + lessonId + '"]').parent();
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
			const $btn = $('.btn--lk-mark-complete');
			const lessonId = $btn.data('lesson-id');
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

					// Check if current lesson is already complete
					if (response.completed_lesson_ids.includes(parseInt(lessonId))) {
						$('.btn--lk-mark-complete')
							.addClass('btn--lk-mark-complete--done')
							.prop('disabled', true)
							.html('<span class="lk-icon"><svg aria-hidden="true" focusable="false" width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.341,12.247a1,1,0,0,0,1.317,1.505l4-3.5a1,1,0,0,0,.028-1.48l-9-8.5A1,1,0,0,0,.313,1.727l8.2,7.745Z" transform="translate(19 6.5) rotate(90)" fill="white"/></svg></span> Completed');
					}
				}
			});
		}
	});
})(jQuery);
