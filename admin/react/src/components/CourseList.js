/**
 * Course List Component
 * 
 * Displays list of courses in sidebar.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

function CourseList({ courses, selectedCourseId, onSelectCourse, onEditCourse, onDeleteCourse }) {
	if (courses.length === 0) {
		return (
			<div className="learnkit-empty-courses">
				<p>{__('No courses yet. Create your first course!', 'learnkit')}</p>
			</div>
		);
	}

	return (
		<div className="learnkit-course-list">
			<h3>{__('Your Courses', 'learnkit')}</h3>
			<ul>
				{courses.map((course) => (
					<li
						key={course.id}
						className={selectedCourseId === course.id ? 'selected' : ''}
					>
						<div
							className="course-item"
							onClick={() => onSelectCourse(course.id)}
						>
							<span className="course-title">{course.title}</span>
						</div>
						<div className="course-actions">
							<Button
								isSmall
								variant="secondary"
								onClick={(e) => {
									e.stopPropagation();
									onEditCourse(course);
								}}
							>
								{__('Edit', 'learnkit')}
							</Button>
							<Button
								isSmall
								isDestructive
								onClick={(e) => {
									e.stopPropagation();
									onDeleteCourse(course.id);
								}}
							>
								{__('Delete', 'learnkit')}
							</Button>
						</div>
					</li>
				))}
			</ul>
		</div>
	);
}

export default CourseList;
