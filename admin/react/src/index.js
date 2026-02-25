/**
 * LearnKit Admin React App
 * 
 * Main entry point for the React-powered admin interface.
 * 
 * @package LearnKit
 * @since 0.1.0
 */

import { render } from '@wordpress/element';
import App from './App';
import './style.css';
import './quiz-builder-styles.css';

// Mount React app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	const rootElement = document.getElementById('learnkit-admin-root');
	
	if (rootElement) {
		render(<App />, rootElement);
	}
});
