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
import { Modal, Button, TextControl, TextareaControl, TabPanel, SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { createPortal } from 'react-dom';
import CourseStructure from './CourseStructure';
import EnrollmentManager from './EnrollmentManager';
import QuizModal from './QuizModal';
import { getAllModules, assignModuleToCourse, getCourseStructure } from '../utils/api';

const CourseDetailModal = ({
	course,
	structure,
	isOpen,
	onClose,
	onSave,
	onEditModule,
	onDeleteModule,
	onCreateModule,
	onDeleteLesson,
	onReorderModules,
	onReloadStructure,
}) => {
	const [title, setTitle] = useState('');
	const [description, setDescription] = useState('');
	const [featuredImage, setFeaturedImage] = useState('');
	const [accessType, setAccessType] = useState('free');
	const [quizModalOpen, setQuizModalOpen] = useState(false);
	const [selectedLesson, setSelectedLesson] = useState(null);
	const [editingLesson, setEditingLesson] = useState(null); // kept for future use

	// "Add Existing Module" picker state.
	const [showExistingModulePicker, setShowExistingModulePicker] = useState(false);
	const [allModules, setAllModules] = useState([]);
	const [modulesPickerLoading, setModulesPickerLoading] = useState(false);
	const [selectedExistingModule, setSelectedExistingModule] = useState('');
	const [addExistingError, setAddExistingError] = useState('');

	// Update form fields when course changes
	useEffect(() => {
		if (course) {
			setTitle(course.title || '');
			setDescription(course.description || '');
			setFeaturedImage(course.featuredImage || '');
			setAccessType(course.accessType || 'free');
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
			accessType,
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

	const handleEditLesson = (lesson) => {
		window.location.href = `/wp-admin/post.php?post=${lesson.id}&action=edit`;
	};

	const handleLessonSaved = () => {
		// no-op: lesson editing happens in the WP block editor
	};

	const handleShowExistingModulePicker = () => {
		setShowExistingModulePicker(true);
		setSelectedExistingModule('');
		setAddExistingError('');
		setModulesPickerLoading(true);

		getAllModules()
			.then((modules) => {
				setAllModules(modules);
			})
			.catch(() => {
				setAddExistingError(__('Failed to load modules', 'learnkit'));
			})
			.finally(() => {
				setModulesPickerLoading(false);
			});
	};

	const handleAddExistingModule = async () => {
		const moduleId = parseInt(selectedExistingModule, 10);
		if (!moduleId) {
			return;
		}

		try {
			await assignModuleToCourse(moduleId, course.id);
			setShowExistingModulePicker(false);
			setSelectedExistingModule('');
			setAddExistingError('');
			// Reload structure so the newly-linked module appears.
			if (onReloadStructure) {
				onReloadStructure(course.id);
			}
		} catch {
			setAddExistingError(__('Failed to add module. Please try again.', 'learnkit'));
		}
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

				{/* Access Type */}
				<SelectControl
					label={__('Access Type', 'learnkit')}
					help={__('Free: anyone can self-enroll. Paid: requires WooCommerce product purchase.', 'learnkit')}
					value={accessType}
					onChange={setAccessType}
					options={[
						{ label: __('Free (Self-Enrollment)', 'learnkit'), value: 'free' },
						{ label: __('Paid (Requires Purchase)', 'learnkit'), value: 'paid' },
					]}
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
										<div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
											<Button variant="secondary" onClick={() => onCreateModule(course?.id)}>
												{__('Add Module', 'learnkit')}
											</Button>
											<Button
												variant="secondary"
												onClick={handleShowExistingModulePicker}
											>
												{__('Add Existing Module', 'learnkit')}
											</Button>
										</div>
									</div>

									{/* Existing-module picker */}
									{showExistingModulePicker && (
										<div
											style={{
												background: '#f6f7f7',
												border: '1px solid #ddd',
												borderRadius: '4px',
												padding: '12px',
												marginBottom: '12px',
											}}
										>
											{addExistingError && (
												<div className="notice notice-error" style={{ marginBottom: '8px' }}>
													<p>{addExistingError}</p>
												</div>
											)}

											{modulesPickerLoading ? (
												<div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
													<Spinner />
													<span>{__('Loading modules…', 'learnkit')}</span>
												</div>
											) : (
												<div style={{ display: 'flex', alignItems: 'flex-end', gap: '8px' }}>
													<SelectControl
														label={__('Choose a module to add', 'learnkit')}
														value={selectedExistingModule}
														onChange={setSelectedExistingModule}
														options={[
															{ label: __('— select a module —', 'learnkit'), value: '' },
															...allModules
																.filter(
																	(m) =>
																		!structure?.modules?.some(
																			(sm) => sm.id === m.id
																		)
																)
																.map((m) => ({
																	label: m.title || `Module #${m.id}`,
																	value: String(m.id),
																})),
														]}
														style={{ marginBottom: 0 }}
													/>
													<Button
														variant="primary"
														isSmall
														onClick={handleAddExistingModule}
														disabled={!selectedExistingModule}
													>
														{__('Add', 'learnkit')}
													</Button>
													<Button
														isSmall
														onClick={() => {
															setShowExistingModulePicker(false);
															setAddExistingError('');
														}}
													>
														{__('Cancel', 'learnkit')}
													</Button>
												</div>
											)}
										</div>
									)}

									<CourseStructure
										structure={structure}
										onEditModule={onEditModule}
										onDeleteModule={onDeleteModule}
										onDeleteLesson={(lessonId) => onDeleteLesson(lessonId, course?.id)}
										onEditLesson={handleEditLesson}
										onReorderModules={onReorderModules}
										onEditQuiz={handleEditQuiz}
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
