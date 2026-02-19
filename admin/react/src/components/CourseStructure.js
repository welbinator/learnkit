/**
 * Course Structure Component
 * 
 * Displays hierarchical course structure with drag-and-drop reordering.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
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
	verticalListSortingStrategy,
	useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

function SortableModule({ module, onEdit, onDelete, onCreateLesson, onEditLesson, onDeleteLesson, onReorderLessons }) {
	const [showLessonInput, setShowLessonInput] = useState(false);
	const [lessonTitle, setLessonTitle] = useState('');
	const [localLessons, setLocalLessons] = useState(module.lessons || []);

	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
	} = useSortable({ id: module.id });

	const style = {
		transform: CSS.Transform.toString(transform),
		transition,
	};

	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		})
	);

	const handleAddLesson = () => {
		if (lessonTitle.trim()) {
			onCreateLesson(module.id, lessonTitle);
			setLessonTitle('');
			setShowLessonInput(false);
		}
	};

	const handleDragEnd = (event) => {
		const { active, over } = event;

		if (active.id !== over.id) {
			const oldIndex = localLessons.findIndex((l) => l.id === active.id);
			const newIndex = localLessons.findIndex((l) => l.id === over.id);
			const reorderedLessons = arrayMove(localLessons, oldIndex, newIndex);
			
			setLocalLessons(reorderedLessons);
			onReorderLessons(module.id, reorderedLessons.map((l) => l.id));
		}
	};

	return (
		<div ref={setNodeRef} style={style} className="learnkit-module">
			<div className="module-header">
				<span className="drag-handle" {...attributes} {...listeners}>
					⋮⋮
				</span>
				<h4>{module.title}</h4>
				<div className="module-actions">
					<Button isSmall onClick={() => onEdit(module)}>
						{__('Edit', 'learnkit')}
					</Button>
					<Button isSmall onClick={() => setShowLessonInput(!showLessonInput)}>
						{__('+ Lesson', 'learnkit')}
					</Button>
					<Button isSmall isDestructive onClick={() => onDelete(module.id)}>
						{__('Delete', 'learnkit')}
					</Button>
				</div>
			</div>

			{showLessonInput && (
				<div className="lesson-input">
					<input
						type="text"
						placeholder={__('Lesson title...', 'learnkit')}
						value={lessonTitle}
						onChange={(e) => setLessonTitle(e.target.value)}
						onKeyPress={(e) => e.key === 'Enter' && handleAddLesson()}
					/>
					<Button variant="primary" isSmall onClick={handleAddLesson}>
						{__('Add', 'learnkit')}
					</Button>
					<Button isSmall onClick={() => setShowLessonInput(false)}>
						{__('Cancel', 'learnkit')}
					</Button>
				</div>
			)}

			{localLessons.length > 0 && (
				<DndContext
					sensors={sensors}
					collisionDetection={closestCenter}
					onDragEnd={handleDragEnd}
				>
					<SortableContext
						items={localLessons.map((l) => l.id)}
						strategy={verticalListSortingStrategy}
					>
						<ul className="lesson-list">
							{localLessons.map((lesson) => (
								<SortableLesson
									key={lesson.id}
									lesson={lesson}
									onEdit={onEditLesson}
									onDelete={onDeleteLesson}
								/>
							))}
						</ul>
					</SortableContext>
				</DndContext>
			)}

			{localLessons.length === 0 && !showLessonInput && (
				<p className="empty-lessons">{__('No lessons yet. Add one!', 'learnkit')}</p>
			)}
		</div>
	);
}

function SortableLesson({ lesson, onEdit, onDelete }) {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
	} = useSortable({ id: lesson.id });

	const style = {
		transform: CSS.Transform.toString(transform),
		transition,
	};

	return (
		<li ref={setNodeRef} style={style} className="lesson-item">
			<span className="drag-handle" {...attributes} {...listeners}>
				⋮⋮
			</span>
			<span className="lesson-title">{lesson.title}</span>
			<div className="lesson-actions">
				<Button isSmall onClick={() => onEdit(lesson)}>
					{__('Edit Content', 'learnkit')}
				</Button>
				<Button isSmall isDestructive onClick={() => onDelete(lesson.id)}>
					{__('Delete', 'learnkit')}
				</Button>
			</div>
		</li>
	);
}

function CourseStructure({
	structure,
	onEditModule,
	onDeleteModule,
	onCreateLesson,
	onEditLesson,
	onDeleteLesson,
	onReorderModules,
	onReorderLessons,
}) {
	const [localModules, setLocalModules] = useState(structure?.modules || []);

	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		})
	);

	const handleDragEnd = (event) => {
		const { active, over } = event;

		if (active.id !== over.id) {
			const oldIndex = localModules.findIndex((m) => m.id === active.id);
			const newIndex = localModules.findIndex((m) => m.id === over.id);
			const reorderedModules = arrayMove(localModules, oldIndex, newIndex);
			
			setLocalModules(reorderedModules);
			onReorderModules(reorderedModules.map((m) => m.id));
		}
	};

	if (!structure || !structure.modules || structure.modules.length === 0) {
		return (
			<div className="learnkit-empty-structure">
				<p>{__('No modules yet. Create your first module!', 'learnkit')}</p>
			</div>
		);
	}

	return (
		<div className="learnkit-course-structure">
			<DndContext
				sensors={sensors}
				collisionDetection={closestCenter}
				onDragEnd={handleDragEnd}
			>
				<SortableContext
					items={localModules.map((m) => m.id)}
					strategy={verticalListSortingStrategy}
				>
					{localModules.map((module) => (
						<SortableModule
							key={module.id}
							module={module}
							onEdit={onEditModule}
							onDelete={onDeleteModule}
							onCreateLesson={onCreateLesson}
							onEditLesson={onEditLesson}
							onDeleteLesson={onDeleteLesson}
							onReorderLessons={onReorderLessons}
						/>
					))}
				</SortableContext>
			</DndContext>
		</div>
	);
}

export default CourseStructure;
