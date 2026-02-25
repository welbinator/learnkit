/**
 * EditModuleModal Component
 *
 * Modal for creating/editing a module with title, description,
 * assigned courses, and drag-to-reorder assigned lessons.
 *
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { Modal, Button, TextControl, TextareaControl, SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { createPortal } from 'react-dom';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	arrayMove,
	SortableContext,
	sortableKeyboardCoordinates,
	useSortable,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
	getAllLessons,
	assignLessonToModule,
	removeLessonFromModule,
	createLesson,
	reorderLessons,
} from '../utils/api';
import QuizModal from './QuizModal';

// ── Sortable lesson row ───────────────────────────────────────────────────────

function SortableLesson({ lesson, onRemove, onEdit, onEditQuiz, disabled }) {
	const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id: lesson.id });

	const style = { transform: CSS.Transform.toString(transform), transition };

	return (
		<li ref={setNodeRef} style={style} className="lesson-item">
			<span className="drag-handle" { ...attributes } { ...listeners }>⋮⋮</span>
			<div style={{ flex: 1 }}>
				<span className="lesson-title">{ lesson.title }</span>
				<div className="lesson-actions">
					<Button isSmall onClick={ () => onEdit( lesson ) }>
						{ __( 'Edit Content', 'learnkit' ) }
					</Button>
					<Button isSmall onClick={ () => onEditQuiz( lesson ) }>
						{ __( 'Quiz', 'learnkit' ) }
					</Button>
					<Button isSmall isDestructive disabled={ disabled } onClick={ () => onRemove( lesson.id ) }>
						{ __( 'Delete', 'learnkit' ) }
					</Button>
				</div>
			</div>
		</li>
	);
}

// ── Main component ────────────────────────────────────────────────────────────

const EditModuleModal = ({ module, isOpen, onClose, onSave }) => {
	const [title, setTitle]             = useState('');
	const [description, setDescription] = useState('');

	// Lesson state
	const [assignedLessons, setAssignedLessons]   = useState([]);
	const [allLessons, setAllLessons]             = useState([]);
	const [lessonsLoading, setLessonsLoading]     = useState(false);
	const [selectedLessonId, setSelectedLessonId] = useState('');
	const [newLessonTitle, setNewLessonTitle]     = useState('');
	const [lessonBusy, setLessonBusy]             = useState(false);

	// Quiz modal state
	const [quizModalOpen, setQuizModalOpen] = useState(false);
	const [quizLesson, setQuizLesson]       = useState(null);

	
	const isEditing = !!( module && module.id && !module._courseId );

	const sensors = useSensors(
		useSensor( PointerSensor ),
		useSensor( KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates } )
	);

	// ── Sync form fields when module changes ─────────────────────────────────

	useEffect( () => {
		if ( module ) {
			setTitle( module.title || '' );
			setDescription( module.description || '' );
		} else {
			setTitle( '' );
			setDescription( '' );
		}
		setAssignedLessons( [] );
		setAllLessons( [] );
		setSelectedLessonId( '' );
		setNewLessonTitle( '' );
	}, [module] );

	// ── Load lessons + courses when editing ──────────────────────────────────

	useEffect( () => {
		if ( !isOpen || !isEditing ) return;
		loadLessons();
	}, [isOpen, isEditing, module?.id] ); // eslint-disable-line react-hooks/exhaustive-deps

	const loadLessons = async () => {
		setLessonsLoading( true );
		try {
			const [assigned, all] = await Promise.all( [
				getAllLessons( module.id ),
				getAllLessons(),
			] );
			setAssignedLessons( assigned );
			setAllLessons( all );
		} catch ( err ) {
			console.error( 'Failed to load lessons:', err );
		} finally {
			setLessonsLoading( false );
		}
	};

	// ── Lesson handlers ───────────────────────────────────────────────────────

	const handleRemoveLesson = async ( lessonId ) => {
		setLessonBusy( true );
		try {
			await removeLessonFromModule( lessonId, module.id );
			await loadLessons();
		} catch ( err ) {
			console.error( 'Failed to remove lesson:', err );
		} finally {
			setLessonBusy( false );
		}
	};

	const handleAssignLesson = async () => {
		if ( !selectedLessonId ) return;
		setLessonBusy( true );
		try {
			await assignLessonToModule( parseInt( selectedLessonId, 10 ), module.id );
			setSelectedLessonId( '' );
			await loadLessons();
		} catch ( err ) {
			console.error( 'Failed to assign lesson:', err );
		} finally {
			setLessonBusy( false );
		}
	};

	const handleCreateLesson = async () => {
		if ( !newLessonTitle.trim() ) return;
		setLessonBusy( true );
		try {
			await createLesson( module.id, { title: newLessonTitle.trim() } );
			setNewLessonTitle( '' );
			await loadLessons();
		} catch ( err ) {
			console.error( 'Failed to create lesson:', err );
		} finally {
			setLessonBusy( false );
		}
	};

	const handleDragEnd = async ( event ) => {
		const { active, over } = event;
		if ( !over || active.id === over.id ) return;

		const oldIndex  = assignedLessons.findIndex( (l) => l.id === active.id );
		const newIndex  = assignedLessons.findIndex( (l) => l.id === over.id );
		const reordered = arrayMove( assignedLessons, oldIndex, newIndex );

		setAssignedLessons( reordered );
		try {
			await reorderLessons( module.id, reordered.map( (l) => l.id ) );
		} catch ( err ) {
			console.error( 'Failed to save lesson order:', err );
		}
	};

	const handleEditLesson = ( lesson ) => {
		window.location.href = `/wp-admin/post.php?post=${ lesson.id }&action=edit`;
	};

	const handleEditQuiz = ( lesson ) => {
		setQuizLesson( lesson );
		setQuizModalOpen( true );
	};

	// ── Save / close ──────────────────────────────────────────────────────────

	const handleSave = () => {
		if ( !title.trim() ) {
			alert( __( 'Please enter a module title', 'learnkit' ) );
			return;
		}
		onSave( {
			title:       title.trim(),
			description: description.trim(),
			courseId:    module?._courseId || null,
		} );
		setTitle( '' );
		setDescription( '' );
	};

	const handleClose = () => {
		setTitle( '' );
		setDescription( '' );
		setAssignedLessons( [] );
		setAllLessons( [] );
		setSelectedLessonId( '' );
		setNewLessonTitle( '' );
		onClose();
	};

	if ( !isOpen ) return null;

	// Derived lists
	const assignedIds      = new Set( assignedLessons.map( (l) => l.id ) );
	const unassignedLessons = allLessons.filter( (l) => !assignedIds.has( l.id ) );
	const lessonOptions    = [
		{ label: __( '— Select a lesson —', 'learnkit' ), value: '' },
		...unassignedLessons.map( (l) => ( { label: l.title, value: String( l.id ) } ) ),
	];

	return (
		<>
			<Modal
				title={ isEditing ? __( 'Edit Module', 'learnkit' ) : __( 'Create Module', 'learnkit' ) }
				onRequestClose={ handleClose }
				className="learnkit-edit-module-modal"
			>
				<div className="modal-content">

					{/* Module Title */}
					<TextControl
						label={ __( 'Module Title', 'learnkit' ) }
						value={ title }
						onChange={ setTitle }
						placeholder={ __( 'e.g. Getting Started with the Basics', 'learnkit' ) }
					/>

					{/* Description */}
					<TextareaControl
						label={ __( 'Description (Optional)', 'learnkit' ) }
						value={ description }
						onChange={ setDescription }
						placeholder={ __( 'A brief description of this module...', 'learnkit' ) }
						rows={ 3 }
					/>

					{/* ── Assigned Lessons (edit mode only) ── */}
					{ isEditing && (
						<div className="learnkit-module-lesson-assignments" style={{ marginTop: '20px' }}>
							<hr />
							<h3 style={{ marginBottom: '8px' }}>{ __( 'Lessons', 'learnkit' ) }</h3>

							{ lessonsLoading ? (
								<div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
									<Spinner />
									<span>{ __( 'Loading lessons…', 'learnkit' ) }</span>
								</div>
							) : (
								<>
									{ assignedLessons.length > 0 ? (
										<DndContext
											sensors={ sensors }
											collisionDetection={ closestCenter }
											onDragEnd={ handleDragEnd }
										>
											<SortableContext
												items={ assignedLessons.map( (l) => l.id ) }
												strategy={ verticalListSortingStrategy }
											>
												<ul className="lesson-list" style={{ marginBottom: '12px' }}>
													{ assignedLessons.map( (lesson) => (
														<SortableLesson
															key={ lesson.id }
															lesson={ lesson }
															onRemove={ handleRemoveLesson }
															onEdit={ handleEditLesson }
															onEditQuiz={ handleEditQuiz }
															disabled={ lessonBusy }
														/>
													) ) }
												</ul>
											</SortableContext>
										</DndContext>
									) : (
										<p style={{ color: '#757575', marginBottom: '12px' }}>
											{ __( 'No lessons assigned yet.', 'learnkit' ) }
										</p>
									) }

									{/* Add existing lesson */}
									<div style={{ display: 'flex', gap: '8px', alignItems: 'flex-end', marginBottom: '12px' }}>
										<div style={{ flex: 1 }}>
											<SelectControl
												label={ __( 'Add existing lesson', 'learnkit' ) }
												value={ selectedLessonId }
												options={ lessonOptions }
												onChange={ setSelectedLessonId }
												disabled={ lessonBusy || unassignedLessons.length === 0 }
											/>
										</div>
										<Button
											variant="secondary"
											onClick={ handleAssignLesson }
											disabled={ !selectedLessonId || lessonBusy }
											style={{ marginBottom: '8px' }}
										>
											{ __( 'Add', 'learnkit' ) }
										</Button>
									</div>

									{/* Create new lesson */}
									<div style={{ display: 'flex', gap: '8px', alignItems: 'flex-end' }}>
										<div style={{ flex: 1 }}>
											<TextControl
												label={ __( 'Create new lesson', 'learnkit' ) }
												value={ newLessonTitle }
												onChange={ setNewLessonTitle }
												placeholder={ __( 'New lesson title…', 'learnkit' ) }
												onKeyPress={ (e) => e.key === 'Enter' && handleCreateLesson() }
												disabled={ lessonBusy }
											/>
										</div>
										<Button
											variant="secondary"
											onClick={ handleCreateLesson }
											disabled={ !newLessonTitle.trim() || lessonBusy }
											style={{ marginBottom: '8px' }}
										>
											{ __( 'Create', 'learnkit' ) }
										</Button>
									</div>
								</>
							) }
						</div>
					) }

						{/* Action Buttons */}
					<div className="modal-actions" style={{ marginTop: '24px' }}>
						<Button variant="secondary" onClick={ handleClose }>
							{ __( 'Cancel', 'learnkit' ) }
						</Button>
						<Button variant="primary" onClick={ handleSave } disabled={ !title.trim() }>
							{ isEditing ? __( 'Save Changes', 'learnkit' ) : __( 'Create Module', 'learnkit' ) }
						</Button>
					</div>
				</div>
			</Modal>

			{/* Quiz modal — portaled to body to escape stacking context */}
			{ quizModalOpen && createPortal(
				<QuizModal
					isOpen={ quizModalOpen }
					onClose={ () => {
						setQuizModalOpen( false );
						setQuizLesson( null );
					} }
					lessonId={ quizLesson?.id || null }
					lessonTitle={ quizLesson?.title || '' }
					contextType="lesson"
				/>,
				document.body
			) }
		</>
	);
};

export default EditModuleModal;
