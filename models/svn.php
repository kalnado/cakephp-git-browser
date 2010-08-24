<?php
/**
 * Chaw : source code and project management
 *
 * @copyright  Copyright 2009, Garrett J. Woodworth (gwoohoo@gmail.com)
 * @license    GNU AFFERO GENERAL PUBLIC LICENSE v3 (http://opensource.org/licenses/agpl-v3.html)
 *
 */

App::import('Model', 'repo.Repo', false);
/**
 * undocumented class
 *
 * @package default
 *
 */
class Svn extends Repo {

	/**
	 * available commands for magic methods
	 *
	 * @var array
	 */
	var $_commands = array(
			//standard commands
		'add', 'annotate', 'blame', 'cat', 'checkout', 'cleanup', 'commit', 'copy', 'delete', 'diff', 'export', 'help',
		'import', 'info', 'list', 'log', 'merge', 'mkdir', 'move', 'praise', 'propdel', 'propedit', 'propget', 'proplist',
		'propset', 'remove', 'rename', 'resolved', 'revert', 'status', 'switch', 'update',
		//shortcuts
		'ann', 'co', 'ci', 'cp', 'del', 'rm', 'ls', 'mv', 'ren', 'pdel', 'pd', 'pedit', 'pe', 'pget', 'pg', 'plist', 'pl',
		'pset', 'ps', 'stat', 'st', 'sw', 'up'
	);

	/**
	 * Run svnadmin, Besides providing the ability to create Subversion repositories,
	 * this program allows you to perform several maintenance operations on those repositories.
	 *
	 * @param string $command
	 * @param mixed $params
	 * @example $this->Svn->admin('create', '/path/to/new/repo');
	 * @return void
	 */
	function admin($command, $options = array()) {
		extract($this->config);
		return $this->execute("{$type}admin {$command}", $options);
	}

	/**
	 * Run svnlook, is a command-line utility for examining different aspects of a Subversion repository.
	 * It does not make any changes to the repository—it's just used for “peeking”
	 *
	 * @param string $command
	 * @param mixed $params
	 * @example $this->Svn->look('author', 'file:///path/to/repo');
	 * @return void
	 */
	function look($command, $options = array()) {
		extract($this->config);

		$path = null;
		if (!empty($options['path'])) {
			$path = ' ' . $options['path'];
			unset($options['path']);
		}
		return $this->execute("{$type}look {$command} {$this->path}{$path}", $options);
	}

	/**
	 * Create a new repo; initialize the branches, tags, trunk; checkout a working copy to TMP
	 *
	 * @param string $project name of the project
	 * @param array $options repo, working
	 * @example $this->Svn->create(array('repo' => TMP . 'svn/repo', 'working' => APP . 'working'));
	 * @return void
	 *
	 */
	function create($options = array()) {
		parent::_create();
		extract($this->config);

		$file = 'file://' . $path;
		if (!is_dir($path)) {
			$this->admin('create', $path);
			$this->import(array(
					CONFIGS . 'templates' . DS . 'svn' .DS . 'project', $file,
					'--message "Initial Project Import"'
			));
		}

		if (!is_dir($working . DS . 'branches')) {
			$this->checkout(array($file, $working));
		}

		$File = new File($path . DS . 'conf' . DS . 'svnserve.conf');
		$File->write("[general]\nauthz-db = ../permissions.ini\n");

		if (is_dir($path) && is_dir($working . DS . 'branches')) {
			return true;
		}
		return false;
	}

	/**
	 * undocumented function
	 *
	 * @param string $path
	 * @return void
	 */
	function update($path = null) {
		if ($path === null) {
			$path = $this->working;
		}
		return $this->run('update', array($path));
	}

	/**
	 * undocumented function
	 *
	 * @param string $type
	 * @param string $options
	 * @return void
	 */
	function find($type = 'all', $options = array()) {
		if (!empty($options['conditions']['path'])) {
			$options['path'] = $options['conditions']['path'];
			unset($options['conditions']['path']);
		}
		$options = array_merge(array(
			'path' => '.', 'limit' => 100, 'page' => 1
		), $options);

		if (empty($options['path'])) {
			return false;
		}

		$data = $this->look('history', array('path' => $options['path']));
		if (preg_match_all('/[\\s+](\\d+)[\\s+]/', $data, $matches)) {
			$data = array_filter($matches[1]);
		}
		if ($type == 'count') {
			return count($data);
		}

		if ($type == 'all') {
			return parent::_findAll($data, compact('limit', 'page'));
		}
	}

	/**
	 * Read the author, data, messages, changes, and diff for a revision
	 *
	 * @param string $revision id of the revision
	 * @example $this->Svn->read(1);
	 * @return array
	 */
	function read($revision = null, $diff = false) {
		$author = trim($this->look('author', array('-r', $revision)));

		$commit_date = $this->look('date', array('-r', $revision));
		$bits = explode(" ", $commit_date);
		$commit_date = $bits[0] . ' ' . @$bits[1];

		$message = trim($this->look('log', array('-r', $revision)));

		$changed = $this->look('changed', array('-r', $revision));

		if ($diff) {
			$diff = $this->look('diff', array('-r', $revision));
		}

		$temp = array();
		$temp = explode("\n", $changed);

		foreach ($temp as $tmp) {
			if (empty($tmp)) {
				continue;
			}
			$bits = explode(" ", $tmp);

			$action = @$bits[0];
			$file = $bits[sizeof($bits)-1];

			switch ($action) {
				case 'A':
					$action = 'Added';
					break;
				case 'D':
					$action = 'Deleted';
					break;
				case 'U':
					$action = 'Updated';
					break;
				case '_U':
					$action = 'Properties Updated';
					break;
				case 'UU':
					$action = 'Contents and Properties Updated';
					break;
			}

			$changes[] = $action . ' ' . $file;
		}

		$data = compact('revision', 'author', 'commit_date', 'message', 'changes', 'diff');
		return $data;
	}

	/**
	 * Get the info about a directory or file
	 *
	 * @param string $path path inside of working with preceeding /
	 * @example $this->Svn->info('/branches');
	 * @return void
	 *
	 */
	function info($path = null) {
		return $this->run('info', array($this->working . $path));
	}

	/**
	 * undocumented function
	 *
	 * @param string $path
	 * @return void
	 */
	function pathInfo($path) {
		//return array('revision' => null, 'date' => null, 'message' => null, 'author' => null);
		$info = $this->run('info', array($path));

		$youngest = null;
		if (preg_match('/Last Changed Rev: (.*)/', $info, $matches)) {
			$youngest = trim($matches[1]);
		}

		$data = $this->run('log', array($path, '-r', $youngest));

		$lines = explode("\n", $data);
		$info = (!empty($lines[3])) ? explode("|", $lines[1]) : array();

		$result['revision'] = (!empty($info[0])) ? trim($info[0], 'r') : null;
		$result['author'] = (!empty($info[1])) ? trim($info[1]) : null;

		$result['date'] = null;
		if ((!empty($info[2]))) {
			$bits = explode(" ", trim($info[2]));
			$result['date'] = $bits[0] . ' ' . @$bits[1];
		}
		$result['message'] = (!empty($lines[3])) ? trim($lines[3]) : null;
		return $result;
	}

	/**
	 * Get the config credentials
	 *
	 * @return string
	 */
	function _credentials() {
		extract($this->config);
		if (!empty($username) && !empty($password)) {
			return "--username {$username} --password {$password}";
		}
		return null;
	}
}
?>