import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('learnkit/dashboard', {
	edit: () => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<div style={{
					padding: '40px',
					textAlign: 'center',
					background: '#f9f9f9',
					border: '2px dashed #dcdcde',
					borderRadius: '8px'
				}}>
					<span className="dashicons dashicons-welcome-learn-more" style={{
						fontSize: '48px',
						color: '#2271b1',
						marginBottom: '16px',
						display: 'block'
					}}></span>
					<h3 style={{ margin: '16px 0 8px' }}>Student Dashboard</h3>
					<p style={{ color: '#757575', margin: 0 }}>
						Shows enrolled courses with progress tracking. Preview available on the frontend.
					</p>
				</div>
			</div>
		);
	},
	save: () => {
		return null; // Server-side rendered
	}
});
