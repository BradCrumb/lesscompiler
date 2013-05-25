# LessCompiler

LessCompiler is a CakePHP LESS component to (automatically) compile less-files (http://lesscss.org/) by using lessc.php (http://leafo.net/lessphp/).

## Requirements

The master branch has the following requirements:

* CakePHP 2.2.0 or greater.
* PHP 5.3.0 or greater.

## Installation

* Clone/Copy the files in this directory into `app/Plugin/LessCompiler`
* Ensure the plugin is loaded in `app/Config/bootstrap.php` by calling `CakePlugin::load('LessCompiler');`
* Include the toolbar component in your `AppController.php`:
   * `public $components = array('LessCompiler.Less');`

## Documentation

The component will check for less-files to (re)compile automatically when:
 * Debug level is > 0
 * autoRun is set to true in the component settings
 * Cache-time expires

In a live environment one can force the component to (re)compile all less-files by supplying forceLessToCompile=true in the request string.

The component writes cache-files to your CakePHP's cache-directory in a subdirectory called "LessComponent".
All less-files should be placed in the `app/less` directory (to generate css-files in the default `webroot/css` directory).
Less-files for the plugin and themes should be stored in `app/Plugin/{pluginname}/less` or `app/View/Themed/{themename/less`.

The default duration time for the cache is 4 hours.
After that time the cache expires and after a new request the component will check for updated or added less-files.

### Possible Component Settings
	public $components = array(
		'LessCompiler.less' 	=> array(
			'sourceFolder' 		=> false 		// Where to look for LESS files, (From the APP directory)
       		'targetFolder' 		=> false 		// Where to put the generated css (From the webroot directory)
			'formatter' 		=> 'compressed' // lessphp compatible formatter
			'preserveComments' 	=> null 		// Preserve comments or remove them
			'variables' 		=> array() 		// Pass variables from php to LESS
			'forceCompiling' 	=> false		// Always recompile
			'autoRun' 			=> false		// Check if compilation is necessary, this ignores the CakePHP Debug setting
		)
	);

## License
GNU General Public License, version 3 (GPL-3.0)
http://opensource.org/licenses/GPL-3.0






