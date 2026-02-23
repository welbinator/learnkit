/**
 * EditModuleModal Component
 * 
 * Modal for creating/editing a module with title and description.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { Modal, Button, TextControl, TextareaControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

const EditModuleModal = ({ module, isOpen, onClose, onSave }) => {
	const [title, setTitle] = useState('');
	const [description, setDescription] = useState('');

	useEffect(() => {
		if (module) {
			setTitle(module.title || '');
			setDescription(module.description || '');
		} else {
			setTitle('');
			setDescription('');
		}
	}, [module]);

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
		onClose();
	};

	if (!isOpen) {
		return null;
	}

	const isEditing = !!(module && !module._courseId);

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
						{isEditing ? __('Save Changes', 'learnkit') : __('Create Module', 'learnkit')}
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default EditModuleModal;
