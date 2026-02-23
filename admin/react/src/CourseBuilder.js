/**
 * CourseBuilder Component
 * 
 * Main Course Builder interface with card-based grid layout.
 * Handles course creation, editing, and module management.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import CourseGrid from './components/CourseGrid';
import CourseDetailModal from './components/CourseDetailModal';
import CreateCourseModal from './components/CreateCourseModal';
import EditModuleModal from './components/EditModuleModal';
import { 
	getCourses, 
	createCourse, 
	updateCourse, 
	deleteCourse,
	getCourseStructure,
	createModule,
	updateModule,
	deleteModule,
	createLesson,
	reorderModules
} from './utils/api';

const CourseBuilder = () => {
	// State
	const [courses, setCourses] = useState([]);
	const [loading, setLoading] = useState(true);
	const [selectedCourseId, setSelectedCourseId] = useState(null);
	const [courseStructure, setCourseStructure] = useState(null);
	const [structureLoading, setStructureLoading] = useState(false);

	// Modal states
	const [showCreateModal, setShowCreateModal] = useState(false);
	const [showDetailModal, setShowDetailModal] = useState(false);
	const [showModuleModal, setShowModuleModal] = useState(false);
	const [editingModule, setEditingModule] = useState(null);

	// Load courses on mount
	useEffect(() => {
		loadCourses();
	}, []);

	const loadCourses = async () => {
		try {
			setLoading(true);
			const data = await getCourses();
			setCourses(data);
		} catch (error) {
			console.error('Failed to load courses:', error);
		} finally {
			setLoading(false);
		}
	};

	const loadCourseStructure = async (courseId) => {
		try {
			setStructureLoading(true);
			const data = await getCourseStructure(courseId);
			setCourseStructure(data);
		} catch (error) {
			console.error('Failed to load course structure:', error);
		} finally {
			setStructureLoading(false);
		}
	};

	// Course handlers
	const handleCourseClick = (courseId) => {
		setSelectedCourseId(courseId);
		setShowDetailModal(true);
		loadCourseStructure(courseId);
	};

	const handleCreateCourse = async (courseData) => {
		try {
			const newCourse = await createCourse(courseData);
			setCourses([...courses, newCourse]);
			setShowCreateModal(false);
			
			// Open the new course in detail modal
			handleCourseClick(newCourse.id);
		} catch (error) {
			console.error('Failed to create course:', error);
		}
	};

	const handleSaveCourse = async (courseData) => {
		try {
			await updateCourse(courseData.id, courseData);
			await loadCourses();
			setShowDetailModal(false);
		} catch (error) {
			console.error('Failed to update course:', error);
		}
	};

	const handleDeleteCourse = async (courseId) => {
		if (!confirm(__('Are you sure you want to delete this course? This cannot be undone.', 'learnkit'))) {
			return;
		}

		try {
			await deleteCourse(courseId);
			setCourses(courses.filter((c) => c.id !== courseId));
			
			if (selectedCourseId === courseId) {
				setShowDetailModal(false);
				setSelectedCourseId(null);
			}
		} catch (error) {
			console.error('Failed to delete course:', error);
		}
	};

	// Module handlers
	const handleCreateModule = (courseId) => {
		setEditingModule({ _courseId: courseId || selectedCourseId });
		setShowModuleModal(true);
	};

	const handleEditModule = (module) => {
		setEditingModule(module);
		setShowModuleModal(true);
	};

	const handleSaveModule = async (moduleData) => {
		try {
			if (editingModule) {
				await updateModule(editingModule.id, moduleData);
			} else {
				await createModule(selectedCourseId, moduleData);
			}
			
			await loadCourseStructure(selectedCourseId);
			setShowModuleModal(false);
			setEditingModule(null);
		} catch (error) {
			console.error('Failed to save module:', error);
		}
	};

	const handleDeleteModule = async (moduleId) => {
		if (!confirm(__('Are you sure you want to delete this module? This cannot be undone.', 'learnkit'))) {
			return;
		}

		try {
			await deleteModule(moduleId);
			await loadCourseStructure(selectedCourseId);
		} catch (error) {
			console.error('Failed to delete module:', error);
		}
	};

	const handleCreateLesson = async (moduleId) => {
		try {
			await createLesson(moduleId, {
				title: __('New Lesson', 'learnkit'),
			});
			await loadCourseStructure(selectedCourseId);
		} catch (error) {
			console.error('Failed to create lesson:', error);
		}
	};

	const handleReorderModules = async (reorderedModules) => {
		// reorderedModules is now the full array of module objects (not just IDs)
		// Update local state immediately for smooth UX
		setCourseStructure({
			...courseStructure,
			modules: reorderedModules,
		});

		// Save order to backend
		try {
			const moduleIds = reorderedModules.map(m => m.id);
			await reorderModules(selectedCourseId, moduleIds);
		} catch (error) {
			console.error('Failed to save module order:', error);
			// Optionally: reload structure to revert to server state
		}
	};

	const selectedCourse = courses.find((c) => c.id === selectedCourseId);

	if (loading) {
		return (
			<div className="learnkit-loading">
				<Spinner />
				<p>{__('Loading courses...', 'learnkit')}</p>
			</div>
		);
	}

	return (
		<div className="learnkit-course-builder">
			{/* Header */}
			<div className="learnkit-builder-header">
				<div className="header-content">
					<span className="header-icon">🎓</span>
					<div className="header-text">
						<h1>{__('Course Builder', 'learnkit')}</h1>
						<p>{__('Create and manage your courses', 'learnkit')}</p>
					</div>
				</div>
				<Button variant="primary" onClick={() => setShowCreateModal(true)}>
					+ {__('New Course', 'learnkit')}
				</Button>
			</div>

			{/* Course Grid */}
			<CourseGrid
				courses={courses}
				onCourseClick={handleCourseClick}
			/>

			{/* Modals */}
			<CreateCourseModal
				isOpen={showCreateModal}
				onClose={() => setShowCreateModal(false)}
				onSave={handleCreateCourse}
			/>

			<CourseDetailModal
				course={selectedCourse}
				structure={courseStructure}
				isOpen={showDetailModal}
				onClose={() => {
					setShowDetailModal(false);
					setSelectedCourseId(null);
					setCourseStructure(null);
				}}
				onSave={handleSaveCourse}
				onEditModule={handleEditModule}
				onDeleteModule={handleDeleteModule}
				onCreateModule={handleCreateModule}
				onCreateLesson={handleCreateLesson}
				onReorderModules={handleReorderModules}
				onReloadStructure={loadCourseStructure}
			/>

			<EditModuleModal
				module={editingModule}
				isOpen={showModuleModal}
				onClose={() => {
					setShowModuleModal(false);
					setEditingModule(null);
				}}
				onSave={handleSaveModule}
			/>
		</div>
	);
};

export default CourseBuilder;
