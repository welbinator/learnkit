/**
 * CourseGrid Component
 * 
 * Displays a responsive grid of course cards.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import CourseCard from './CourseCard';

const CourseGrid = ({ courses, onCourseClick }) => {
	if (!courses || courses.length === 0) {
		return (
			<div className="learnkit-empty-state">
				<p>{__('No courses yet. Create your first course to get started!', 'learnkit')}</p>
			</div>
		);
	}

	return (
		<div className="learnkit-course-grid">
			{courses.map((course) => (
				<CourseCard
					key={course.id}
					course={course}
					onClick={onCourseClick}
				/>
			))}
		</div>
	);
};

export default CourseGrid;
