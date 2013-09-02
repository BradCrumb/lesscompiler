<?php
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('Component', 'Controller');
App::uses('LessComponent', 'LessCompiler.Controller/Component');

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

		//Test generate of CSS with debug off and autorun off
		Configure::write('debug', 0);
		$Less = new LessComponent(new ComponentCollection(), array('autoRun' => false));
		$Less->startup($this->Controller);
		$Less->initialize($this->Controller);

		//Clean all generated CSS files
		$this->Less->cleanGeneratedCss();

		$generatedFiles = $Less->generateCss();

		//Files may not be generated
		$this->assertEmpty($generatedFiles);

		Configure::write('debug', 2);
	}

	public function testSetFoldersFallback() {
		$Less = new LessComponent(new ComponentCollection(), array('sourceFolder' => false));
		$Less->startup($this->Controller);
		$Less->initialize($this->Controller);

		$this->assertFalse($Less->settings['sourceFolder']);
	}

	public function testBeforeRender() {
		//Clean all generated CSS files
		$this->Less->cleanGeneratedCss();

		//Trigger beforeRender
		$this->Less->beforeRender($this->Controller);

		//Check if the files are generated
		$generatedFiles = $this->Less->generateCss();

		foreach ($generatedFiles as $path) {
			$this->assertFileExists($path);
		}
	}

	public function testPreserveComments() {
		$Less = new LessComponent(new ComponentCollection(), array('preserveComments' => true));
		$Less->startup($this->Controller);
		$Less->initialize($this->Controller);

		$this->assertTrue($Less->settings['preserveComments']);

		//Clean all generated CSS files
		$Less->cleanGeneratedCss();

		//Check if the files are generated
		$generatedFiles = $Less->generateCss();

		foreach ($generatedFiles as $path) {
			$this->assertFileExists($path);
		}
	}

	public function testVariables() {
		$Less = new LessComponent(new ComponentCollection(), array('variables' => array('color' => '#ffffff')));
		$Less->startup($this->Controller);
		$Less->initialize($this->Controller);

		$this->assertTrue($Less->settings['variables']['color'] == '#ffffff');

		//Clean all generated CSS files
		$Less->cleanGeneratedCss();

		//Check if the files are generated
		$generatedFiles = $Less->generateCss();

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