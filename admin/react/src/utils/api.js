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
