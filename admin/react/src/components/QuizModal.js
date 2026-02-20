import React from 'react';
import { Modal } from '@wordpress/components';
import QuizBuilder from './QuizBuilder';

/**
 * Quiz Modal Component
 * 
 * Modal wrapper for the Quiz Builder.
 */
const QuizModal = ({ isOpen, onClose, lessonId, lessonTitle }) => {
	if (!isOpen) return null;

	// Determine title based on context
	const modalTitle = lessonTitle 
		? `Quiz for: ${lessonTitle}` 
		: 'Create Quiz';

	return (
		<Modal
			title={modalTitle}
			onRequestClose={onClose}
			className="learnkit-quiz-modal"
			style={{ maxWidth: '900px' }}
		>
			<QuizBuilder lessonId={lessonId} />
		</Modal>
	);
};

export default QuizModal;
