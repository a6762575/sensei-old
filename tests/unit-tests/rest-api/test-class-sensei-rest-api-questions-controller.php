<?php
/**
 * Sensei REST API: Sensei_REST_API_Questions_Controller tests
 *
 * @package sensei-lms
 * @since   3.9.0
 */

/**
 * Class Sensei_REST_API_Questions_Controller tests.
 *
 * @group rest-api
 */
class Sensei_REST_API_Questions_Controller_Tests extends WP_Test_REST_TestCase {
	use Sensei_Test_Login_Helpers;

	/**
	 * A server instance that we use in tests to dispatch requests.
	 *
	 * @var WP_REST_Server $server
	 */
	protected $server;

	/**
	 * Sensei post factory.
	 *
	 * @var Sensei_Factory
	 */
	protected $factory;

	/**
	 * Test specific setup.
	 */
	public function setUp() {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		$this->factory = new Sensei_Factory();

		do_action( 'rest_api_init' );

		// We need to re-instantiate the controller on each tests to register any hooks.
		new Sensei_REST_API_Questions_Controller( 'question' );
	}

	/**
	 * Test specific teardown.
	 */
	public function tearDown() {
		parent::tearDown();

		global $wp_rest_server;
		$wp_rest_server = null;
	}

	/**
	 * Class wide setup.
	 *
	 * @param WP_UnitTest_Factory $factory Helper factory to create WP objects.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		$teacher_role = new Sensei_Teacher();
		$teacher_role->create_role();
	}

	/**
	 * Tests to make sure guests cannot access questions.
	 */
	public function testGuestsCannotAccessQuestions() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/questions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( $response->get_status(), 401 );
	}

	/**
	 * Tests to make sure visitors cannot access questions.
	 */
	public function testVisitorsCannotAccessQuestions() {
		$this->login_as_student();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/questions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( $response->get_status(), 403 );
	}

	/**
	 * Tests to make sure teachers can access their own questions.
	 */
	public function testTeacherCanAccessTheirOwnQuestions() {
		$this->login_as_teacher();
		$a = $this->factory->question->create();

		$this->login_as_teacher_b();
		$teacher_b_question = $this->factory->question->create();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/questions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( $response->get_status(), 200 );

		$data = $response->get_data();

		$this->assertEquals( 1, count( $data ), 'Should only have one question' );
		$this->assertEquals( $teacher_b_question, $data[0]['id'], 'Should only see teacher B\'s question' );
		$this->assertEquals( Sensei()->question->get_question_type( $teacher_b_question ), $data[0]['question-type-slug'], 'Question type slug should be filled' );
	}

	/**
	 * Tests to make sure admins can access all questions.
	 */
	public function testAdminCanAccessAllQuestions() {
		$question_ids = [];
		$this->login_as_teacher();
		$question_ids[] = $this->factory->question->create();

		$this->login_as_teacher_b();
		$question_ids[] = $this->factory->question->create();

		$this->login_as_admin();
		$question_ids[] = $this->factory->question->create();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/questions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( $response->get_status(), 200 );

		$data = $response->get_data();

		$this->assertEquals( count( $question_ids ), count( $data ), 'Should see all questions' );

		$fetched_question_ids = wp_list_pluck( $data, 'id' );
		sort( $fetched_question_ids );

		$this->assertEquals( $question_ids, $fetched_question_ids, 'All question IDs should be returned' );
	}

	public function testPostContentIsQuestionBlock() {

		$question_id = $this->factory->question->create(
			[
				'question_type' => 'single-line',
			]
		);

		$blocks = $this->request_question( $question_id );

		$this->assertCount( 1, $blocks );
		$this->assertEquals( 'sensei-lms/quiz-question', $blocks[0]['blockName'] );

	}

	public function testPostContentQuestionBlockAttributes() {
		$question_id = $this->factory->question->create(
			[
				'question'               => 'Test Question',
				'question_type'          => 'multiple-choice',
				'question_description'   => 'Text description',
				'question_grade'         => 2,
				'question_right_answers' => [ 'Right answer' ],
				'question_wrong_answers' => [ 'Wrong,comma', 'Wrong 1' ],
				'random_order'           => 'no',
				'answer_order'           => 'ac70b9a3f24b5b657826b567057169a2,b13d55d1ff11d676253fa5e4b0517bd7,89dc5589bfebac1468e8823afd5a4861',
				'answer_feedback'        => 'Some feedback',
			]
		);

		$blocks = $this->request_question( $question_id );

		$this->assertEquals(
			[
				'title'      => 'Test Question',
				'type'       => 'multiple-choice',
				'id'         => $question_id,
				'shared'     => false,
				'categories' => [],
				'options'    => [
					'grade'          => 2,
					'answerFeedback' => 'Some feedback',
					'randomOrder'    => false,
				],
				'answer'     => [
					'answers' => [
						[
							'label'   => 'Wrong 1',
							'correct' => false,
						],
						[
							'label'   => 'Wrong,comma',
							'correct' => false,
						],
						[
							'label'   => 'Right answer',
							'correct' => true,
						],
					],
				],
			],
			$blocks[0]['attrs']
		);

	}

	public function testPostContentQuestionBlockInnerBlocks() {
		$question_id = $this->factory->question->create(
			[
				'question'             => 'Test Question',
				'question_type'        => 'single-line',
				'question_description' => 'Text description',
			]
		);

		$blocks = $this->request_question( $question_id );

		$this->assertEquals(
			[
				[
					'blockName'    => 'core/paragraph',
					'innerHTML'    => 'Text description',
					'innerContent' => [ 'Text description' ],
					'innerBlocks'  => [],
					'attrs'        => [],
				],
			],
			$blocks[0]['innerBlocks']
		);

	}

	/**
	 * Request question for editing, and return blocks parsed from content.
	 *
	 * @param int $question_id
	 *
	 * @return array[]
	 */
	private function request_question( int $question_id ): array {

		$this->login_as_admin();

		$request = new WP_REST_Request( 'GET', '/wp/v2/questions/' . $question_id );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		return parse_blocks( $response->get_data()['content']['raw'] );
	}

}