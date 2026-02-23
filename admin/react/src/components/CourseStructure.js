/**
 * Course Structure Component
 * 
 * Displays hierarchical course structure with drag-and-drop reordering.
 * 
 * @package LearnKit
 * @since 0.2.0
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
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

function SortableModule({ moduleId, module, onEdit, onDelete, onEditLesson, onDeleteLesson, onReorderLessons, onEditQuiz }) {
	const [localLessons, setLocalLessons] = useState(module?.lessons || []);

	useEffect(() => {
		setLocalLessons(module?.lessons || []);
	}, [module?.lessons]);

	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
	} = useSortable({ id: moduleId });

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
				<h4>{module?.title || __('Untitled Module', 'learnkit')}</h4>
				<div className="module-actions">
					<Button isSmall onClick={() => onEdit(module)}>
						{__('Edit', 'learnkit')}
					</Button>
					<Button isSmall isDestructive onClick={() => onDelete(module.id)}>
						{__('Delete', 'learnkit')}
					</Button>
				</div>
			</div>

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
									onEditQuiz={onEditQuiz}
								/>
							))}
						</ul>
					</SortableContext>
				</DndContext>
			)}

			{localLessons.length === 0 && (
				<p className="empty-lessons">{__('No lessons yet. Edit this module to add lessons.', 'learnkit')}</p>
			)}
		</div>
	);
}

function SortableLesson({ lesson, onEdit, onDelete, onEditQuiz }) {
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
				<Button isSmall onClick={() => onEditQuiz(lesson)}>
					{__('Quiz', 'learnkit')}
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
	onEditLesson,
	onDeleteLesson,
	onReorderModules,
	onReorderLessons,
	onEditQuiz,
}) {
	const [localModules, setLocalModules] = useState(structure?.modules || []);

	// Update localModules when structure prop changes
	useEffect(() => {
		if (structure?.modules) {
			setLocalModules(structure.modules);
		}
	}, [structure]);

	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		})
	);

	const handleDragEnd = (event) => {
		const { active, over } = event;

		if (!over || active.id === over.id) {
			return;
		}

		setLocalModules((prevModules) => {
			const oldIndex = prevModules.findIndex((m) => m.id === active.id);
			const newIndex = prevModules.findIndex((m) => m.id === over.id);
			
			if (oldIndex === -1 || newIndex === -1) {
				return prevModules;
			}

			const reorderedModules = arrayMove(prevModules, oldIndex, newIndex);
			
			// Call parent callback with full module objects (not just IDs)
			if (onReorderModules) {
				onReorderModules(reorderedModules);
			}
			
			return reorderedModules;
		});
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
							moduleId={module.id}
							module={module}
							onEdit={onEditModule}
							onDelete={onDeleteModule}
							onEditLesson={onEditLesson}
							onDeleteLesson={onDeleteLesson}
							onReorderLessons={onReorderLessons}
							onEditQuiz={onEditQuiz}
						/>
					))}
				</SortableContext>
			</DndContext>
		</div>
	);
}

export default CourseStructure;
