/**
 * LearnKit Admin App Component
 * 
 * Root component for the LearnKit course builder interface.
 * Sprint 1: Hello World placeholder
 * Sprint 2+: Full course builder UI
 * 
 * @package LearnKit
 * @since 0.1.0
 */

import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

function App() {
	const [apiConnected, setApiConnected] = useState(false);
	const [courses, setCourses] = useState([]);
	const [loading, setLoading] = useState(true);

	// Test API connection on mount
	useEffect(() => {
		testApiConnection();
	}, []);

	/**
	 * Test REST API connectivity.
	 */
	const testApiConnection = async () => {
		try {
			const response = await fetch(window.learnkitAdmin.apiUrl + '/courses', {
				headers: {
					'X-WP-Nonce': window.learnkitAdmin.nonce,
				},
			});

			if (response.ok) {
				const data = await response.json();
				setApiConnected(true);
				setCourses(data);
			}
		} catch (error) {
			console.error('API Connection Error:', error);
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Handle test button click.
	 */
	const handleTestClick = () => {
		alert(__('Hello from LearnKit! React is working! 🎉', 'learnkit'));
	};

	if (loading) {
		return (
			<div className="learnkit-loading">
				{__('Loading LearnKit Admin...', 'learnkit')}
			</div>
		);
	}

	return (
		<div className="learnkit-admin-app">
			<Card>
				<CardHeader>
					<h2>
						{__('🎓 Welcome to LearnKit Admin', 'learnkit')}
					</h2>
				</CardHeader>
				<CardBody>
					<p style={{ fontSize: '16px', marginBottom: '20px' }}>
						{__('Congratulations! The React admin interface is successfully loaded.', 'learnkit')}
					</p>

					<div style={{ 
						padding: '20px', 
						background: apiConnected ? '#d1e7dd' : '#f8d7da',
						border: `1px solid ${apiConnected ? '#badbcc' : '#f5c2c7'}`,
						borderRadius: '4px',
						marginBottom: '20px'
					}}>
						<h3 style={{ marginTop: 0 }}>
							{apiConnected ? '✓ ' : '✗ '}
							{__('REST API Status', 'learnkit')}
						</h3>
						<p style={{ marginBottom: '10px' }}>
							<strong>{__('Endpoint:', 'learnkit')}</strong> {window.learnkitAdmin.apiUrl}
						</p>
						<p style={{ marginBottom: 0 }}>
							<strong>{__('Status:', 'learnkit')}</strong>{' '}
							{apiConnected 
								? __('Connected successfully', 'learnkit')
								: __('Connection failed', 'learnkit')
							}
						</p>
						{apiConnected && (
							<p style={{ marginTop: '10px', marginBottom: 0 }}>
								<strong>{__('Courses Found:', 'learnkit')}</strong> {courses.length}
							</p>
						)}
					</div>

					<Button
						variant="primary"
						onClick={handleTestClick}
						style={{ marginRight: '10px' }}
					>
						{__('Test React Button', 'learnkit')}
					</Button>

					<Button
						variant="secondary"
						onClick={testApiConnection}
					>
						{__('Retest API Connection', 'learnkit')}
					</Button>

					<div style={{ 
						marginTop: '30px', 
						padding: '20px', 
						background: '#f0f0f0',
						borderRadius: '4px'
					}}>
						<h3>{__('Sprint 1 Foundation - Complete! ✅', 'learnkit')}</h3>
						<ul style={{ lineHeight: '1.8' }}>
							<li>✓ {__('Plugin structure created', 'learnkit')}</li>
							<li>✓ {__('Custom post types registered (courses, modules, lessons)', 'learnkit')}</li>
							<li>✓ {__('Custom database tables created (enrollments, progress, certificates)', 'learnkit')}</li>
							<li>✓ {__('REST API endpoints working', 'learnkit')}</li>
							<li>✓ {__('React build pipeline configured', 'learnkit')}</li>
							<li>✓ {__('Admin menu integrated', 'learnkit')}</li>
						</ul>
						<p style={{ marginTop: '15px', fontWeight: 'bold' }}>
							{__('Next: Sprint 2 - Course Builder Basics', 'learnkit')}
						</p>
					</div>
				</CardBody>
			</Card>
		</div>
	);
}

export default App;
