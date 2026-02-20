import React from 'react';
import { Modal } from '@wordpress/components';
import QuizBuilder from './QuizBuilder';

/**
 * Quiz Modal Component
 * 
 * Modal wrapper for the Quiz Builder.
 */
const QuizModal = ({ isOpen, onClose, lessonId, moduleId, courseId, lessonTitle, contextType }) => {
	console.log('QuizModal render:', { isOpen, lessonId, moduleId, courseId, contextType });
	
	if (!isOpen) {
		console.log('QuizModal: isOpen is false, returning null');
		return null;
	}

	console.log('QuizModal: Rendering modal...');

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

	console.log('About to return Modal component...');

	return (
		<div style={{
			position: 'fixed',
			top: 0,
			left: 0,
			right: 0,
			bottom: 0,
			backgroundColor: 'rgba(0,0,0,0.5)',
			zIndex: 999999,
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			padding: '20px'
		}}>
			<div style={{
				backgroundColor: 'white',
				borderRadius: '12px',
				maxWidth: '900px',
				width: '100%',
				maxHeight: '90vh',
				display: 'flex',
				flexDirection: 'column',
				overflow: 'hidden'
			}}>
				{/* Header */}
				<div style={{ 
					padding: '20px 24px', 
					borderBottom: '1px solid #dcdcde',
					display: 'flex',
					alignItems: 'center',
					gap: '12px'
				}}>
					<button 
						onClick={onClose}
						style={{ 
							width: '36px',
							height: '36px',
							borderRadius: '8px',
							border: 'none',
							background: '#1d2327',
							color: 'white',
							cursor: 'pointer',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							fontSize: '18px',
							lineHeight: 1
						}}
					>
						☰
					</button>
					<div>
						<div style={{ fontSize: '12px', color: '#757575', marginBottom: '2px' }}>
							Quiz for Module
						</div>
						<h2 style={{ margin: 0, fontSize: '18px', fontWeight: 600 }}>
							{lessonTitle}
						</h2>
					</div>
					<button 
						onClick={onClose}
						style={{ 
							marginLeft: 'auto',
							fontSize: '24px', 
							border: 'none', 
							background: 'none', 
							cursor: 'pointer',
							color: '#757575',
							padding: '4px 8px'
						}}
					>
						×
					</button>
				</div>

				{/* Content */}
				<div style={{ padding: '24px', overflowY: 'auto', flex: 1 }}>
					<QuizBuilder 
						lessonId={lessonId} 
						moduleId={moduleId}
						courseId={courseId}
						contextType={contextType}
					/>
				</div>
			</div>
		</div>
	);
};

export default QuizModal;
