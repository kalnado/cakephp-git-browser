<?php
/**
 * Chaw : source code and project management
 *
 * @copyright  Copyright 2009, Garrett J. Woodworth (gwoohoo@gmail.com)
 * @license    GNU AFFERO GENERAL PUBLIC LICENSE v3 (http://opensource.org/licenses/agpl-v3.html)
 *
 */
/**
 * Base class for various repo types
 *
 */
class Repo extends Overloadable {

	/**
	 * configuration
	 *
	 * @var array
	 */
	var $config = array(
		'class' => 'Git', 'type' => 'git', 'path' => null, 'working' => null,
		'username' => '', 'password' => '', 'chmod' => 0755, 'chawuser' => 'chawbacca'
	);

	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	var $_commands = array();

	/**
	 * Type of Repo
	 *
	 * @var string
	 */
	var $type = 'git';

	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	var $path = null;

	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	var $working = null;

	/**
	 *  branch name used mostly by Git
	 *
	 * @var string
	 */
	var $branch = null;

	/**
	 * undocumented class variable
	 *
	 * @var string
	 */
	var $debug = array();

	/**
	 * undocumented class variable
	 *
	 * @var string
	 */
	var $response = array();

	/**
	 * undocumented class variable
	 *
	 * @var string
	 */
	var $useTable = false;

	/**
	 * undocumented class variable
	 *
	 * @var string
	 */
	var $_before = array();

	/**
	 * so we can paginate
	 *
	 * @var string
	 */
	var $recursive = 0;

	/**
	 * so we can paginate
	 *
	 * @var string
	 */
	var $alias = null;

	/**
	 * should the command be logged
	 *
	 * @var boolean
	 */
	var $logDebug = true;

	/**
	 * should the response be logged
	 *
	 * @var boolean
	 */
	var $logResponse = false;

	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	var $chawuser = 'chawbacca';

	/**
	 * undocumented function
	 *
	 * @param string $config
	 */
	function __construct($config = array()) {
		$this->config($config);
		$this->alias = ucwords($this->type);
	}

	/**
	 * undocumented function
	 *
	 * @param string $config
	 * @return void
	 */
	function config($config = array()) {
		if (!empty($config['alias']) && empty($config['type'])) {
			$config['type'] = $config['alias'];
		}
		$config = array_merge($this->config, (array)$config);
		$this->type = $config['type'] = strtolower($config['type']);
		$this->path = $config['path'] = rtrim($config['path'], '\/');
		$this->working = $config['working'] = rtrim($config['working'], '\/');
		$this->chawuser = $config['chawuser'];
		return $this->config = $config;
	}

	/**
	 * Magic methods
	 *
	 * @return void
	 */
	function call__($method, $params = array()) {
		if (method_exists($this, "_{$method}")) {
			$finder = "_{$method}";
			$command = array_shift($params);
			$args = array_shift($params);
			return $this->$finder($command, $args, $params);
		} else if (in_array($method, $this->_commands)){
			$args = array_shift($params);
			$return = array_pop($params);
			return $this->run($method, $args, $return);
		} else {
			trigger_error('method ' . $method . ' does not exist');
		}
		return false;
	}

	/**
	 * Set multiple commands to be run before will be joined with &&
	 *
	 * @param mixed command single command string or array of commands
	 * @return void
	 */
	function cd($dir = null) {
		if (is_null($dir)) {
			$dir = $this->working;
		}
		if ($dir{0} != '/') {
			$dir = $this->working . DS . $dir;
		}
		$this->_before[0] = "cd {$dir}";
	}

	/**
	 * Set multiple commands to be run before will be joined with &&
	 *
	 * @param mixed command single command string or array of commands
	 * @return void
	 */
	function before($command = array()) {
		if (is_string($command)) {
			$command = array($command);
		}
		$this->_before = array_merge($this->_before, $command);
	}

	/**
	 * Run a command specific to this type of repo
	 *
	 * @see execute for params
	 * @return misxed
	 */
	function run($command, $args = array(), $return = false) {
		extract($this->config);
		$response = $this->execute("{$type} {$command}", $args, $return);
		if ($this->logResponse === true) {
			$this->response[] = $response;
		}
		return $response;
	}

	/**
	 * Executes given command with results based on return type
	 *
	 *
	 * @param string $command - the command to run
	 * @param mixed $args as array - the arguments for the command, as string - the return type
	 * @param string $return
	 * false - will use shell_exec() and return a string
	 * true - will return the command
	 * capture - will use exec() and return an array
	 * pass - will use passthru() and return binary type
	 *
	 * @return mixed
	 */
	function execute($command, $args = array(), $return = false) {
		$before = null;
		if ($return !== true) {
			$before = (!empty($this->_before)) ? trim(join(' && ', $this->_before)) . ' && ' : null;
			$this->_before = array();
		}
		if (is_string($args)) {
			$args = array($args);
		}
		$args = array_map('escapeshellcmd', (array)$args);

		$c = trim("{$before}{$command} " . join(' ', (array)$args) . " " . $this->_credentials());

		if ($return === true) {
			return $c;
		}

		if ($this->logDebug == true) {
			$this->debug[] = $c;
		}

		umask(0);
		putenv("PHP_CHAWUSER={$this->chawuser}");
		switch ($return) {
			case 'capture':
				exec($c, $response);
			break;
			case 'pass':
			case 'passthru':
				passthru($c, $response);
			break;
			case 'hide':
				$response = shell_exec($c . ' > /dev/null 2>&1');
			break;
			default:
				$response = shell_exec($c);
			break;
		}
		return $response;
	}

	/**
	 * undocumented function
	 *
	 * @param string $data
	 * @param string $query
	 * @return void
	 */
	function _findAll($data, $query = array()) {
		$query = array_merge(array(
			'fields' => null,
			'offset' => 0, 'limit' => 100, 'order' => 'desc', 'page' => 1
		), $query);

		if ($query['page'] > 1) {
			$query['offset'] = ($query['page'] - 1) * $query['limit'];
		}

		extract($query);

		$results = array();

		if (strtolower($order) == 'asc') {
			$data = array_reverse($data);
		}

		foreach ($data as $key => $value) {
			if ($key >= $offset) {
				if ($result = $this->read($value)) {
					$results[]['Repo'] = $result;
					$key++;
				}
			}
			if ($limit > 0 && $key >= $offset + $limit) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Create the parent folders for a repository
	 *
	 * @return boolean
	 */
	function _create($options = array(), $return = false) {
		extract(array_merge($this->config, $options));

		$path = dirname($path);
		$working = dirname($working);

		if (!is_dir($path)) {
			$Parent = new Folder($path, true, $chmod);
		}
		if (!is_dir($working)) {
			$Working = new Folder($working, true, $chmod);
		}

		if (is_dir($path) && is_dir($working)) {
			return true;
		}

		return false;
	}

	/**
	 * Deletes branch are resets
	 *
	 * @return void
	 *
	 */
	function _rebase() {
		$Cleanup = new Folder($this->working);
		if ($Cleanup->pwd() == $this->working) {
			$Cleanup->delete();
		}
		return $this->pull();
	}

	/**
	 * Creates a hook
	 *
	 * @param string $name
	 * GIT
	 * applypatch-msg, commit-message, post-commit, post-receive, post-update,
	 * pre-applypatch, pre-commit, pre-rebase, update)
	 *
	 * SVN
	 * post-commit, post-lock, post-revprop-change, post-unlock, pre-commit, pre-lock,
	 * pre-revprop-change, pre-unlock, start-commit
	 *
	 * @param string $data location of the repository
	 * @return boolean
	 */
	function _hook($name, $data = null, $options = array()) {
		extract($this->config);
		$Hook = new File($path . DS . 'hooks' . DS . $name, true, $chmod);
		chmod($Hook->pwd(), $chmod);

		if (!is_string($data) || $data === null) {
			extract((array)$data);
			if (file_exists(CONFIGS . 'templates' . DS . $type . DS . 'hooks' . DS . $name)) {
				ob_start();
				include(CONFIGS . 'templates' . DS . $type . DS . 'hooks' . DS . $name);
				$data = ob_get_clean();
			}
		}

		if (empty($data)) {
			return false;
		}

		if ($Hook->append($data)) {
			return true;
		}

		return false;
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 */
	function _credentials() {
		return null;
	}
}

?>