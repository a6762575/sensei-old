<?php
/**
 * File containing the class Sensei_MailPoet.
 *
 * @package sensei
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( \MailPoet\API\API::class ) ) {
	return;
}

/**
 * MailPoet integration class.
 *
 * Handles the integration with the MailPoet plugin,
 * creates a list for each course and group, adds enrolled students.
 *
 * @package Core
 * @since $$next-version$$
 */
class Sensei_MailPoet {

	/**
	 * MailPoet API handle.
	 *
	 * @var object
	 */
	private $mailpoet_api;

	/**
	 * Instance of the current handler.
	 *
	 * @var Sensei_MailPoet
	 */
	private static $instance;

	/**
	 * A list of Sensei Courses and Groups.
	 *
	 * @var array
	 */
	private $sensei_lists;

	/**
	 * All lists on MailPoet.
	 *
	 * @var array
	 */
	private $mail_poet_lists;

	/**
	 * Constructor
	 *
	 * @since $$next-version$$
	 */
	public function __construct() {
		if ( class_exists( \MailPoet\API\API::class ) ) {
			$this->mailpoet_api = \MailPoet\API\API::MP( 'v1' );
			if ( $this->mailpoet_api->isSetupComplete() ) {
				add_action( 'init', array( $this, 'maybe_schedule_sync_job' ), 10 );

				add_action( 'sensei_pro_student_groups_group_student_added', array( $this, 'add_student_subscriber' ), 10, 2 );
				add_action( 'sensei_pro_student_groups_group_students_removed', array( $this, 'remove_student_subscribers' ), 10, 2 );

				add_action( 'sensei_course_enrolment_status_changed', array( $this, 'maybe_add_student_course_subscriber' ), 10, 3 );
				add_action( 'sensei_admin_enrol_user', array( $this, 'add_student_subscriber' ), 10, 2 );
				add_action( 'sensei_manual_enrolment_learner_enrolled', array( $this, 'add_student_course_subscriber' ), 10, 2 );
				add_action( 'sensei_manual_enrolment_learner_withdrawn', array( $this, 'remove_student_course_subscriber' ), 10, 2 );
			}
		}
	}

	/**
	 * Get the instance of the class.
	 *
	 * @return Sensei_MailPoet
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Attach job to cron.
	 *
	 * @since $$next-version$$
	 *
	 * @access private
	 * @return void
	 */
	public function maybe_schedule_sync_job() {
		Sensei_Scheduler::instance()->schedule_job( new Sensei_MailPoet_Sync_Job() );
	}

	/**
	 * Get all groups and courses in Sensei.
	 *
	 * @since $$next-version$$
	 *
	 * @return array
	 */
	public function get_sensei_lists() {
		if ( empty( $this->sensei_lists ) ) {
			$this->sensei_lists = Sensei_MailPoet_Repository::fetch_sensei_lists();
		}

		return $this->sensei_lists;
	}

	/**
	 * Get all lists in MailPoet and use list name as index for easy local searching.
	 *
	 * @since $$next-version$$
	 *
	 * @return array
	 */
	public function get_mailpoet_lists() {
		if ( empty( $this->mail_poet_lists ) ) {
			$lists                 = $this->mailpoet_api->getLists();
			$this->mail_poet_lists = array_column( $lists, null, 'name' );
		}
		return $this->mail_poet_lists;
	}

	/**
	 * Add students as subscribers to lists on MailPoet for courses/groups.
	 *
	 * @since $$next-version$$
	 * @param array      $subscribers A list of students belonging to this group/course.
	 * @param string|int $list_id ID of the list on MailPoet.
	 *
	 * @return void
	 */
	public function add_subscribers( $subscribers, $list_id ) {
		$options = array(
			'send_confirmation_email'      => false,
			'schedule_welcome_email'       => false,
			'skip_subscriber_notification' => true,
		);
		foreach ( $subscribers as $subscriber ) {
			$subscriber_data = array(
				'email'      => $subscriber->user_email,
				'first_name' => $subscriber->display_name,
			);

			try {
				// All WordPress users are already on a list 'WordPress Users' on MailPoet.
				$mp_subscriber = $this->mailpoet_api->getSubscriber( $subscriber->user_email );
				$this->mailpoet_api->subscribeToList( $mp_subscriber['id'], $list_id, $options );
			} catch ( \MailPoet\API\MP\v1\APIException $exception ) {
				if ( 4 === $exception->getCode() ) {
					// subscriber does not exist.
					$this->mailpoet_api->addSubscriber( $subscriber_data, array( $list_id ), $options );
				}
			}
		}
	}

	/**
	 * Remove students as subscribers from lists on MailPoet for courses/groups.
	 *
	 * @since $$next-version$$
	 * @param array      $subscribers A list of students to remove.
	 * @param string|int $list_id ID of the list on MailPoet.
	 *
	 * @return void
	 */
	public function remove_subscribers( $subscribers, $list_id ) {
		foreach ( $subscribers as $subscriber ) {
			try {
				$mp_subscriber = $this->mailpoet_api->getSubscriber( $subscriber->user_email );
				$this->mailpoet_api->unsubscribeFromList( $mp_subscriber['id'], $list_id );
			} catch ( \MailPoet\API\MP\v1\APIException $exception ) {
				continue;
			}
		}
	}

	/**
	 * Creates a Sensei LMS MailPoet list with name and description.
	 *
	 * @since $$next-version$$
	 * @param string $list_name The name of the list.
	 * @param string $list_description The description of the list.
	 *
	 * @return int|string|null
	 */
	public function create_list( $list_name, $list_description ) {
		$new_list = array(
			'name'        => $list_name,
			'description' => $list_description,
		);
		try {
			$new_list = $this->mailpoet_api->addList( $new_list );
			return $new_list['id'];
		} catch ( \MailPoet\API\MP\v1\APIException $exception ) {
			// see https://github.com/mailpoet/mailpoet/blob/trunk/doc/api_methods/AddList.md#error-handling.
			return null;
		}
	}

	/**
	 * Add a student as a subscriber to a list on MailPoet for a course or group.
	 * If the list does not exist, it will be created.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 *
	 * @access private
	 * @return void
	 */
	public function add_student_subscriber( $post_id, $user_id ) {
		$student = get_user_by( 'id', $user_id );
		$post    = get_post( $post_id );
		if ( ! $student || ! $post ) {
			return;
		}

		$mailpoet_lists = $this->get_mailpoet_lists();
		$list_name      = Sensei_MailPoet_Repository::get_list_name( $post->post_title, $post->post_type );
		if ( ! array_key_exists( $list_name, $mailpoet_lists ) ) {
			$mp_list_id = $this->create_list( $list_name, $post->post_excerpt );
		} else {
			$mp_list_id = $mailpoet_lists[ $list_name ]['id'];
		}
		if ( null !== $mp_list_id ) {
			$this->add_subscribers( array( $student ), $mp_list_id );
		}
	}

	/**
	 * Remove students as subscribers from lists on MailPoet for courses/groups.
	 * This function is used when a student is removed from a group or course.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $user_ids Array of User IDs.
	 *
	 * @access private
	 * @return void
	 */
	public function remove_student_subscribers( $post_id, $user_ids ) {
		$students = get_users( array( 'include' => $user_ids ) );
		$post     = get_post( $post_id );
		if ( ! $students || ! $post ) {
			return;
		}

		$mailpoet_lists = $this->get_mailpoet_lists();
		$list_name      = Sensei_MailPoet_Repository::get_list_name( $post->post_title, 'group' );

		if ( ! array_key_exists( $list_name, $mailpoet_lists ) ) {
			return;
		} else {
			$mp_list_id = $mailpoet_lists[ $list_name ]['id'];
		}
		if ( null !== $mp_list_id ) {
			$this->remove_subscribers( $students, $mp_list_id );
		}
	}

	/**
	 * Decides whether to add or remove a student as subscriber to a course list on MailPoet. A proxy to SenseiMailPoet::add_student_subscriber and SenseiMailPoet::remove_student_subscribers.
	 *
	 * @param int  $user_id User ID.
	 * @param int  $course_id Post ID.
	 * @param bool $is_enrolled Enrollment status.
	 *
	 * @access private
	 * @return void
	 */
	public function maybe_add_student_course_subscriber( $user_id, $course_id, $is_enrolled ) {
		if ( $is_enrolled ) {
			$this->add_student_subscriber( $course_id, $user_id );
		} else {
			$this->remove_student_subscribers( $course_id, array( $user_id ) );
		}
	}

	/**
	 * Remove student as subscriber to a course list on MailPoet. A proxy to SenseiMailPoet::remove_student_subscribers.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Post ID.
	 *
	 * @access private
	 * @return void
	 */
	public function remove_student_course_subscriber( $user_id, $course_id ) {
		$this->remove_student_subscribers( $course_id, array( $user_id ) );
	}

	/**
	 * Add student as subscriber to a course list on MailPoet. A proxy to SenseiMailPoet::add_student_subscriber.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Post ID.
	 *
	 * @access private
	 * @return void
	 */
	public function add_student_course_subscriber( $user_id, $course_id ) {
		$this->add_student_subscriber( $course_id, $user_id );
	}

	/**
	 * Get subscribers of a MailPoet list.
	 * Returns null if the list does not exist.
	 *
	 * @since $$next-version$$
	 * @param int $mp_list_id MailPoet list ID.
	 *
	 * @return array
	 */
	public function get_mailpoet_subscribers( $mp_list_id ) {
		try {
			return $this->mailpoet_api->getSubscribers( array( 'listId' => (int) $mp_list_id ) );
		} catch ( \MailPoet\API\MP\v1\APIException $exception ) {
			return array();
		}
	}

	/**
	 * Figure out which subscribers to add and remove from lists on MailPoet for courses/groups.
	 *
	 * @since $$next-version$$
	 * @param array      $students A list of students belonging to this group/course list.
	 * @param array      $subscribers A list of subscribers already on MailPoet.
	 * @param string|int $list_id ID of the list on MailPoet.
	 *
	 * @return void
	 */
	public function sync_subscribers( $students, $subscribers, $list_id ) {
		$options = array(
			'send_confirmation_email'      => false,
			'schedule_welcome_email'       => false,
			'skip_subscriber_notification' => true,
		);
		foreach ( $students as $student ) {
			if ( ! array_key_exists( $student->user_email, $subscribers ) ) {
				$subscriber_data = array(
					'email'      => $student->user_email,
					'first_name' => $student->display_name,
				);

				try {
					$mp_subscriber = $this->mailpoet_api->getSubscriber( $student->user_email );
					$this->mailpoet_api->subscribeToList( $mp_subscriber['id'], $list_id, $options );
				} catch ( \MailPoet\API\MP\v1\APIException $exception ) {
					if ( 4 === $exception->getCode() ) {
						// subscriber does not exist.
						$this->mailpoet_api->addSubscriber( $subscriber_data, array( $list_id ), $options );
					}
				}
			}
		}
		foreach ( $subscribers as $subscriber ) {
			if ( ! array_key_exists( $subscriber['email'], $students ) ) {
				try {
					$mp_subscriber = $this->mailpoet_api->getSubscriber( $subscriber['email'] );
					$this->mailpoet_api->unsubscribeFromList( $mp_subscriber['id'], $list_id );
				} catch ( \MailPoet\API\MP\v1\APIException $exception ) {
					continue;
				}
			}
		}
	}
}
