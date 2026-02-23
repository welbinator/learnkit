/**
 * EditModuleModal Component
 * 
 * Modal for creating/editing a module with title and description.
 * When editing an existing module, also surfaces "Assigned to Courses" with
 * add / remove controls that update immediately (no Save required).
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { Modal, Button, TextControl, TextareaControl, SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { getCourses, assignModuleToCourse, removeModuleFromCourse } from '../utils/api';

const EditModuleModal = ({ module, isOpen, onClose, onSave }) => {
	const [title, setTitle] = useState('');
	const [description, setDescription] = useState('');

	// Course assignment state (only relevant when editing an existing module).
	const [allCourses, setAllCourses] = useState([]);
	const [assignedCourseIds, setAssignedCourseIds] = useState([]);
	const [coursesLoading, setCoursesLoading] = useState(false);
	const [selectedCourseToAdd, setSelectedCourseToAdd] = useState('');
	const [assignError, setAssignError] = useState('');

	// isEditing is true when a module with a real persisted id is being edited.
	// When creating, module may have a temporary _courseId marker but no real .id.
	const isEditing = !!(module && module.id && !module._courseId);

	useEffect(() => {
		if (module) {
			setTitle(module.title || '');
			setDescription(module.description || '');
		} else {
			setTitle('');
			setDescription('');
		}
	}, [module]);

	// Fetch available courses and seed the assigned list when editing.
	useEffect(() => {
		if (!isOpen || !isEditing) {
			return;
		}

		setCoursesLoading(true);
		setAssignError('');

		getCourses()
			.then((courses) => {
				setAllCourses(courses);
				// Seed from module.course_ids (array from API).
				const ids = Array.isArray(module?.course_ids)
					? module.course_ids.map(Number)
					: [];
				setAssignedCourseIds(ids);
				setSelectedCourseToAdd('');
			})
			.catch(() => {
				setAssignError(__('Failed to load courses', 'learnkit'));
			})
			.finally(() => {
				setCoursesLoading(false);
			});
	}, [isOpen, isEditing, module?.id]); // eslint-disable-line react-hooks/exhaustive-deps

	const handleAddCourse = async () => {
		const courseId = parseInt(selectedCourseToAdd, 10);
		if (!courseId) {
			return;
		}

		try {
			await assignModuleToCourse(module.id, courseId);
			setAssignedCourseIds((prev) => [...prev, courseId]);
			setSelectedCourseToAdd('');
			setAssignError('');
		} catch {
			setAssignError(__('Failed to assign course. Please try again.', 'learnkit'));
		}
	};

	const handleRemoveCourse = async (courseId) => {
		try {
			await removeModuleFromCourse(module.id, courseId);
			setAssignedCourseIds((prev) => prev.filter((id) => id !== courseId));
			setAssignError('');
		} catch {
			setAssignError(__('Failed to remove course. Please try again.', 'learnkit'));
		}
	};

	const handleSave = () => {
		if (!title.trim()) {
			alert(__('Please enter a module title', 'learnkit'));
			return;
		}

		onSave({
			title: title.trim(),
			description: description.trim(),
			courseId: module?._courseId || null,
		});

		// Reset form
		setTitle('');
		setDescription('');
	};

	const handleClose = () => {
		setTitle('');
		setDescription('');
		setAssignError('');
		onClose();
	};

	if (!isOpen) {
		return null;
	}

	// Courses not yet assigned to this module.
	const unassignedCourses = allCourses.filter(
		(c) => !assignedCourseIds.includes(c.id)
	);

	// Build SelectControl options for the "Add to course" dropdown.
	const addCourseOptions = [
		{ label: __('— select a course —', 'learnkit'), value: '' },
		...unassignedCourses.map((c) => ({ label: c.title, value: String(c.id) })),
	];

	return (
		<Modal
			title={isEditing ? __('Edit Module', 'learnkit') : __('Create Module', 'learnkit')}
			onRequestClose={handleClose}
			className="learnkit-edit-module-modal"
		>
			<div className="modal-content">
				{/* Module Title */}
				<TextControl
					label={__('Module Title', 'learnkit')}
					value={title}
					onChange={setTitle}
					placeholder={__('e.g. Getting Started with the Basics', 'learnkit')}
				/>

				{/* Description */}
				<TextareaControl
					label={__('Description (Optional)', 'learnkit')}
					value={description}
					onChange={setDescription}
					placeholder={__('A brief description of this module...', 'learnkit')}
					rows={3}
				/>

				{/* Course Assignments — only shown when editing an existing module */}
				{isEditing && (
					<div className="learnkit-module-course-assignments" style={{ marginTop: '20px' }}>
						<hr />
						<h3 style={{ marginBottom: '12px' }}>{__('Assigned to Courses', 'learnkit')}</h3>

						{assignError && (
							<div className="notice notice-error" style={{ marginBottom: '8px' }}>
								<p>{assignError}</p>
							</div>
						)}

						{coursesLoading ? (
							<div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
								<Spinner />
								<span>{__('Loading courses…', 'learnkit')}</span>
							</div>
						) : (
							<>
								{/* List of currently assigned courses */}
								{assignedCourseIds.length === 0 ? (
									<p style={{ color: '#757575', marginBottom: '12px' }}>
										{__('Not assigned to any course yet.', 'learnkit')}
									</p>
								) : (
									<ul style={{ listStyle: 'none', margin: '0 0 12px', padding: 0 }}>
										{assignedCourseIds.map((courseId) => {
											const course = allCourses.find((c) => c.id === courseId);
											return (
												<li
													key={courseId}
													style={{
														display: 'flex',
														alignItems: 'center',
														justifyContent: 'space-between',
														padding: '4px 0',
														borderBottom: '1px solid #e0e0e0',
													}}
												>
													<span>{course ? course.title : `Course #${courseId}`}</span>
													<Button
														isSmall
														isDestructive
														onClick={() => handleRemoveCourse(courseId)}
														aria-label={__('Remove course assignment', 'learnkit')}
													>
														&times;
													</Button>
												</li>
											);
										})}
									</ul>
								)}

								{/* "Add to course" picker */}
								{unassignedCourses.length > 0 && (
									<div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
										<SelectControl
											label={__('Add to course', 'learnkit')}
											value={selectedCourseToAdd}
											options={addCourseOptions}
											onChange={setSelectedCourseToAdd}
											style={{ marginBottom: 0 }}
										/>
										<Button
											variant="secondary"
											isSmall
											onClick={handleAddCourse}
											disabled={!selectedCourseToAdd}
											style={{ marginTop: '22px' }}
										>
											{__('Add', 'learnkit')}
										</Button>
									</div>
								)}
							</>
						)}
					</div>
				)}

				{/* Action Buttons */}
				<div className="modal-actions" style={{ marginTop: '20px' }}>
					<Button variant="secondary" onClick={handleClose}>
						{__('Cancel', 'learnkit')}
					</Button>
					<Button
						variant="primary"
						onClick={handleSave}
						disabled={!title.trim()}
					>
						{isEditing ? __('Save Changes', 'learnkit') : __('Create Module', 'learnkit')}
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default EditModuleModal;
