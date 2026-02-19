/**
 * Course Builder Component
 * 
 * Main course builder interface with tree view, drag-and-drop reordering,
 * and CRUD operations for courses, modules, and lessons.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import CourseList from './components/CourseList';
import CourseStructure from './components/CourseStructure';
import CourseEditorModal from './components/CourseEditorModal';
import ModuleEditorModal from './components/ModuleEditorModal';
import { apiRequest } from './utils/api';

function CourseBuilder() {
	const [courses, setCourses] = useState([]);
	const [selectedCourseId, setSelectedCourseId] = useState(null);
	const [courseStructure, setCourseStructure] = useState(null);
	const [loading, setLoading] = useState(true);
	const [structureLoading, setStructureLoading] = useState(false);
	
	// Modal states
	const [showCourseModal, setShowCourseModal] = useState(false);
	const [showModuleModal, setShowModuleModal] = useState(false);
	const [editingCourse, setEditingCourse] = useState(null);
	const [editingModule, setEditingModule] = useState(null);

	useEffect(() => {
		loadCourses();
	}, []);

	useEffect(() => {
		if (selectedCourseId) {
			loadCourseStructure(selectedCourseId);
		}
	}, [selectedCourseId]);

	const loadCourses = async () => {
		setLoading(true);
		try {
			const data = await apiRequest('/courses');
			setCourses(data);
		} catch (error) {
			console.error('Failed to load courses:', error);
		} finally {
			setLoading(false);
		}
	};

	const loadCourseStructure = async (courseId) => {
		setStructureLoading(true);
		try {
			const data = await apiRequest(`/courses/${courseId}/structure`);
			setCourseStructure(data);
		} catch (error) {
			console.error('Failed to load course structure:', error);
		} finally {
			setStructureLoading(false);
		}
	};

	const handleSelectCourse = (courseId) => {
		setSelectedCourseId(courseId);
	};

	const handleCreateCourse = () => {
		setEditingCourse(null);
		setShowCourseModal(true);
	};

	const handleEditCourse = (course) => {
		setEditingCourse(course);
		setShowCourseModal(true);
	};

	const handleDeleteCourse = async (courseId) => {
		if (!confirm(__('Are you sure you want to delete this course? This will also delete all modules and lessons.', 'learnkit'))) {
			return;
		}

		try {
			await apiRequest(`/courses/${courseId}`, { method: 'DELETE' });
			await loadCourses();
			if (selectedCourseId === courseId) {
				setSelectedCourseId(null);
				setCourseStructure(null);
			}
		} catch (error) {
			alert(__('Failed to delete course', 'learnkit'));
		}
	};

	const handleSaveCourse = async (courseData) => {
		try {
			if (editingCourse) {
				await apiRequest(`/courses/${editingCourse.id}`, {
					method: 'PUT',
					body: courseData,
				});
			} else {
				const response = await apiRequest('/courses', {
					method: 'POST',
					body: courseData,
				});
				if (response.course_id) {
					setSelectedCourseId(response.course_id);
				}
			}
			await loadCourses();
			setShowCourseModal(false);
		} catch (error) {
			alert(__('Failed to save course', 'learnkit'));
		}
	};

	const handleCreateModule = () => {
		if (!selectedCourseId) {
			alert(__('Please select a course first', 'learnkit'));
			return;
		}
		setEditingModule(null);
		setShowModuleModal(true);
	};

	const handleEditModule = (module) => {
		setEditingModule(module);
		setShowModuleModal(true);
	};

	const handleDeleteModule = async (moduleId) => {
		if (!confirm(__('Are you sure you want to delete this module? This will also delete all lessons in it.', 'learnkit'))) {
			return;
		}

		try {
			await apiRequest(`/modules/${moduleId}`, { method: 'DELETE' });
			await loadCourseStructure(selectedCourseId);
		} catch (error) {
			alert(__('Failed to delete module', 'learnkit'));
		}
	};

	const handleSaveModule = async (moduleData) => {
		try {
			moduleData.course_id = selectedCourseId;
			
			if (editingModule) {
				await apiRequest(`/modules/${editingModule.id}`, {
					method: 'PUT',
					body: moduleData,
				});
			} else {
				await apiRequest('/modules', {
					method: 'POST',
					body: moduleData,
				});
			}
			await loadCourseStructure(selectedCourseId);
			setShowModuleModal(false);
		} catch (error) {
			alert(__('Failed to save module', 'learnkit'));
		}
	};

	const handleCreateLesson = async (moduleId, lessonTitle) => {
		try {
			const response = await apiRequest('/lessons', {
				method: 'POST',
				body: {
					title: lessonTitle,
					module_id: moduleId,
				},
			});
			
			// Open WordPress editor for the new lesson
			if (response.lesson && response.lesson.edit_link) {
				window.open(response.lesson.edit_link, '_blank');
			}
			
			await loadCourseStructure(selectedCourseId);
		} catch (error) {
			alert(__('Failed to create lesson', 'learnkit'));
		}
	};

	const handleEditLesson = (lesson) => {
		// Open WordPress block editor for the lesson
		if (lesson.edit_link) {
			window.open(lesson.edit_link, '_blank');
		}
	};

	const handleDeleteLesson = async (lessonId) => {
		if (!confirm(__('Are you sure you want to delete this lesson?', 'learnkit'))) {
			return;
		}

		try {
			await apiRequest(`/lessons/${lessonId}`, { method: 'DELETE' });
			await loadCourseStructure(selectedCourseId);
		} catch (error) {
			alert(__('Failed to delete lesson', 'learnkit'));
		}
	};

	const handleReorderModules = async (newOrder) => {
		try {
			await apiRequest(`/courses/${selectedCourseId}/reorder-modules`, {
				method: 'PUT',
				body: { order: newOrder },
			});
			await loadCourseStructure(selectedCourseId);
		} catch (error) {
			alert(__('Failed to reorder modules', 'learnkit'));
		}
	};

	const handleReorderLessons = async (moduleId, newOrder) => {
		try {
			await apiRequest(`/modules/${moduleId}/reorder-lessons`, {
				method: 'PUT',
				body: { order: newOrder },
			});
			await loadCourseStructure(selectedCourseId);
		} catch (error) {
			alert(__('Failed to reorder lessons', 'learnkit'));
		}
	};

	if (loading) {
		return (
			<div className="learnkit-loading">
				<Spinner />
				<p>{__('Loading Course Builder...', 'learnkit')}</p>
			</div>
		);
	}

	return (
		<div className="learnkit-course-builder">
			<div className="learnkit-builder-header">
				<h2>{__('🎓 Course Builder', 'learnkit')}</h2>
				<Button variant="primary" onClick={handleCreateCourse}>
					{__('Create New Course', 'learnkit')}
				</Button>
			</div>

			<div className="learnkit-builder-content">
				<div className="learnkit-sidebar">
					<CourseList
						courses={courses}
						selectedCourseId={selectedCourseId}
						onSelectCourse={handleSelectCourse}
						onEditCourse={handleEditCourse}
						onDeleteCourse={handleDeleteCourse}
					/>
				</div>

				<div className="learnkit-main">
					{selectedCourseId ? (
						<>
							<div className="learnkit-structure-header">
								<h3>{courseStructure?.course?.title || __('Course Structure', 'learnkit')}</h3>
								<Button variant="secondary" onClick={handleCreateModule}>
									{__('Add Module', 'learnkit')}
								</Button>
							</div>

							{structureLoading ? (
								<div className="learnkit-loading">
									<Spinner />
								</div>
							) : (
								<CourseStructure
									structure={courseStructure}
									onEditModule={handleEditModule}
									onDeleteModule={handleDeleteModule}
									onCreateLesson={handleCreateLesson}
									onEditLesson={handleEditLesson}
									onDeleteLesson={handleDeleteLesson}
									onReorderModules={handleReorderModules}
									onReorderLessons={handleReorderLessons}
								/>
							)}
						</>
					) : (
						<div className="learnkit-empty-state">
							<p>{__('Select a course from the sidebar or create a new one to get started.', 'learnkit')}</p>
						</div>
					)}
				</div>
			</div>

			{showCourseModal && (
				<CourseEditorModal
					course={editingCourse}
					onSave={handleSaveCourse}
					onClose={() => setShowCourseModal(false)}
				/>
			)}

			{showModuleModal && (
				<ModuleEditorModal
					module={editingModule}
					onSave={handleSaveModule}
					onClose={() => setShowModuleModal(false)}
				/>
			)}
		</div>
	);
}

export default CourseBuilder;
