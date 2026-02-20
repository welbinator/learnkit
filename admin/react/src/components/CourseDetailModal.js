/**
 * CourseDetailModal Component
 * 
 * Large modal showing course details with editable fields and module management.
 * Includes drag-and-drop module reordering and enrollment management.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { Modal, Button, TextControl, TextareaControl, TabPanel, CheckboxControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { createPortal } from 'react-dom';
import CourseStructure from './CourseStructure';
import EnrollmentManager from './EnrollmentManager';
import QuizModal from './QuizModal';

const CourseDetailModal = ({
	course,
	structure,
	isOpen,
	onClose,
	onSave,
	onEditModule,
	onDeleteModule,
	onCreateModule,
	onCreateLesson,
	onReorderModules,
}) => {
	const [title, setTitle] = useState('');
	const [description, setDescription] = useState('');
	const [featuredImage, setFeaturedImage] = useState('');
	const [selfEnrollment, setSelfEnrollment] = useState(false);
	const [quizModalOpen, setQuizModalOpen] = useState(false);
	const [selectedLesson, setSelectedLesson] = useState(null);
	const [quizMap, setQuizMap] = useState({}); // Map of module/lesson IDs to quiz existence
	const [loadingQuizMap, setLoadingQuizMap] = useState(false);

	// Fetch quiz existence for all modules and lessons
	useEffect(() => {
		if (course && isOpen && !loadingQuizMap) {
			fetchQuizMap();
		}
	}, [course?.id, isOpen]);

	const fetchQuizMap = async () => {
		if (!course || !structure || loadingQuizMap) return;
		
		setLoadingQuizMap(true);
		const map = {};
		
		try {
			// Check for course-level quiz
			const courseQuizRes = await fetch(
				`${window.wpApiSettings.root}learnkit/v1/quizzes?course_id=${course.id}`,
				{
					headers: { 'X-WP-Nonce': window.wpApiSettings.nonce }
				}
			);
			if (courseQuizRes.ok) {
				const data = await courseQuizRes.json();
				map[`course-${course.id}`] = data.length > 0;
			}
		} catch (error) {
			console.error('Error checking course quiz:', error);
		}

		// Check for module and lesson quizzes
		for (const module of structure || []) {
			try {
				const moduleQuizRes = await fetch(
					`${window.wpApiSettings.root}learnkit/v1/quizzes?module_id=${module.id}`,
					{
						headers: { 'X-WP-Nonce': window.wpApiSettings.nonce }
					}
				);
				if (moduleQuizRes.ok) {
					const data = await moduleQuizRes.json();
					map[`module-${module.id}`] = data.length > 0;
				}
			} catch (error) {
				console.error(`Error checking module ${module.id} quiz:`, error);
			}

			// Check lessons in this module
			for (const lesson of module.lessons || []) {
				try {
					const lessonQuizRes = await fetch(
						`${window.wpApiSettings.root}learnkit/v1/quizzes?lesson_id=${lesson.id}`,
						{
							headers: { 'X-WP-Nonce': window.wpApiSettings.nonce }
						}
					);
					if (lessonQuizRes.ok) {
						const data = await lessonQuizRes.json();
						map[`lesson-${lesson.id}`] = data.length > 0;
					}
				} catch (error) {
					console.error(`Error checking lesson ${lesson.id} quiz:`, error);
				}
			}
		}

		setQuizMap(map);
		setLoadingQuizMap(false);
	};

	// Update form fields when course changes
	useEffect(() => {
		if (course) {
			setTitle(course.title || '');
			setDescription(course.description || '');
			setFeaturedImage(course.featuredImage || '');
			setSelfEnrollment(course.selfEnrollment || false);
		}
	}, [course]);

	const handleFeaturedImageUpload = () => {
		// Use WordPress media library
		const mediaUploader = wp.media({
			title: __('Select Featured Image', 'learnkit'),
			button: {
				text: __('Use this image', 'learnkit'),
			},
			multiple: false,
		});

		mediaUploader.on('select', () => {
			const attachment = mediaUploader.state().get('selection').first().toJSON();
			setFeaturedImage(attachment.url);
		});

		mediaUploader.open();
	};

	const handleSave = () => {
		onSave({
			...course,
			title,
			description,
			featuredImage,
			selfEnrollment,
		});
	};

	const handleEditQuiz = (context) => {
		// context can be a lesson object, module object, or { type: 'course' }
		console.log('handleEditQuiz START:', context);
		console.log('Before setState - quizModalOpen:', quizModalOpen);
		console.log('Before setState - selectedLesson:', selectedLesson);
		
		setSelectedLesson(context);
		setQuizModalOpen(true);
		
		console.log('After setState called');
		
		// Check state in next tick
		setTimeout(() => {
			console.log('After setState timeout - should be updated now');
		}, 100);
	};

	if (!isOpen || !course) {
		return null;
	}

	return (
		<>
		<Modal
			title={__('Edit Course', 'learnkit')}
			onRequestClose={onClose}
			className="learnkit-course-detail-modal"
			shouldCloseOnClickOutside={false}
		>
			<div className="modal-content">
				{/* Featured Image */}
				<div className="featured-image-section">
					<label>{__('Featured Image', 'learnkit')}</label>
					{featuredImage ? (
						<div className="featured-image-preview">
							<img src={featuredImage} alt={title} />
							<Button
								isDestructive
								isSmall
								onClick={() => setFeaturedImage('')}
							>
								{__('Remove', 'learnkit')}
							</Button>
						</div>
					) : (
						<div className="featured-image-upload" onClick={handleFeaturedImageUpload}>
							<div className="upload-placeholder">
								<span className="dashicons dashicons-format-image"></span>
								<p>{__('Click to upload', 'learnkit')}</p>
							</div>
						</div>
					)}
				</div>

				{/* Course Title */}
				<TextControl
					label={__('Course Title', 'learnkit')}
					value={title}
					onChange={setTitle}
					placeholder={__('e.g. Introduction to Design Systems', 'learnkit')}
				/>

				{/* Description */}
				<TextareaControl
					label={__('Description', 'learnkit')}
					value={description}
					onChange={setDescription}
					placeholder={__('A brief description of what this course covers...', 'learnkit')}
					rows={4}
				/>

				{/* Self-Enrollment Toggle */}
				<CheckboxControl
					label={__('Enable Self-Enrollment', 'learnkit')}
					help={__('Allow students to enroll themselves in this course from the catalog.', 'learnkit')}
					checked={selfEnrollment}
					onChange={setSelfEnrollment}
				/>

				{/* Tabbed Content */}
				<TabPanel
					className="learnkit-course-tabs"
					activeClass="is-active"
					tabs={[
						{
							name: 'structure',
							title: __('Modules & Lessons', 'learnkit'),
						},
						{
							name: 'enrollments',
							title: __('Enrollments', 'learnkit'),
						},
						{
							name: 'quizzes',
							title: __('Course Quizzes', 'learnkit'),
						},
					]}
				>
					{(tab) => {
						if (tab.name === 'structure') {
							return (
								<div className="modules-section">
									<div className="section-header">
										<h3>{__('Modules', 'learnkit')} ({structure?.modules?.length || 0})</h3>
										<Button variant="secondary" onClick={onCreateModule}>
											{__('Add Module', 'learnkit')}
										</Button>
									</div>

									<CourseStructure
										structure={structure}
										onEditModule={onEditModule}
										onDeleteModule={onDeleteModule}
										onCreateLesson={onCreateLesson}
										onReorderModules={onReorderModules}
										onEditQuiz={handleEditQuiz}
										quizMap={quizMap}
									/>
								</div>
							);
						}

						if (tab.name === 'enrollments') {
							return (
								<EnrollmentManager
									courseId={course.id}
									courseName={title}
								/>
							);
						}

						if (tab.name === 'quizzes') {
							return (
								<div className="quizzes-section">
									<h3>{__('Course-Level Quizzes', 'learnkit')}</h3>
									<p style={{ color: '#757575', marginBottom: '20px' }}>
										{__('Course-level quizzes are standalone assessments not tied to specific lessons.', 'learnkit')}
									</p>
									<Button variant="secondary" onClick={() => handleEditQuiz({ id: null, title: 'Course Quiz', type: 'course' })}>
										{__('+ Add Course Quiz', 'learnkit')}
									</Button>
									{/* TODO: List existing course quizzes here */}
								</div>
							);
						}

						return null;
					}}
				</TabPanel>

				{/* Action Buttons */}
				<div className="modal-actions">
					<Button variant="secondary" onClick={onClose}>
						{__('Cancel', 'learnkit')}
					</Button>
					<Button variant="primary" onClick={handleSave}>
						{__('Save Changes', 'learnkit')}
					</Button>
				</div>
			</div>
		</Modal>
		{(() => {
			console.log('Render check at return:', { quizModalOpen, selectedLesson, course });
			if (quizModalOpen) {
				console.log('Attempting to render QuizModal via portal...');
				// Render quiz modal via portal at document.body level to escape parent modal
				return createPortal(
					<QuizModal
						isOpen={quizModalOpen}
						onClose={() => {
							console.log('QuizModal onClose called');
							setQuizModalOpen(false);
							setSelectedLesson(null);
							// Refresh quiz map to update button text
							fetchQuizMap();
						}}
						lessonId={selectedLesson?.type === 'module' || selectedLesson?.type === 'course' ? null : selectedLesson?.id}
						moduleId={selectedLesson?.type === 'module' ? selectedLesson.id : null}
						courseId={selectedLesson?.type === 'course' || !selectedLesson?.id ? course.id : null}
						lessonTitle={selectedLesson?.title || 'Quiz'}
						contextType={selectedLesson?.type || 'lesson'}
					/>,
					document.body
				);
			} else {
				console.log('quizModalOpen is false, not rendering QuizModal');
				return null;
			}
		})()}
		</>
	);
};

export default CourseDetailModal;
