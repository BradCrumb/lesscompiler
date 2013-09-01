<?php
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('Component', 'Controller');
App::uses('LessComponent', 'Less.Controller/Component');

// A fake controller to test against
class TestLessController extends Controller {

	public $paginate = null;
}


/**
 * LessComponent Test Case
 */
class LessComponentTest extends CakeTestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$Collection = new ComponentCollection();
		$this->Less = new LessComponent($Collection);

		$CakeRequest = new CakeRequest();
		$CakeResponse = new CakeResponse();
		$this->Controller = new TestLessController($CakeRequest, $CakeResponse);

		$this->Less->startup($this->Controller);
		$this->Less->initialize($this->Controller);
	}

	public function testCleanGeneratedCss() {
		//Generate the CSS files
		$this->Less->generateCss();

		//Clean the CSS files
		$cleanedFiles = $this->Less->cleanGeneratedCss();

		foreach ($cleanedFiles as $path) {
			$this->assertFileNotExists($path);
		}
	}

	public function testGenerateCss() {
		//Clean all generated CSS files
		$this->Less->cleanGeneratedCss();

		//Generate the css files
		$generatedFiles = $this->Less->generateCss();

		foreach ($generatedFiles as $path) {
			$this->assertFileExists($path);
		}
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->Less);

		parent::tearDown();
	}
}