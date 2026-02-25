/**
 * CourseCard Component
 * 
 * Displays a single course card in the grid layout.
 * Shows featured image, status badge, title, description, and module count.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';

const CourseCard = ({ course, onClick }) => {
	const { id, title, description, status, moduleCount, featuredImage } = course;
	
	const statusColor = status === 'publish' ? 'published' : 'draft';
	const statusLabel = status === 'publish' ? __('Published', 'learnkit') : __('Draft', 'learnkit');

	return (
		<div
			className="learnkit-course-card"
			onClick={() => onClick(id)}
			role="button"
			tabIndex={0}
			onKeyDown={(e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					onClick(id);
				}
			}}
		>
			<div className="course-card-image">
				{featuredImage ? (
					<img src={featuredImage} alt={title} />
				) : (
					<div className="course-card-placeholder" />
				)}
				<span className={`course-status-badge status-${statusColor}`}>
					{statusLabel}
				</span>
			</div>

			<div className="course-card-content">
				<h3 className="course-card-title">{title}</h3>
				<p className="course-card-description">{description}</p>
				<div className="course-card-meta">
					<span className="module-count">
						📚 {moduleCount} {moduleCount === 1 ? __('module', 'learnkit') : __('modules', 'learnkit')}
					</span>
				</div>
			</div>
		</div>
	);
};

export default CourseCard;
