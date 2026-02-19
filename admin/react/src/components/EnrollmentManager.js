/**
 * EnrollmentManager Component
 * 
 * Admin interface for manually enrolling users in courses.
 * Displays enrolled users and provides enrollment controls.
 * 
 * @package LearnKit
 * @since 0.2.15
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, SelectControl, Spinner, Notice } from '@wordpress/components';
import { getEnrollments, createEnrollment, deleteEnrollment, getUsers } from '../utils/api';

const EnrollmentManager = ({ courseId, courseName }) => {
	const [enrollments, setEnrollments] = useState([]);
	const [users, setUsers] = useState([]);
	const [selectedUserId, setSelectedUserId] = useState('');
	const [loading, setLoading] = useState(true);
	const [enrolling, setEnrolling] = useState(false);
	const [error, setError] = useState(null);
	const [success, setSuccess] = useState(null);

	useEffect(() => {
		loadData();
	}, [courseId]);

	const loadData = async () => {
		setLoading(true);
		try {
			const [enrollmentsData, usersData] = await Promise.all([
				getEnrollments(courseId),
				getUsers()
			]);
			setEnrollments(enrollmentsData);
			setUsers(usersData);
		} catch (err) {
			setError(__('Failed to load enrollment data', 'learnkit'));
			console.error(err);
		} finally {
			setLoading(false);
		}
	};

	const handleEnroll = async () => {
		if (!selectedUserId) {
			setError(__('Please select a user', 'learnkit'));
			return;
		}

		setEnrolling(true);
		setError(null);
		setSuccess(null);

		try {
			await createEnrollment(selectedUserId, courseId);
			setSuccess(__('User enrolled successfully', 'learnkit'));
			setSelectedUserId('');
			await loadData();
		} catch (err) {
			setError(err.message || __('Failed to enroll user', 'learnkit'));
		} finally {
			setEnrolling(false);
		}
	};

	const handleUnenroll = async (enrollmentId, userName) => {
		if (!confirm(sprintf(__('Remove %s from this course?', 'learnkit'), userName))) {
			return;
		}

		try {
			await deleteEnrollment(enrollmentId);
			setSuccess(sprintf(__('%s has been unenrolled', 'learnkit'), userName));
			await loadData();
		} catch (err) {
			setError(err.message || __('Failed to unenroll user', 'learnkit'));
		}
	};

	if (loading) {
		return (
			<div className="learnkit-loading">
				<Spinner />
				<p>{__('Loading enrollments...', 'learnkit')}</p>
			</div>
		);
	}

	// Filter out already enrolled users
	const enrolledUserIds = enrollments.map(e => e.user_id);
	const availableUsers = users.filter(u => !enrolledUserIds.includes(u.id));

	return (
		<div className="learnkit-enrollment-manager">
			<div className="learnkit-enrollment-header">
				<h3>{sprintf(__('Enrollments for: %s', 'learnkit'), courseName)}</h3>
			</div>

			{error && (
				<Notice status="error" isDismissible onRemove={() => setError(null)}>
					{error}
				</Notice>
			)}

			{success && (
				<Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
					{success}
				</Notice>
			)}

			{/* Enrollment Form */}
			<div className="learnkit-enroll-form">
				<h4>{__('Enroll New User', 'learnkit')}</h4>
				<div className="learnkit-enroll-controls">
					<SelectControl
						label={__('Select User', 'learnkit')}
						value={selectedUserId}
						onChange={setSelectedUserId}
						options={[
							{ label: __('-- Choose a user --', 'learnkit'), value: '' },
							...availableUsers.map(user => ({
								label: user.email ? `${user.name} (${user.email})` : user.name,
								value: user.id.toString()
							}))
						]}
						disabled={enrolling}
					/>
					<Button
						isPrimary
						onClick={handleEnroll}
						disabled={!selectedUserId || enrolling}
						isBusy={enrolling}
					>
						{__('Enroll User', 'learnkit')}
					</Button>
				</div>
				{availableUsers.length === 0 && (
					<p className="description">
						{__('All users are already enrolled in this course.', 'learnkit')}
					</p>
				)}
			</div>

			{/* Enrolled Users List */}
			<div className="learnkit-enrolled-users">
				<h4>{sprintf(__('Enrolled Users (%d)', 'learnkit'), enrollments.length)}</h4>
				{enrollments.length === 0 ? (
					<p className="description">{__('No users enrolled yet.', 'learnkit')}</p>
				) : (
					<table className="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>{__('Name', 'learnkit')}</th>
								<th>{__('Email', 'learnkit')}</th>
								<th>{__('Enrolled Date', 'learnkit')}</th>
								<th>{__('Status', 'learnkit')}</th>
								<th>{__('Actions', 'learnkit')}</th>
							</tr>
						</thead>
						<tbody>
							{enrollments.map(enrollment => (
								<tr key={enrollment.id}>
									<td>{enrollment.user_name}</td>
									<td>{enrollment.user_email}</td>
									<td>{new Date(enrollment.enrolled_at).toLocaleDateString()}</td>
									<td>
										<span className={`status-badge status-${enrollment.status}`}>
											{enrollment.status}
										</span>
									</td>
									<td>
										<Button
											isDestructive
											isSmall
											onClick={() => handleUnenroll(enrollment.id, enrollment.user_name)}
										>
											{__('Unenroll', 'learnkit')}
										</Button>
									</td>
								</tr>
							))}
						</tbody>
					</table>
				)}
			</div>
		</div>
	);
};

export default EnrollmentManager;
