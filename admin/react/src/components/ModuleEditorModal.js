/**
 * Module Editor Modal Component
 * 
 * Modal for creating/editing modules.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Modal, Button, TextControl, TextareaControl } from '@wordpress/components';

function ModuleEditorModal({ module, onSave, onClose }) {
	const [title, setTitle] = useState(module?.title || '');
	const [excerpt, setExcerpt] = useState(module?.excerpt || '');
	const [content, setContent] = useState(module?.content || '');
	const [saving, setSaving] = useState(false);

	const handleSave = async () => {
		if (!title.trim()) {
			alert(__('Module title is required', 'learnkit'));
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
			title={module ? __('Edit Module', 'learnkit') : __('Create New Module', 'learnkit')}
			onRequestClose={onClose}
			className="learnkit-module-modal"
		>
			<div className="learnkit-modal-content">
				<TextControl
					label={__('Module Title', 'learnkit')}
					value={title}
					onChange={setTitle}
					placeholder={__('Enter module title...', 'learnkit')}
				/>

				<TextareaControl
					label={__('Short Description', 'learnkit')}
					value={excerpt}
					onChange={setExcerpt}
					placeholder={__('Brief description of the module...', 'learnkit')}
					rows={3}
				/>

				<TextareaControl
					label={__('Full Description', 'learnkit')}
					value={content}
					onChange={setContent}
					placeholder={__('Detailed module description...', 'learnkit')}
					rows={6}
				/>

				<div className="learnkit-modal-actions">
					<Button
						variant="primary"
						onClick={handleSave}
						disabled={saving}
					>
						{saving ? __('Saving...', 'learnkit') : __('Save Module', 'learnkit')}
					</Button>
					<Button onClick={onClose}>
						{__('Cancel', 'learnkit')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}

export default ModuleEditorModal;
