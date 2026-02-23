/**
 * Lesson Editor Modal Component
 *
 * Modal for editing lesson content, drip settings, and module assignments.
 *
 * @package LearnKit
 * @since 0.5.0
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, TextControl, TextareaControl, SelectControl, Spinner } from '@wordpress/components';
import { updateLesson, getLesson, getAllModules, assignLessonToModule, removeLessonFromModule } from '../utils/api';

function LessonEditorModal({ lesson, onSave, onClose }) {
	const [title, setTitle] = useState('');
	const [content, setContent] = useState('');
	const [releaseType, setReleaseType] = useState('immediate');
	const [releaseDays, setReleaseDays] = useState('');
	const [releaseDate, setReleaseDate] = useState('');
	const [saving, setSaving] = useState(false);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState('');

	// Module assignment state.
	const [allModules, setAllModules] = useState([]);
	const [assignedModuleIds, setAssignedModuleIds] = useState([]);
	const [modulesLoading, setModulesLoading] = useState(false);
	const [selectedModuleToAdd, setSelectedModuleToAdd] = useState('');
	const [assignError, setAssignError] = useState('');

	// Fetch full lesson data when modal opens to avoid saving over existing content.
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
				// Seed module assignments from the API response.
				const ids = Array.isArray(fullLesson?.module_ids)
					? fullLesson.module_ids.map(Number)
					: [];
				setAssignedModuleIds(ids);
			})
			.catch(() => {
				// Fall back to shallow data from the course structure endpoint.
				setTitle(lesson?.title || '');
				setContent('');
				setReleaseType('immediate');
				setReleaseDays('');
				setReleaseDate('');
				setAssignedModuleIds([]);
			})
			.finally(() => {
				setLoading(false);
			});

		// Also load all modules for the assignment picker.
		setModulesLoading(true);
		getAllModules()
			.then((modules) => {
				setAllModules(modules);
			})
			.catch(() => {
				// Non-fatal: assignment picker simply won't show options.
			})
			.finally(() => {
				setModulesLoading(false);
			});
	}, [lesson?.id]);

	const handleAddModule = async () => {
		const moduleId = parseInt(selectedModuleToAdd, 10);
		if (!moduleId) {
			return;
		}

		try {
			await assignLessonToModule(lesson.id, moduleId);
			setAssignedModuleIds((prev) => [...prev, moduleId]);
			setSelectedModuleToAdd('');
			setAssignError('');
		} catch {
			setAssignError(__('Failed to assign module. Please try again.', 'learnkit'));
		}
	};

	const handleRemoveModule = async (moduleId) => {
		try {
			await removeLessonFromModule(lesson.id, moduleId);
			setAssignedModuleIds((prev) => prev.filter((id) => id !== moduleId));
			setAssignError('');
		} catch {
			setAssignError(__('Failed to remove module assignment. Please try again.', 'learnkit'));
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

	// Modules not yet assigned to this lesson.
	const unassignedModules = allModules.filter(
		(m) => !assignedModuleIds.includes(m.id)
	);

	const addModuleOptions = [
		{ label: __('— select a module —', 'learnkit'), value: '' },
		...unassignedModules.map((m) => ({
			label: m.title || `Module #${m.id}`,
			value: String(m.id),
		})),
	];

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
						<p>{__('Loading lesson…', 'learnkit')}</p>
					</div>
				) : (
					<>
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

						{/* Module Assignments */}
						<hr style={{ margin: '24px 0' }} />
						<h3 style={{ marginBottom: '12px' }}>{__('Assigned to Modules', 'learnkit')}</h3>

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
								{assignedModuleIds.length === 0 ? (
									<p style={{ color: '#757575', marginBottom: '12px' }}>
										{__('Not assigned to any module yet.', 'learnkit')}
									</p>
								) : (
									<ul style={{ listStyle: 'none', margin: '0 0 12px', padding: 0 }}>
										{assignedModuleIds.map((moduleId) => {
											const mod = allModules.find((m) => m.id === moduleId);
											return (
												<li
													key={moduleId}
													style={{
														display: 'flex',
														alignItems: 'center',
														justifyContent: 'space-between',
														padding: '4px 0',
														borderBottom: '1px solid #e0e0e0',
													}}
												>
													<span>{mod ? mod.title : `Module #${moduleId}`}</span>
													<Button
														isSmall
														isDestructive
														onClick={() => handleRemoveModule(moduleId)}
														aria-label={__('Remove module assignment', 'learnkit')}
													>
														&times;
													</Button>
												</li>
											);
										})}
									</ul>
								)}

								{unassignedModules.length > 0 && (
									<div style={{ display: 'flex', alignItems: 'flex-end', gap: '8px' }}>
										<SelectControl
											label={__('Add to module', 'learnkit')}
											value={selectedModuleToAdd}
											options={addModuleOptions}
											onChange={setSelectedModuleToAdd}
											style={{ marginBottom: 0 }}
										/>
										<Button
											variant="secondary"
											isSmall
											onClick={handleAddModule}
											disabled={!selectedModuleToAdd}
											style={{ marginTop: '22px' }}
										>
											{__('Add', 'learnkit')}
										</Button>
									</div>
								)}
							</>
						)}
					</>
				)}

				<div className="learnkit-modal-actions" style={{ marginTop: '24px' }}>
					<Button
						variant="primary"
						onClick={handleSave}
						disabled={saving || loading}
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
