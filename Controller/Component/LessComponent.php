<?php
App::uses('LessCompiler', 'LessCompiler.Lib');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('Component', 'Controller');

/**
 * LessCompiler
 *
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2013 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @package LessCompilerse
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 * @todo add php-functions to the lessc configuration
 */
class LessComponent extends Component {

/**
 * Settings for the Less Compiler Component
 *
 * forceCompiling : enabled by supplied ?forceLessToCompile=true in the url
 * autoRun: run the component even if the debug level = 0
 *
 * @var array
 */
	public $settings = array(
		'sourceFolder'		=> 'less',			// Where to look for LESS files, (From the APP directory)
		'targetFolder'		=> false,			// Where to put the generated css (From the webroot directory)
		'formatter'			=> 'compressed',	// lessphp compatible formatter
		'preserveComments'	=> null,			// Preserve comments or remove them
		'variables'			=> array(),			// Pass variables from php to LESS
		'forceCompiling'	=> false,			// Always recompile
		'autoRun'			=> false,			// Check if compilation is necessary, this ignores the CakePHP Debug setting
		'sourceMap'			=> false			// Generate sourcemap
	);

/**
 * Controller instance reference
 *
 * @var object
 */
	public $controller;

/**
 * Components used by LessComponent
 *
 * @var array
 */
	public $components = array('RequestHandler', 'Session');

/**
 * Contains the indexed folders consisting of less-files
 *
 * @var array
 */
	protected $_lessFolders;

/**
 * Contains the folders with processed css files
 *
 * @var array
 */
	protected $_cssFolders;

/**
 * Location of the folder where the cache-files should be stored
 *
 * @var array
 */
	protected $_cacheFolder;

/**
 * CacheKey used for the cache file.
 *
 * @var string
 */
	public $cacheKey = 'LessComponent_cache';

/**
 * Duration of the debug kit history cache
 *
 * @var string
 */
	public $cacheDuration = '+4 hours';

/**
 * Status whether component is enabled or disabled
 *
 * @var boolean
 */
	public $enabled = true;

/**
 * Minimum required PHP version
 *
 * @var string
 */
	protected static $_minVersionPHP = '5.3';

/**
 * Minimum required CakePHP version
 *
 * @var string
 */
	protected static $_minVersionCakePHP = '2.2.0';

/**
 * Minimum required Lessc.php version
 *
 * @var string
 */
	protected static $_minVersionLessc = '0.3.9';

/**
 * Public constructor for the LessComponent
 *
 * @param ComponentCollection $collection
 * @param array               $settings
 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$this->controller = $collection->getController();
		$settings = array_merge($settings, (array)Configure::read('LessCompiler'));

		parent::__construct($collection, array_merge($this->settings, (array)$settings));

		// Don't execute the component if the debuglevel is 0
		// unless compileLess requestparameter is supplied
		// if autoRun is true then ALWAYS run the component
		if (!Configure::read('debug') &&
			!isset($this->controller->request->query['forceLessToCompile']) &&
			false === $this->settings['autoRun']) {
			$this->enabled = false;
			return false;
		}

		if (isset($this->controller->request->query['forceLessToCompile'])) {
			$this->settings['forceCompiling'] = true;
		}

		$this->_checkVersion();

		$this->cacheKey .= $this->Session->read('Config.userAgent');

		$this->_createCacheConfig();

		$this->_setFolders();
	}

/**
 * Checks the versions of PHP, CakePHP and lessphp
 *
 * @throws CakeException If one of the required versions is not available
 *
 * @return void
 */
	protected function _checkVersion() {
		if (PHP_VERSION < self::$_minVersionPHP) {
			throw new CakeException(__('The LessCompiler plugin requires PHP version %s or higher!', self::$_minVersionPHP));
		}

		if (Configure::version() < self::$_minVersionCakePHP) {
			throw new CakeException(__('The LessCompiler plugin requires CakePHP version %s or higher!', self::$_minVersionCakePHP));
		}

		$lessc = new LessCompiler();

		if ($lessc::$VERSION < self::$_minVersionLessc) {
			throw new CakeException(__('The LessCompiler plugin requires lessc version %s or higher!', self::$_minVersionLessc));
		}

		unset($lessc);
	}

/**
 * Indicated whether cache is enabled
 *
 * @return boolean
 */
	protected function _isCacheEnabled() {
		return Configure::read('Cache.disable') !== true;
	}

/**
 * Create the cache config for this component
 *
 * @return void
 */
	protected function _createCacheConfig() {
		if ($this->_isCacheEnabled()) {
			$cache = array(
				'duration' => $this->cacheDuration,
				'engine' => 'File',
				'path' => CACHE
			);
			if (isset($this->settings['cache'])) {
				$cache = array_merge($cache, $this->settings['cache']);
			}
			Cache::config('LessComponent', $cache);
		}
	}

/**
 * Set the value for a specific key in this component's cache
 *
 * @param string $key
 * @param all $value
 */
	protected function _setCacheKey($key, $value) {
		if ($this->_isCacheEnabled()) {

			$config = Cache::config('LessComponent');
			if (empty($config)) {
				return;
			}

			$lesscCache = Cache::read($this->cacheKey, 'LessComponent');
			if (empty($lesscCache)) {
				$lesscCache = array();
			}

			$lesscCache[$key] = $value;

			Cache::write($this->cacheKey, $lesscCache, 'LessComponent');
		}
	}

/**
 * Get the value for a specific key in this component's cache
 *
 * @param  string $key
 *
 * @return false|value
 */
	protected function _getCacheKey($key) {
		$result = Cache::read($this->cacheKey, 'LessComponent');
		if (isset($result[$key])) {
			return $result[$key];
		}

		return false;
	}

/**
 * Remove a specific key in this component's cache
 *
 * @param  string $key
 *
 * @return void
 */
	protected function _removeCacheKey($key) {
		if ($this->_isCacheEnabled()) {
			Cache::delete('less_compiled', 'LessComponent');
		}
	}

/**
 * Set all possible folders
 *
 * @return void
 */
	protected function _setFolders() {
		$this->_cacheFolder = CACHE . __CLASS__;

		$this->_lessFolders['default'] = $this->settings['sourceFolder']?
														APP . $this->settings['sourceFolder']:
														APP . 'less';

		$this->_cssFolders['default'] = $this->settings['targetFolder']?
														WWW_ROOT . $this->settings['targetFolder']:
														WWW_ROOT . 'css';

		$this->_checkFolders();

		$this->_lessFolders['default'] = new Folder($this->_lessFolders['default']);
		$this->_cssFolders['default'] = new Folder($this->_cssFolders['default']);

		$this->_checkThemeFolders();
		$this->_checkPluginFolders();
	}

/**
 * Check if the less and cache directories are present.
 *
 * If not create them
 *
 * @return void
 */
	protected function _checkFolders() {
		if (!is_dir($this->_lessFolders['default'])) {
			mkdir($this->_lessFolders['default']);
		}

		if (!is_dir($this->_cacheFolder)) {
			mkdir($this->_cacheFolder);
		}
	}

/**
 * Check all the Theme folders for less directories
 *
 * @return void
 */
	protected function _checkThemeFolders() {
		$themedDirectory = APP . 'View' . DS . 'Themed';

		$folder = new Folder($themedDirectory);
		list($themes, $files) = $folder->read();

		foreach ($themes as $theme) {
			$lessDir = $themedDirectory . DS . $theme . DS . 'less';
			$cssDir = $themedDirectory . DS . $theme . DS . 'webroot' . DS . 'css';

			if ($theme != '.svn' && is_dir($lessDir) && is_dir($cssDir)) {
				$this->_lessFolders[$theme] = new Folder($lessDir);
				$this->_cssFolders[$theme] = new Folder($cssDir);
			}
		}
	}

/**
 * Check all the Plugin folders for less directories
 *
 * @return void
 */
	protected function _checkPluginFolders() {
		$pluginDirectory = APP . 'Plugin';

		$folder = new Folder($pluginDirectory);
		list($plugins, $files) = $folder->read();

		foreach ($plugins as $plugin) {
			$lessDir = $pluginDirectory . DS . $plugin . DS . 'less';
			$cssDir = $pluginDirectory . DS . $plugin . DS . 'webroot' . DS . 'css';
			if ($plugin != '.svn' && is_dir($lessDir) && is_dir($cssDir)) {
				$this->_lessFolders[$plugin] = new Folder($lessDir);
				$this->_cssFolders[$plugin] = new Folder($cssDir);
			}
		}
	}

/**
 * Before Render
 *
 * Before a page is rendered trigger the compiler
 *
 * @param  Controller $controller The Controller where the component is loaded
 *
 * @return void
 */
	public function beforeRender(Controller $controller) {
		$this->generateCSS();
	}

/**
 * Generate the CSS from all the LESS files we can find
 *
 * @return String[] Generated CSS files
 */
	public function generateCss() {
		$generatedFiles = array();

	/**
 	 * Run the check for the up-to-date compiled less files when
 	 *
 	 * - The Cache does not contain an indication of the fact that the check has run
 	 * - Debug mode is set larger than 0 (suggesting development mode)
 	 * - The requested parameter has been set to force the check
 	 * - The component should run always (autorun) despite of the debuglevel
 	 */
		if ((($this->_isCacheEnabled() && false === $this->_getCacheKey('less_compiled')) ||
			Configure::read('debug') > 0 ||
			true === $this->settings['autoRun'] ||
			true === $this->settings['forceCompiling']
			) && $this->enabled) {
			foreach ($this->_lessFolders as $key => $lessFolder) {
				foreach ($lessFolder->find() as $file) {
					$file = new File($file);
					if ($file->ext() == 'less' && substr($file->name, 0, 2) !== '._' && substr($file->name, 0, 1) !== '_') {
						$lessFile = $lessFolder->path . DS . $file->name;
						$cssFile = $this->_cssFolders[$key]->path . DS . str_replace('.less', '.css', $file->name);

						if ($this->_autoCompileLess($lessFile, $cssFile, $lessFolder->path . DS)) {
							$generatedFiles[] = $cssFile;
						}
					}
				}
			}

			$this->_setCacheKey('less_compiled', true);
		}

		return $generatedFiles;
	}

/**
 * Compile the less files
 *
 * @param  string $inputFile
 * @param  string $outputFile
 * @param  string $lessFolder
 *non
 * @return boolean
 */
	protected function _autoCompileLess($inputFile, $outputFile, $lessFolder) {
		$cacheFile = str_replace(DS, '_', str_replace(APP, null, $outputFile));
		$cacheFile = $this->_cacheFolder . DS . $cacheFile;
		$cacheFile = substr_replace($cacheFile, 'cache', -3);

		$cache = file_exists($cacheFile)?
			unserialize(file_get_contents($cacheFile)):
			$inputFile;

		$lessCompiler = new LessCompiler();
		$lessCompiler->setSourceMap($this->settings['sourceMap']);
		$lessCompiler->setFormatter($this->settings['formatter']);

		if (is_bool($this->settings['preserveComments'])) {
			$lessCompiler->setPreserveComments($this->settings['preserveComments']);
		}

		if ($this->settings['variables']) {
			$lessCompiler->setVariables($this->settings['variables']);
		}

		$lessCompiler->registerFunction('arie', function($var) {
			return $var;
		});

		$newCache = $lessCompiler->cachedCompile($cache, $this->settings['forceCompiling']);

		if (true === $this->settings['forceCompiling'] ||
			!is_array($cache) ||
			$newCache["updated"] > $cache["updated"]) {
			file_put_contents($cacheFile, serialize($newCache));
			file_put_contents($outputFile, $newCache['compiled']);

			return true;
		}

		return false;
	}

/**
 * Clean the generated CSS files
 *
 * @return array
 */
	public function cleanGeneratedCss() {
		$this->_removeCacheKey('less_compiled');

		// Cleaned files that we will return
		$cleanedFiles = array();
		foreach ($this->_lessFolders as $key => $lessFolder) {
			foreach ($lessFolder->find() as $file) {
				$file = new File($file);

				if ($file->ext() == 'less' && substr($file->name, 0, 2) !== '._') {
					$lessFile = $lessFolder->path . DS . $file->name;
					$cssFile = $this->_cssFolders[$key]->path . DS . str_replace('.less', '.css', $file->name);

					if (file_exists($cssFile)) {
						unlink($cssFile);
						$cleanedFiles[] = $cssFile;
					}
				}
			}
		}

		// Remove all cache files at once
		if (is_dir($this->_cacheFolder)) {
			@closedir($this->_cacheFolder);
			$folder = new Folder($this->_cacheFolder);
			$folder->delete();
			unset($folder);
			$cleanedFiles[] = $this->_cacheFolder . DS . '*';
		}
		mkdir($this->_cacheFolder);

		return $cleanedFiles;
	}
}
