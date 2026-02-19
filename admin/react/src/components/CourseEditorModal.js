/**
 * Course Editor Modal Component
 * 
 * Modal for creating/editing courses.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Modal, Button, TextControl, TextareaControl } from '@wordpress/components';

function CourseEditorModal({ course, onSave, onClose }) {
	const [title, setTitle] = useState(course?.title || '');
	const [excerpt, setExcerpt] = useState(course?.excerpt || '');
	const [content, setContent] = useState(course?.content || '');
	const [saving, setSaving] = useState(false);

	const handleSave = async () => {
		if (!title.trim()) {
			alert(__('Course title is required', 'learnkit'));
			return;
		}

		setSaving(true);
		try {
			await onSave({ title, excerpt, content });
		} finally {
			setSaving(false);
		}
	};

	return (
		<Modal
			title={course ? __('Edit Course', 'learnkit') : __('Create New Course', 'learnkit')}
			onRequestClose={onClose}
			className="learnkit-course-modal"
		>
			<div className="learnkit-modal-content">
				<TextControl
					label={__('Course Title', 'learnkit')}
					value={title}
					onChange={setTitle}
					placeholder={__('Enter course title...', 'learnkit')}
				/>

				<TextareaControl
					label={__('Short Description', 'learnkit')}
					value={excerpt}
					onChange={setExcerpt}
					placeholder={__('Brief description of the course...', 'learnkit')}
					rows={3}
				/>

				<TextareaControl
					label={__('Full Description', 'learnkit')}
					value={content}
					onChange={setContent}
					placeholder={__('Detailed course description...', 'learnkit')}
					rows={6}
				/>

				<div className="learnkit-modal-actions">
					<Button
						variant="primary"
						onClick={handleSave}
						disabled={saving}
					>
						{saving ? __('Saving...', 'learnkit') : __('Save Course', 'learnkit')}
					</Button>
					<Button onClick={onClose}>
						{__('Cancel', 'learnkit')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}

export default CourseEditorModal;
