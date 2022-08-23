<?php
/**
 * File containing the Sensei_Course_Progress_Comments_Repository class.
 *
 * @package sensei
 */

namespace Sensei\Student_Progress\Repositories;

use DateTime;
use Sensei\Student_Progress\Models\Course_Progress_Comments;
use Sensei\Student_Progress\Models\Course_Progress_Interface;
use Sensei_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sensei_Course_Progress_Comments_Repository
 *
 * @since $$next-version$$
 */
class Course_Progress_Comments_Repository implements Course_Progress_Repository_Interface {
	/**
	 * Creates a new course progress.
	 *
	 * @param int $course_id The course ID.
	 * @param int $user_id The user ID.
	 * @return Course_Progress_Interface The course progress.
	 */
	public function create( int $course_id, int $user_id ): Course_Progress_Interface {
		$comment_id = Sensei_Utils::update_course_status( $user_id, $course_id, 'in-progress' );

		$comment    = get_comment( $comment_id );
		$created_at = new DateTime( $comment->comment_date );

		$comment_meta = get_comment_meta( $comment_id );
		if ( ! $comment_meta ) {
			$comment_meta = [];
		}
		$started_at = ! empty( $comment_meta['start'] ) ? new DateTime( $comment_meta['start'] ) : new DateTime();
		unset( $comment_meta['start'] );

		return new Course_Progress_Comments( $comment_id, $course_id, $user_id, $created_at, $comment->comment_approved, $started_at, null, $created_at, $comment_meta );
	}

	/**
	 * Gets a course progress.
	 *
	 * @param int $course_id The course ID.
	 * @param int $user_id The user ID.
	 * @return Course_Progress_Comments|null The course progress.
	 */
	public function get( int $course_id, int $user_id ): ?Course_Progress_Interface {
		$activity_args = [
			'post_id' => $course_id,
			'user_id' => $user_id,
			'type'    => 'sensei_course_status',
		];
		$comment       = Sensei_Utils::sensei_check_for_activity( $activity_args, true );
		if ( ! $comment ) {
			return null;
		}

		$created_at   = new DateTime( $comment->comment_date );
		$comment_meta = get_comment_meta( $comment->ID );
		if ( ! $comment_meta ) {
			$comment_meta = [];
		}
		$started_at = ! empty( $comment_meta['start'] ) ? new DateTime( $comment_meta['start'] ) : new DateTime();
		unset( $comment_meta['start'] );

		return new Course_Progress_Comments( (int) $comment->comment_ID, $course_id, $user_id, $created_at, $comment->comment_approved, $started_at, null, $created_at, $comment_meta );
	}

	/**
	 * Checks if a course progress exists.
	 *
	 * @param int $course_id The course ID.
	 * @param int $user_id The user ID.
	 * @return bool Whether the course progress exists.
	 */
	public function has( int $course_id, int $user_id ): bool {
		$activity_args = [
			'post_id' => $course_id,
			'user_id' => $user_id,
			'type'    => 'sensei_course_status',
		];
		$count         = Sensei_Utils::sensei_check_for_activity( $activity_args );
		return $count > 0;
	}

	/**
	 * Save course progress.
	 *
	 * @param Course_Progress_Interface $course_progress The course progress.
	 */
	public function save( Course_Progress_Interface $course_progress ): void {
		$metadata = $course_progress->get_metadata();
		if ( $course_progress->get_started_at() ) {
			$metadata['start'] = $course_progress->get_started_at()->format( 'Y-m-d H:i:s' );
		}
		Sensei_Utils::update_course_status( $course_progress->get_user_id(), $course_progress->get_course_id(), $course_progress->get_status(), $metadata );
	}
}
