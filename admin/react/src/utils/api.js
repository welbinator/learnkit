/**
 * API utility functions
 * 
 * @package LearnKit
 * @since 0.2.0
 */

/**
 * Make an API request to LearnKit REST endpoints.
 * 
 * @param {string} endpoint - API endpoint (without base URL)
 * @param {Object} options - Fetch options
 * @returns {Promise} API response data
 */
export async function apiRequest(endpoint, options = {}) {
	const url = window.learnkitAdmin.apiUrl + endpoint;
	
	const defaultOptions = {
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.learnkitAdmin.nonce,
		},
	};

	if (options.body) {
		options.body = JSON.stringify(options.body);
	}

	const response = await fetch(url, { ...defaultOptions, ...options });

	if (!response.ok) {
		throw new Error(`API request failed: ${response.statusText}`);
	}

	return await response.json();
}

/**
 * Get all courses with module counts.
 */
export async function getCourses() {
	const courses = await apiRequest('/courses');
	
	// Transform to include module count and other metadata
	return courses.map(course => ({
		id: course.id,
		title: course.title || 'Untitled Course',
		description: course.excerpt || '',
		status: course.status,
		featuredImage: course.featured_image || '',
		accessType: course.access_type || 'free',
		moduleCount: course.module_count || 0,
	}));
}

/**
 * Create a new course.
 */
export async function createCourse(courseData) {
	const response = await apiRequest('/courses', {
		method: 'POST',
		body: {
			title: courseData.title,
			status: courseData.status || 'draft',
			meta: {
				learnkit_description: courseData.description || '',
				learnkit_featured_image: courseData.featuredImage || '',
			},
		},
	});

	return {
		id: response.id,
		title: response.title?.rendered || courseData.title,
		description: courseData.description || '',
		status: response.status,
		featuredImage: courseData.featuredImage || '',
		moduleCount: 0,
	};
}

/**
 * Update a course.
 */
export async function updateCourse(courseId, courseData) {
	return await apiRequest(`/courses/${courseId}`, {
		method: 'POST',
		body: {
			title: courseData.title,
			excerpt: courseData.description || '',
			featured_image_url: courseData.featuredImage || '',
			access_type: courseData.accessType || 'free',
		},
	});
}

/**
 * Delete a course.
 */
export async function deleteCourse(courseId) {
	return await apiRequest(`/courses/${courseId}`, {
		method: 'DELETE',
	});
}

/**
 * Get course structure (modules and lessons).
 */
export async function getCourseStructure(courseId) {
	return await apiRequest(`/courses/${courseId}/structure`);
}

/**
 * Create a new module.
 */
export async function createModule(courseId, moduleData) {
	return await apiRequest('/modules', {
		method: 'POST',
		body: { ...moduleData, course_id: courseId },
	});
}

/**
 * Update a module.
 */
export async function updateModule(moduleId, moduleData) {
	return await apiRequest(`/modules/${moduleId}`, {
		method: 'POST',
		body: moduleData,
	});
}

/**
 * Delete a module.
 */
export async function deleteModule(moduleId) {
	return await apiRequest(`/modules/${moduleId}`, {
		method: 'DELETE',
	});
}

/**
 * Get all modules (no filter — for "add existing" pickers).
 */
export async function getAllModules() {
	return await apiRequest('/modules');
}

/**
 * Assign a module to a course (additive — does not remove other assignments).
 *
 * @param {number} moduleId
 * @param {number} courseId
 */
export async function assignModuleToCourse(moduleId, courseId) {
	return await apiRequest(`/modules/${moduleId}/assign-course`, {
		method: 'POST',
		body: { course_id: courseId },
	});
}

/**
 * Remove a module's assignment from a course.
 *
 * @param {number} moduleId
 * @param {number} courseId
 */
export async function removeModuleFromCourse(moduleId, courseId) {
	return await apiRequest(`/modules/${moduleId}/assign-course`, {
		method: 'DELETE',
		body: { course_id: courseId },
	});
}

/**
 * Create a new lesson.
 */
export async function createLesson(moduleId, lessonData) {
	return await apiRequest(`/modules/${moduleId}/lessons`, {
		method: 'POST',
		body: lessonData,
	});
}

/**
 * Get all lessons (no filter — for "add existing" pickers).
 */
export async function getAllLessons() {
	return await apiRequest('/lessons');
}

/**
 * Assign a lesson to a module (additive — does not remove other assignments).
 *
 * @param {number} lessonId
 * @param {number} moduleId
 */
export async function assignLessonToModule(lessonId, moduleId) {
	return await apiRequest(`/lessons/${lessonId}/assign-module`, {
		method: 'POST',
		body: { module_id: moduleId },
	});
}

/**
 * Remove a lesson's assignment from a module.
 *
 * @param {number} lessonId
 * @param {number} moduleId
 */
export async function removeLessonFromModule(lessonId, moduleId) {
	return await apiRequest(`/lessons/${lessonId}/assign-module`, {
		method: 'DELETE',
		body: { module_id: moduleId },
	});
}

/**
 * Reorder modules in a course.
 */
export async function reorderModules(courseId, moduleIds) {
	return await apiRequest(`/courses/${courseId}/reorder-modules`, {
		method: 'POST',
		body: {
			order: moduleIds,
		},
	});
}

/**
 * Get a single lesson by ID.
 */
export async function getLesson(lessonId) {
	return await apiRequest(`/lessons/${lessonId}`);
}

/**
 * Update a lesson (including drip meta).
 */
export async function updateLesson(lessonId, lessonData) {
	return await apiRequest(`/lessons/${lessonId}`, {
		method: 'POST',
		body: lessonData,
	});
}

/**
 * Get enrollments for a course.
 */
export async function getEnrollments(courseId) {
	return await apiRequest(`/enrollments/course/${courseId}`);
}

/**
 * Create a new enrollment.
 */
export async function createEnrollment(userId, courseId) {
	return await apiRequest('/enrollments', {
		method: 'POST',
		body: {
			user_id: userId,
			course_id: courseId,
		},
	});
}

/**
 * Delete an enrollment.
 */
export async function deleteLesson(lessonId) {
	return await apiRequest(`/lessons/${lessonId}`, {
		method: 'DELETE',
	});
}


export async function deleteEnrollment(enrollmentId) {
	return await apiRequest(`/enrollments/${enrollmentId}`, {
		method: 'DELETE',
	});
}

/**
 * Get all lessons (optionally filtered by module_id).
 */
export async function getAllLessons( moduleId ) {
	const endpoint = moduleId ? `/lessons?module_id=${moduleId}` : '/lessons';
	return await apiRequest( endpoint );
}

/**
 * Assign a lesson to a module (sets _lk_module_id).
 */
export async function assignLessonToModule( lessonId, moduleId ) {
	return await apiRequest( `/lessons/${lessonId}/assign-module`, {
		method: 'POST',
		body: { module_id: moduleId },
	} );
}

/**
 * Remove a lesson from a module (clears _lk_module_id).
 */
export async function removeLessonFromModule( lessonId, moduleId ) {
	return await apiRequest( `/lessons/${lessonId}/assign-module`, {
		method: 'DELETE',
		body: { module_id: moduleId },
	} );
}

/**
 * Get WordPress users (for enrollment dropdown).
 */
export async function getUsers() {
	const response = await fetch(window.learnkitAdmin.wpApiUrl + 'wp/v2/users?per_page=100', {
		headers: {
			'X-WP-Nonce': window.learnkitAdmin.nonce,
		},
	});

	if (!response.ok) {
		throw new Error('Failed to fetch users');
	}

	const users = await response.json();
	return users.map(user => ({
		id: user.id,
		name: user.name,
		email: user.email || '',
	}));
}
