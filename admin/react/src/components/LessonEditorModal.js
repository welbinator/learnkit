/**
 * Lesson Editor Modal Component
 *
 * Modal for editing lesson content, drip settings, and module assignment (one-to-one).
 *
 * @package LearnKit
 * @since 0.5.0
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, TextControl, TextareaControl, SelectControl, Spinner } from '@wordpress/components';
import { updateLesson, getLesson, getAllModules, assignLessonToModule } from '../utils/api';

function LessonEditorModal({ lesson, onSave, onClose }) {
	const [title, setTitle] = useState('');
	const [content, setContent] = useState('');
	const [releaseType, setReleaseType] = useState('immediate');
	const [releaseDays, setReleaseDays] = useState('');
	const [releaseDate, setReleaseDate] = useState('');
	const [saving, setSaving] = useState(false);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState('');

	// Module assignment state (one-to-one).
	const [allModules, setAllModules] = useState([]);
	const [assignedModuleId, setAssignedModuleId] = useState(0);
	const [pendingModuleId, setPendingModuleId] = useState('');
	const [modulesLoading, setModulesLoading] = useState(false);
	const [assignError, setAssignError] = useState('');
	const [assignSaving, setAssignSaving] = useState(false);

	// Fetch full lesson data when modal opens.
	useEffect(() => {
		if ( ! lesson?.id ) {
			return;
		}

		setLoading(true);
		setError('');

		getLesson(lesson.id)
			.then((fullLesson) => {
				setTitle(fullLesson?.title || lesson?.title || '');
				setContent(fullLesson?.content || '');
				setReleaseType(fullLesson?.release_type || 'immediate');
				setReleaseDays(String(fullLesson?.release_days || ''));
				setReleaseDate(fullLesson?.release_date || '');
				// One-to-one: use module_id directly.
				const mid = fullLesson?.module_id || (Array.isArray(fullLesson?.module_ids) ? fullLesson.module_ids[0] : 0) || 0;
				setAssignedModuleId(Number(mid));
				setPendingModuleId(mid ? String(mid) : '');
			})
			.catch(() => {
				setTitle(lesson?.title || '');
				setContent('');
				setReleaseType('immediate');
				setReleaseDays('');
				setReleaseDate('');
				setAssignedModuleId(0);
				setPendingModuleId('');
			})
			.finally(() => {
				setLoading(false);
			});

		// Load all modules for the reassign dropdown.
		setModulesLoading(true);
		getAllModules()
			.then((modules) => setAllModules(modules))
			.catch(() => {})
			.finally(() => setModulesLoading(false));
	}, [lesson?.id]);

	const handleReassignModule = async () => {
		const moduleId = parseInt(pendingModuleId, 10);
		if (!moduleId || moduleId === assignedModuleId) {
			return;
		}
		setAssignSaving(true);
		setAssignError('');
		try {
			await assignLessonToModule(lesson.id, moduleId);
			setAssignedModuleId(moduleId);
			setAssignError('');
		} catch {
			setAssignError(__('Failed to update module assignment. Please try again.', 'learnkit'));
		} finally {
			setAssignSaving(false);
		}
	};

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

	const moduleOptions = [
		{ label: __('— select a module —', 'learnkit'), value: '' },
		...allModules.map((m) => ({
			label: m.title || `Module #${m.id}`,
			value: String(m.id),
		})),
	];

	const currentModuleName = allModules.find((m) => m.id === assignedModuleId)?.title || null;

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

				{loading ? (
					<div style={{ textAlign: 'center', padding: '32px' }}>
						<Spinner />
					</div>
				) : (
					<>
						<TextControl
							label={__('Lesson Title', 'learnkit')}
							value={title}
							onChange={setTitle}
							placeholder={__('Enter lesson title', 'learnkit')}
						/>

						<TextareaControl
							label={__('Content', 'learnkit')}
							value={content}
							onChange={setContent}
							rows={8}
							placeholder={__('Enter lesson content', 'learnkit')}
						/>

						{/* Drip settings */}
						<hr style={{ margin: '24px 0' }} />
						<h3 style={{ marginBottom: '12px' }}>{__('Content Release', 'learnkit')}</h3>

						<SelectControl
							label={__('Release Type', 'learnkit')}
							value={releaseType}
							options={[
								{ label: __('Immediate', 'learnkit'), value: 'immediate' },
								{ label: __('Days after enrollment', 'learnkit'), value: 'days_after_enrollment' },
								{ label: __('Specific date', 'learnkit'), value: 'specific_date' },
							]}
							onChange={setReleaseType}
						/>

						{releaseType === 'days_after_enrollment' && (
							<TextControl
								label={__('Days after enrollment', 'learnkit')}
								type="number"
								value={releaseDays}
								onChange={setReleaseDays}
								min={0}
								help={__('The lesson becomes available this many days after the student enrolls.', 'learnkit')}
							/>
						)}

						{releaseType === 'specific_date' && (
							<TextControl
								label={__('Release date', 'learnkit')}
								type="date"
								value={releaseDate ? releaseDate.substring(0, 10) : ''}
								onChange={(val) => setReleaseDate(val)}
								help={__('The lesson becomes available on this date.', 'learnkit')}
							/>
						)}

						{/* Module Assignment (one-to-one) */}
						<hr style={{ margin: '24px 0' }} />
						<h3 style={{ marginBottom: '12px' }}>{__('Module Assignment', 'learnkit')}</h3>

						{assignError && (
							<div className="notice notice-error" style={{ marginBottom: '8px' }}>
								<p>{assignError}</p>
							</div>
						)}

						{modulesLoading ? (
							<div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
								<Spinner />
								<span>{__('Loading modules…', 'learnkit')}</span>
							</div>
						) : (
							<>
								<p style={{ marginBottom: '8px', color: '#757575' }}>
									{assignedModuleId && currentModuleName
										? <>
											{__('Currently assigned to:', 'learnkit')} <strong>{currentModuleName}</strong>
										</>
										: __('Not assigned to any module.', 'learnkit')
									}
								</p>
								<div style={{ display: 'flex', alignItems: 'flex-end', gap: '8px' }}>
									<SelectControl
										label={__('Reassign to module', 'learnkit')}
										value={pendingModuleId}
										options={moduleOptions}
										onChange={setPendingModuleId}
										style={{ marginBottom: 0 }}
									/>
									<Button
										variant="secondary"
										isSmall
										onClick={handleReassignModule}
										disabled={!pendingModuleId || parseInt(pendingModuleId, 10) === assignedModuleId || assignSaving}
										style={{ marginTop: '22px' }}
									>
										{assignSaving ? <Spinner /> : __('Move', 'learnkit')}
									</Button>
								</div>
							</>
						)}
					</>
				)}
			</div>

			<div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '24px' }}>
				<Button variant="tertiary" onClick={onClose}>
					{__('Cancel', 'learnkit')}
				</Button>
				<Button variant="primary" onClick={handleSave} disabled={saving || loading}>
					{saving ? <Spinner /> : __('Save Lesson', 'learnkit')}
				</Button>
			</div>
		</Modal>
	);
}

export default LessonEditorModal;
