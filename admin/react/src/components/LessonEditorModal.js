/**
 * Lesson Editor Modal Component
 *
 * Modal for editing lesson content and drip settings.
 *
 * @package LearnKit
 * @since 0.5.0
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Modal, Button, TextControl, TextareaControl, SelectControl } from '@wordpress/components';
import { updateLesson } from '../utils/api';

function LessonEditorModal({ lesson, onSave, onClose }) {
	const [title, setTitle] = useState(lesson?.title || '');
	const [content, setContent] = useState(lesson?.content || '');
	const [releaseType, setReleaseType] = useState(lesson?.release_type || 'immediate');
	const [releaseDays, setReleaseDays] = useState(String(lesson?.release_days || ''));
	const [releaseDate, setReleaseDate] = useState(lesson?.release_date || '');
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');

	const handleSave = async () => {
		if (!title.trim()) {
			setError(__('Lesson title is required', 'learnkit'));
			return;
		}

		setSaving(true);
		setError('');

		try {
			const payload = {
				title,
				content,
				release_type: releaseType,
				release_days: releaseType === 'days_after_enrollment' ? parseInt(releaseDays, 10) || 0 : 0,
				release_date: releaseType === 'specific_date' ? releaseDate : '',
			};

			await updateLesson(lesson.id, payload);

			if (onSave) {
				onSave(payload);
			}
		} catch (err) {
			setError(__('Failed to save lesson. Please try again.', 'learnkit'));
		} finally {
			setSaving(false);
		}
	};

	return (
		<Modal
			title={__('Edit Lesson', 'learnkit')}
			onRequestClose={onClose}
			className="learnkit-lesson-editor-modal"
		>
			<div className="learnkit-modal-content">
				{error && (
					<div className="notice notice-error" style={{ marginBottom: '16px' }}>
						<p>{error}</p>
					</div>
				)}

				<TextControl
					label={__('Lesson Title', 'learnkit')}
					value={title}
					onChange={setTitle}
					placeholder={__('Enter lesson title...', 'learnkit')}
				/>

				<TextareaControl
					label={__('Content', 'learnkit')}
					value={content}
					onChange={setContent}
					placeholder={__('Lesson content...', 'learnkit')}
					rows={6}
				/>

				{/* Drip content settings */}
				<hr style={{ margin: '24px 0' }} />
				<h3 style={{ marginBottom: '12px' }}>{__('Drip Content Settings', 'learnkit')}</h3>

				<SelectControl
					label={__('Release Type', 'learnkit')}
					value={releaseType}
					onChange={setReleaseType}
					options={[
						{ label: __('Immediate', 'learnkit'), value: 'immediate' },
						{ label: __('Days after enrollment', 'learnkit'), value: 'days_after_enrollment' },
						{ label: __('Specific date', 'learnkit'), value: 'specific_date' },
					]}
				/>

				{releaseType === 'days_after_enrollment' && (
					<TextControl
						label={__('Days after enrollment', 'learnkit')}
						type="number"
						value={releaseDays}
						onChange={setReleaseDays}
						min={1}
						help={__('Number of days after the student enrolls before this lesson unlocks.', 'learnkit')}
					/>
				)}

				{releaseType === 'specific_date' && (
					<TextControl
						label={__('Unlock date', 'learnkit')}
						type="date"
						value={releaseDate ? releaseDate.substring(0, 10) : ''}
						onChange={(val) => setReleaseDate(val)}
						help={__('The lesson becomes available on this date.', 'learnkit')}
					/>
				)}

				<div className="learnkit-modal-actions" style={{ marginTop: '24px' }}>
					<Button
						variant="primary"
						onClick={handleSave}
						disabled={saving}
					>
						{saving ? __('Saving...', 'learnkit') : __('Save Lesson', 'learnkit')}
					</Button>
					<Button onClick={onClose}>
						{__('Cancel', 'learnkit')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}

export default LessonEditorModal;
