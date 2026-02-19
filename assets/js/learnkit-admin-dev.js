/**
 * LearnKit Admin Development Warning
 * 
 * Displayed when React bundle hasn't been built yet.
 * 
 * @package LearnKit
 * @since 0.1.0
 */

(function() {
	'use strict';

	// Wait for DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const root = document.getElementById('learnkit-admin-root');
		
		if (!root) {
			return;
		}

		root.innerHTML = `
			<div style="padding: 40px; text-align: center; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px;">
				<h2 style="color: #856404; margin-top: 0;">⚠️ React Build Required</h2>
				<p style="font-size: 16px; color: #856404; max-width: 600px; margin: 0 auto 20px;">
					The LearnKit admin interface requires the React bundle to be built.
				</p>
				<div style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; text-align: left; font-family: monospace; font-size: 14px; max-width: 600px; margin: 0 auto;">
					<div style="color: #6a9955;"># Navigate to plugin directory</div>
					<div>cd wp-content/plugins/learnkit/admin/react/</div>
					<br>
					<div style="color: #6a9955;"># Install dependencies</div>
					<div>npm install</div>
					<br>
					<div style="color: #6a9955;"># Build for production</div>
					<div>npm run build</div>
					<br>
					<div style="color: #6a9955;"># Or run development mode with hot reload</div>
					<div>npm start</div>
				</div>
				<p style="margin-top: 20px; color: #856404; font-size: 14px;">
					<strong>Sprint 1 Status:</strong> React pipeline setup in progress. This warning will disappear once the build completes.
				</p>
			</div>
		`;
	}
})();
