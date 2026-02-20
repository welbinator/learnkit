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

	const handleEditQuiz = (lesson) => {
		setSelectedLesson(lesson);
		setQuizModalOpen(true);
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
		{quizModalOpen && selectedLesson && (
			<QuizModal
				isOpen={quizModalOpen}
				onClose={() => {
					setQuizModalOpen(false);
					setSelectedLesson(null);
				}}
				lessonId={selectedLesson.id}
				lessonTitle={selectedLesson.title}
			/>
		)}
		</>
	);
};

export default CourseDetailModal;
