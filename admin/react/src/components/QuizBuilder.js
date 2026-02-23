import React, { useState, useEffect } from 'react';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

/**
 * Quiz Builder Component
 * 
 * Allows instructors to create and manage quiz questions for a lesson, module, or course.
 */
const QuizBuilder = ({ lessonId, courseId, lessonTitle, contextType, onClose }) => {
	const [quiz, setQuiz] = useState(null);
	const [quizTitle, setQuizTitle] = useState('');
	const [questions, setQuestions] = useState([]);
	const [settings, setSettings] = useState({
		passingScore: 70,
		timeLimit: 0,
		attemptsAllowed: 0,
		requiredToComplete: false
	});
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [editingQuestion, setEditingQuestion] = useState(null);

	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		})
	);

	const contextId = lessonId || courseId;

	useEffect(() => {
		if (contextId) {
			loadQuiz();
		}
	}, [contextId]);

	const loadQuiz = async () => {
		setLoading(true);
		try {
			let queryParam = '';
			if (lessonId) {
				queryParam = `lesson_id=${lessonId}`;
			} else if (courseId) {
				queryParam = `course_id=${courseId}`;
			}

			// Check if quiz exists for this context
			const response = await fetch(`${window.wpApiSettings.root}learnkit/v1/quizzes?${queryParam}`, {
				headers: {
					'X-WP-Nonce': window.wpApiSettings.nonce
				}
			});

			if (response.ok) {
				const data = await response.json();
				if (data.length > 0) {
					const quizData = data[0];
					setQuiz(quizData);
					setQuizTitle(quizData.title || '');
					setQuestions(JSON.parse(quizData.meta._lk_questions || '[]'));
					setSettings({
						passingScore: parseInt(quizData.meta._lk_passing_score) || 70,
						timeLimit: parseInt(quizData.meta._lk_time_limit) || 0,
						attemptsAllowed: parseInt(quizData.meta._lk_attempts_allowed) || 0,
						requiredToComplete: quizData.meta._lk_required_to_complete === '1' || quizData.meta._lk_required_to_complete === true
					});
				} else {
					// New quiz — default title from lesson title
					const defaultTitle = lessonTitle ? `${lessonTitle} Quiz` : (courseId ? 'Course Quiz' : 'Quiz');
					setQuizTitle(defaultTitle);
				}
			}
		} catch (error) {
			console.error('Error loading quiz:', error);
			alert('Failed to load quiz: ' + error.message);
		} finally {
			setLoading(false);
		}
	};

	const saveQuiz = async () => {
		setSaving(true);
		try {
			const meta = {
				_lk_passing_score: settings.passingScore,
				_lk_time_limit: settings.timeLimit,
				_lk_attempts_allowed: settings.attemptsAllowed,
				_lk_required_to_complete: settings.requiredToComplete,
				_lk_questions: JSON.stringify(questions)
			};

			if (lessonId) {
				meta._lk_lesson_id = lessonId;
			} else if (courseId) {
				meta._lk_course_id = courseId;
			}

			const quizData = {
				title: quizTitle,
				status: 'publish',
				meta
			};

			const url = quiz 
				? `${window.wpApiSettings.root}wp/v2/quizzes/${quiz.id}`
				: `${window.wpApiSettings.root}wp/v2/quizzes`;

			const method = quiz ? 'PUT' : 'POST';

			const response = await fetch(url, {
				method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.wpApiSettings.nonce
				},
				body: JSON.stringify(quizData)
			});

			if (response.ok) {
				const data = await response.json();
				setQuiz(data);
				alert('Quiz saved successfully! Close and reopen this course to see "Edit Quiz" button.');
			} else {
				throw new Error('Save failed');
			}
		} catch (error) {
			console.error('Error saving quiz:', error);
			alert('Failed to save quiz. Please try again.');
		} finally {
			setSaving(false);
		}
	};

	const addQuestion = (type) => {
		const newQuestion = {
			id: Date.now().toString(),
			type,
			question: '',
			points: 1,
			options: type === 'multiple_choice' ? ['', '', '', ''] : ['True', 'False'],
			correctAnswer: type === 'multiple_choice' ? 0 : 0
		};
		setQuestions([...questions, newQuestion]);
		setEditingQuestion(newQuestion.id);
	};

	const updateQuestion = (id, field, value) => {
		setQuestions(questions.map(q => 
			q.id === id ? { ...q, [field]: value } : q
		));
	};

	const updateQuestionOption = (id, index, value) => {
		setQuestions(questions.map(q => {
			if (q.id === id) {
				const newOptions = [...q.options];
				newOptions[index] = value;
				return { ...q, options: newOptions };
			}
			return q;
		}));
	};

	const deleteQuestion = (id) => {
		if (confirm('Are you sure you want to delete this question?')) {
			setQuestions(questions.filter(q => q.id !== id));
			if (editingQuestion === id) {
				setEditingQuestion(null);
			}
		}
	};

	const handleDragEnd = (event) => {
		const { active, over } = event;

		if (active.id !== over.id) {
			setQuestions((items) => {
				const oldIndex = items.findIndex(item => item.id === active.id);
				const newIndex = items.findIndex(item => item.id === over.id);
				return arrayMove(items, oldIndex, newIndex);
			});
		}
	};

	// Safety check - ensure we have a context ID
	if (!contextId) {
		return (
			<div className="lk-error" style={{ padding: '20px', color: '#dc3545' }}>
				<p>Error: No context ID provided. Please provide a lessonId, moduleId, or courseId.</p>
			</div>
		);
	}

	if (loading) {
		return <div className="lk-loading">Loading quiz...</div>;
	}

	return (
		<div className="lk-quiz-builder">
			{/* Quiz Title */}
			<div className="lk-quiz-title-field" style={{ marginBottom: '24px' }}>
				<label style={{ display: 'block', fontWeight: 600, marginBottom: '4px' }}>Quiz Title</label>
				<input
					type="text"
					value={quizTitle}
					onChange={(e) => setQuizTitle(e.target.value)}
					style={{ width: '100%', padding: '8px 12px', fontSize: '15px', border: '1px solid #dcdcde', borderRadius: '4px' }}
				/>
			</div>

			{/* Settings Section */}
			<div className="lk-quiz-settings">
				<h3>Quiz Settings</h3>
				<div className="lk-settings-grid">
					<div className="lk-setting">
						<label>Passing Score (%)</label>
						<input
							type="number"
							min="0"
							max="100"
							value={settings.passingScore}
							onChange={(e) => setSettings({ ...settings, passingScore: parseInt(e.target.value) })}
						/>
					</div>
					<div className="lk-setting">
						<label>Time Limit (minutes, 0 = unlimited)</label>
						<input
							type="number"
							min="0"
							value={settings.timeLimit}
							onChange={(e) => setSettings({ ...settings, timeLimit: parseInt(e.target.value) })}
						/>
					</div>
					<div className="lk-setting">
						<label>Attempts Allowed (0 = unlimited)</label>
						<input
							type="number"
							min="0"
							value={settings.attemptsAllowed}
							onChange={(e) => setSettings({ ...settings, attemptsAllowed: parseInt(e.target.value) })}
						/>
					</div>
				</div>
				<div className="lk-setting-checkbox">
					<label>Required to complete lesson</label>
					<input
						type="checkbox"
						checked={settings.requiredToComplete}
						onChange={(e) => setSettings({ ...settings, requiredToComplete: e.target.checked })}
					/>
				</div>
			</div>
			{/* Questions Section */}
			<div className="lk-quiz-questions">
				<div className="lk-questions-header">
					<h3>Questions <span>({questions.length})</span></h3>
					<div className="lk-add-question-buttons">
						<button
							type="button"
							className="lk-add-question-btn"
							onClick={() => addQuestion('multiple_choice')}
						>
							+ Multiple Choice
						</button>
						<button
							type="button"
							className="lk-add-question-btn"
							onClick={() => addQuestion('true_false')}
						>
							⊕ True / False
						</button>
					</div>
				</div>

				{questions.length === 0 ? (
					<div className="lk-no-questions">
						<div className="dashicons dashicons-menu-alt3"></div>
						<p>No questions yet</p>
						<small>Click a button above to add your first question.</small>
					</div>
				) : (
					<DndContext
						sensors={sensors}
						collisionDetection={closestCenter}
						onDragEnd={handleDragEnd}
					>
						<SortableContext
							items={questions.map(q => q.id)}
							strategy={verticalListSortingStrategy}
						>
							{questions.map((question, index) => (
								<QuestionItem
									key={question.id}
									question={question}
									index={index}
									isEditing={editingQuestion === question.id}
									onEdit={() => setEditingQuestion(question.id)}
									onCollapse={() => setEditingQuestion(null)}
									onUpdate={updateQuestion}
									onUpdateOption={updateQuestionOption}
									onDelete={deleteQuestion}
								/>
							))}
						</SortableContext>
					</DndContext>
				)}
			</div>

			{/* Save Button */}
			<div className="lk-quiz-actions">
				<button
					type="button"
					className="lk-btn lk-btn-secondary"
					onClick={() => { if (onClose) onClose(); }}
				>
					Cancel
				</button>
				<button
					type="button"
					className="lk-btn lk-btn-primary"
					onClick={saveQuiz}
					disabled={saving}
				>
					{saving ? 'Saving...' : 'Save Quiz'}
				</button>
			</div>
		</div>
	);
};

/**
 * Sortable Question Item Component
 */
const QuestionItem = ({ question, index, isEditing, onEdit, onCollapse, onUpdate, onUpdateOption, onDelete }) => {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
	} = useSortable({ id: question.id });

	const style = {
		transform: CSS.Transform.toString(transform),
		transition,
	};

	return (
		<div ref={setNodeRef} style={style} className={`lk-question-item ${isEditing ? 'editing' : ''}`}>
			<div className="lk-question-header">
				<div className="lk-question-drag" {...attributes} {...listeners}>
					<span className="dashicons dashicons-menu"></span>
				</div>
				<div className="lk-question-summary">
					<strong>Question {index + 1}</strong>
					<span className="lk-question-type">
						{question.type === 'multiple_choice' ? 'Multiple Choice' : 'True/False'}
					</span>
					{question.question && <span className="lk-question-preview">{question.question}</span>}
				</div>
				<div className="lk-question-actions">
					<button
						type="button"
						className="button button-small"
						onClick={isEditing ? onCollapse : onEdit}
					>
						{isEditing ? 'Collapse' : 'Edit'}
					</button>
					<button
						type="button"
						className="button button-small button-link-delete"
						onClick={() => onDelete(question.id)}
					>
						Delete
					</button>
				</div>
			</div>

			{isEditing && (
				<div className="lk-question-editor">
					<div className="lk-question-field">
						<label>Question Text</label>
						<textarea
							value={question.question}
							onChange={(e) => onUpdate(question.id, 'question', e.target.value)}
							placeholder="Enter your question..."
							rows="3"
						/>
					</div>

					<div className="lk-question-field">
						<label>Points</label>
						<input
							type="number"
							min="1"
							value={question.points}
							onChange={(e) => onUpdate(question.id, 'points', parseInt(e.target.value))}
						/>
					</div>

					{question.type === 'multiple_choice' && (
						<div className="lk-question-field">
							<label>Answer Options</label>
							{question.options.map((option, optIndex) => (
								<div key={optIndex} className="lk-option-row">
									<input
										type="radio"
										name={`correct-${question.id}`}
										checked={question.correctAnswer === optIndex}
										onChange={() => onUpdate(question.id, 'correctAnswer', optIndex)}
									/>
									<input
										type="text"
										value={option}
										onChange={(e) => onUpdateOption(question.id, optIndex, e.target.value)}
										placeholder={`Option ${optIndex + 1}`}
									/>
								</div>
							))}
							<p className="description">Select the radio button next to the correct answer.</p>
						</div>
					)}

					{question.type === 'true_false' && (
						<div className="lk-question-field">
							<label>Correct Answer</label>
							<select
								value={question.correctAnswer}
								onChange={(e) => onUpdate(question.id, 'correctAnswer', parseInt(e.target.value))}
							>
								<option value={0}>True</option>
								<option value={1}>False</option>
							</select>
						</div>
					)}
				</div>
			)}
		</div>
	);
};

export default QuizBuilder;
