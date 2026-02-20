import React from 'react';
import { Modal } from '@wordpress/components';
import QuizBuilder from './QuizBuilder';

/**
 * Quiz Modal Component
 * 
 * Modal wrapper for the Quiz Builder.
 */
const QuizModal = ({ isOpen, onClose, lessonId, moduleId, courseId, lessonTitle, contextType }) => {
	if (!isOpen) return null;

	// Determine title based on context
	let modalTitle = 'Create Quiz';
	if (contextType === 'lesson' && lessonTitle) {
		modalTitle = `Quiz for Lesson: ${lessonTitle}`;
	} else if (contextType === 'module' && lessonTitle) {
		modalTitle = `Quiz for Module: ${lessonTitle}`;
	} else if (contextType === 'course') {
		modalTitle = 'Course Quiz';
	}

	// Pass the appropriate ID to QuizBuilder
	const quizContextId = lessonId || moduleId || courseId;

	return (
		<Modal
			title={modalTitle}
			onRequestClose={onClose}
			className="learnkit-quiz-modal"
			style={{ maxWidth: '900px' }}
		>
			<QuizBuilder 
				lessonId={lessonId} 
				moduleId={moduleId}
				courseId={courseId}
				contextType={contextType}
			/>
		</Modal>
	);
};

export default QuizModal;
