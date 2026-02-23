/**
 * CreateCourseModal Component
 * 
 * Modal for creating a new course with title, description, and featured image.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { Modal, Button, TextControl, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

const CreateCourseModal = ({ isOpen, onClose, onSave }) => {
	const [title, setTitle] = useState('');
	const [description, setDescription] = useState('');
	const [featuredImage, setFeaturedImage] = useState('');

	const handleFeaturedImageUpload = () => {
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
		if (!title.trim()) {
			alert(__('Please enter a course title', 'learnkit'));
			return;
		}

		onSave({
			title: title.trim(),
			description: description.trim(),
			featuredImage,
			status: 'draft',
		});

		// Reset form
		setTitle('');
		setDescription('');
		setFeaturedImage('');
	};

	const handleClose = () => {
		setTitle('');
		setDescription('');
		setFeaturedImage('');
		onClose();
	};

	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={__('Create New Course', 'learnkit')}
			onRequestClose={handleClose}
			className="learnkit-create-course-modal"
		>
			<div className="modal-content">
				{/* Featured Image */}
				<div className="featured-image-section">
					<label>{__('Featured Image', 'learnkit')}</label>
					{featuredImage ? (
						<div className="featured-image-preview">
							<img src={featuredImage} alt={__('Featured image preview', 'learnkit')} />
							<button
								type="button"
								className="remove-image"
								onClick={() => setFeaturedImage('')}
							>
								{__('Remove', 'learnkit')}
							</button>
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

				{/* Action Buttons */}
				<div className="modal-actions">
					<Button variant="secondary" onClick={handleClose}>
						{__('Cancel', 'learnkit')}
					</Button>
					<Button
						variant="primary"
						onClick={handleSave}
						disabled={!title.trim()}
					>
						{__('Create Course', 'learnkit')}
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default CreateCourseModal;
