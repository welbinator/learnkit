/**
 * Course Catalog JavaScript
 *
 * @package LearnKit
 * @since 0.3.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Handle enrollment button clicks
		$('.button-enroll').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var courseId = $button.data('course-id');
			
			if ($button.hasClass('enrolling') || $button.prop('disabled')) {
				return;
			}
			
			$button.addClass('enrolling').prop('disabled', true).text('Enrolling');
			
			$.ajax({
				url: learnkitCatalog.ajaxUrl,
				type: 'POST',
				data: {
					action: 'learnkit_enroll_course',
					nonce: learnkitCatalog.nonce,
					course_id: courseId
				},
				success: function(response) {
					if (response.success) {
						// Success - reload page to show enrolled state
						location.reload();
					} else {
						alert(response.data.message || 'Enrollment failed. Please try again.');
						$button.removeClass('enrolling').prop('disabled', false).text('Enroll Now');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$button.removeClass('enrolling').prop('disabled', false).text('Enroll Now');
				}
			});
		});
	});

})(jQuery);
