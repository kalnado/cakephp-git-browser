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
class Git extends Repo {

	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	var $gitDir = null;

	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	var $branch = null;

	/**
	 * available commands for magic methods
	 *
	 * @var array
	 */
	var $_commands = array(
		'clone', 'config', 'diff', 'status', 'log', 'show', 'blame', 'whatchanged',
		'add', 'rm', 'commit', 'pull', 'push', 'branch', 'checkout', 'merge', 'remote'
	);

	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	var $__data = array();

	/**
	 * undocumented function
	 *
	 * @param string $options
	 * @return void
	 */
	function create($options = array()) {
		parent::_create();
		extract($this->config);

		if (!is_dir($path)) {
			$Project = new Folder($path, true, 0775);
		}

		if (is_dir($path) && !file_exists($path . DS . 'config')) {
			$this->cd($path);
			$this->run("--bare init");
		}

		$this->branch('master', true);

		if (!empty($options['remote'])) {
			$remote = $options['remote'];
			unset($options['remote']);
		} else {
			$remote = "git@git.chaw";
		}

		$project = basename($path);

		if (is_dir($this->working) && !file_exists($this->working . DS . '.gitignore')) {
			$this->cd();
			$this->before(array("touch .gitignore"));
			$this->commit("Initial Project Commit");
			$this->push();
		}
		//CakeLog::write(LOG_DEBUG, $this->debug);

		if (is_dir($path) && is_dir($this->working)) {
			return true;
		}

		return true;
	}

	/**
	 * undocumented function
	 *
	 * @param string $user
	 * @param string $options
	 * @return void
	 */
	function fork($user = null, $options = array()) {
		if (!$user) {
			return false;
		}
		extract($this->config);
		$working = dirname($working);
		if ($this->branch !== null) {
			$this->branch = null;
			$working = dirname($working);
		}
		$project = basename($path);
		$fork = dirname($path) . DS . 'forks' . DS . $user . DS . $project;

		$this->config(array(
			'working' => $fork
		));

		if (is_dir($this->working)) {
			$this->config(array(
				'path' => $this->working,
				'working' => $working . DS . 'forks' . DS . $user . DS . str_replace('.git', '', $project)
			));
			$this->pull();
			return true;
		}

		$userDir = dirname($this->working);;
		if (!is_dir($userDir)) {
			$Fork = new Folder($userDir, true, $chmod);
		}

		$this->clone(array('--mirror', $this->path, $this->working));

		if (is_dir($this->working)) {
			if (!empty($options['remote'])) {
				$remote = $options['remote'];
				unset($options['remote']);
			} else {
				$remote = "git@git.chaw";
			}
			$this->config(array(
				'path' => $this->working,
				'working' => $working . DS . 'forks' . DS . $user . DS . str_replace('.git', '', $project)
			));
			//$this->remote(array('add', 'origin', "{$remote}:forks/{$user}/{$project}"));
			$this->pull();
			//$this->merge($project);
		}

		if (is_dir($this->path) && is_dir($this->working)) {
			return true;
		}

		return false;
	}

	/**
	 * undocumented function
	 *
	 * @param string $name
	 * @param string $switch
	 * @return void
	 */
	function branch($name, $switch = false) {
		if (!$name) {
			return false;
		}
		extract($this->config);

		$path = $this->working;
		$branch = basename($path);

		if ($name == $branch) {
			return $this->branch = $name;
		}

		if ($this->branch == $branch) {
			$path = dirname($this->working);
		}

		$path = $path . DS . str_replace("/", "_", $name);
		if (!is_dir($path)) {
			$base = dirname($path);
			if (!is_dir($base)) {
				$clone = new Folder($base, true, $chmod);
			}
			$this->run('clone', array('-b', $name, $this->path, $path));
			@chmod($path, $chmod);
		}
		if ($switch === true) {
			$this->config(array('working' => $path));
			return $this->branch = $name;
		}
	}

	/**
	 * undocumented function
	 *
	 * @param string $options
	 * @return void
	 */
	function commit($options = array()) {
		$path = '.';
		if (is_string($options)) {
			$options = array('-m', escapeshellarg($options));
		} else {
			if (!empty($options['path'])) {
				$path = $options['path'];
				unset($options['path']);
			}
		}

		if (!$this->branch) {
			$this->pull();
		}

		$this->cd();
		$this->run('add', array($path));
		$this->cd();
		return $this->run('commit', $options);
	}

	/**
	 * undocumented function
	 *
	 * @param string $remote
	 * @param string $branch
	 * @return void
	 */
	function push($remote = 'origin', $branch = 'master') {
		$this->cd();
		return $this->run('push', array($remote, $branch), 'capture');
	}

	/**
	 * undocumented function
	 *
	 * @param string $remote
	 * @param string $branch
	 * @param string $params
	 * @return void
	 */
	function update($remote = null, $branch = null, $params = array()) {
		$this->cd();
 		return $this->run('pull -q', array_merge($params, array($remote, $branch)), 'hide');
	}

	/**
	 * undocumented function
	 *
	 * @param string $remote
	 * @param string $branch
	 * @param string $params
	 * @return void
	 */
	function pull($remote ='origin', $branch = 'master', $params = array()) {
		if (!is_dir($this->path)) {
			return false;
		}
		$this->branch($branch, true);

		if (is_dir($this->working)) {
			$this->update($remote, $branch);
			return true;
		}

		return false;
	}

	/**
	 * undocumented function
	 *
	 * @param string $project
	 * @param string $fork
	 * @return void
	 */
	function merge($project, $fork = false) {
		$this->branch('master', true);
		$this->update('origin', 'master');

		$remote = 'parent';
		if (strpos($project, '.git') === false) {
			$project = "{$project}.git";
		}
		if ($fork) {
			$remote = $fork;
			$project = "forks/{$fork}/{$project}";
		}

		$this->cd();
		$this->remote(array('add', $remote, Configure::read('Content.git') . 'repo' . DS . $project));

		$response = $this->update($remote, 'master', array('--squash'));

		if (!empty($response[3])) {
			if (strpos($response[3], 'failed') !== false) {
				return false;
			}
		}

		$this->commit("Merge from {$project}");
		$this->push('origin', 'master');
		return true;
	}

	/**
	 * find all revisions and return contents of read.
	 * type: all, count, array()
	 *
	 * @param string $type
	 * @param array $options
	 * @return array
	 */
	function find($type = 'all', $options = array()) {
		if ($type == 'branches') {
			$result = $this->run('branch -a', null, 'capture');
			
			$branches = array();
			foreach ($result as $branch) {
				if (strpos($branch, 'remotes/') !== false || strpos($branch, 'origin/') !== false) {
					continue;
				}
				if ($branch[0] == '*' || $branch[0] == ' ') {
					$branches[] = trim(substr($branch, 1));
					continue;
				}
			}
			return $branches;
		}

		if (is_array($type)) {
			$options = $type;
			$type = 'first';
		}

		$options = array_merge(array(
			'conditions' => array(), 'fields' => null,
			'hash' => null, 'path' => '.',
			'order' => 'desc', 'limit' => 100,  'page' => 1
		), $options);

		if (!empty($options['revision'])) {
			$options['hash'] = $options['revision'];
			unset($options['revision']);
		}

		list($options['fields'], $format) = $this->__fields($options['fields']);

		if ($type == 'first') {
			$data = $this->run('log', array($options['hash'], $format, '-1'));
			if (!empty($data)) {
				return array_combine($options['fields'], array_filter(explode(chr(0), $data)));
			}
			return $data;
		}

		if (empty($options['path'])) {
			return false;
		}

		$branch = null;
		if (!empty($options['branch'])) {
			$branch = $options['branch'] . ' ';
		}

		$data = $this->__data;

		if (empty($data)) {
			$data = explode("\n", trim($this->run('log', array_merge(
				$options['conditions'], array(
					"{$branch}--pretty=format:%H", '--',
					str_replace($this->working . '/', '', $options['path'])
				)
			))));
			$this->__data = $data;
		}

		if ($type == 'count') {
			return count($data);
		}

		if ($type == 'all') {
			$this->__data = array();
			return parent::_findAll($data, $options);
		}
	}

	/**
	 * undocumented function
	 *
	 * @return array
	 */
	function __fields($fields = null) {
		$fieldMap = array(
			'hash' => '%H',
			'commit_date' => '%ai',
			'email' => '%ae',
			'author' => '%an',
			'committer' => '%cn',
			'committer_email' => '%ce',
			'subject' => '%s',
			'message' => '%s',
			'revision' => '%H'
		);

		if (empty($fields)) {
			$fields = array_keys($fieldMap);
		}

		$format = '--pretty=format:';

		foreach($fields as $field) {
			$format .= $fieldMap[$field] . '%x00';
		}
		return array($fields, $format);
	}

	/**
	 * undocumented function
	 *
	 * @param string $newrev
	 * @param string $options
	 * @return void
	 */
	function read($newrev = null, $options = false) {
		if (!is_array($options)) {
			$options = array('diff' => $options);
		}
		$options = array_merge(array(
			'diff' => false, 'fields' => null
		), $options);

		list($options['fields'], $format) = $this->__fields($options['fields']);

		if (!empty($options['diff'])) {
			$info = $this->run('show', array($newrev, $format, "-1"), 'capture');
		} else {
			$info = $this->run('log', array($newrev, $format, "-1"), 'capture');
		}
		if (empty($info)) {
			return null;
		}

		$data = array_combine($options['fields'], array_filter(explode(chr(0), $info[0])));
		unset($info[0]);

		$changes = array();

		if (!empty($options['diff'])) {
			$data['diff'] = join("\n", $info);
		}

		return $data;
	}

	/**
	 * undocumented function
	 *
	 * @param string $branch
	 * @param string $params
	 * @return void
	 */
	function info($branch, $params = null) {
		if ($params === null) {
			$params = array('--header', '--max-count=1', $branch);
		} else if (is_array($params)) {
			array_push($params, $branch);
		} else {
			$params = array("--pretty=format:'{$params}'", $branch);
		}
		$this->cd();
		$out = $this->run('rev-list', $params, 'capture');

		return $out;
	}

	/**
	 * undocumented function
	 *
	 * @param string $branch
	 * @param string $params
	 * @return void
	 */
	function tree($branch, $params = array()) {
		if (empty($params)) {
			$params = array($branch, "| sed -e 's/\t/ /g'");
		} else {
			array_push($params, $branch);
		}
		$out = $this->run('ls-tree', $params, 'capture');

		if (empty($out[0])) {
			return false;
		}

		if (strpos(trim($out[0]), ' ') === false) {
			return $out;
		}

		$result = array();

        foreach ($out as $line) {
            $entry = array();
            $arr = explode(" ", $line);
            $entry['perm'] = $arr[0];
            $entry['type'] = $arr[1];
            $entry['hash'] = $arr[2];
            $entry['file'] = $arr[3];
            $result[] = $entry;
        }
        return $result;
	}

	/**
	 * undocumented function
	 *
	 * @param string $path
	 * @return void
	 */
	function pathInfo($path = null) {
		$this->cd();
		if ($path) {
			$path = str_replace($this->working . DS, '', $path);
		}
		$info = $this->run('log', array("--pretty=format:%H%x00%an%x00%ai%x00%s", '-1', '--', escapeshellarg($path)));
		if (empty($info)) {
			return null;
		}
		list($revision, $author, $date, $message) = explode(chr(0), $info);
		$message = str_replace(dirname($this->path), "", $message);
		return compact('revision', 'author', 'date', 'message');
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 */
	function delete($delete = null) {
		$branch = $delete ? $delete : $this->branch;
		$working = $delete ? $this->working . DS . $delete : $this->working;

		if ($branch !== 'master') {
			$this->branch('master', true);
			$this->run('branch -D', array($branch), 'hide');
			$this->cd();
			$this->run('remote prune origin', array(), 'hide');
		}
		$this->execute("rm -rf {$working}");

		if (!is_dir($working)) {
			return true;
		}
		return false;
	}

	/**
	 * Run a command specific to this type of repo
	 *
	 * @see execute for params
	 * @return mixed
	 */
	function run($command, $args = array(), $return = false) {
		extract($this->config);

		$gitDir = null;
		if (empty($this->_before) && empty($this->gitDir)) {
			$gitDir = "--git-dir={$this->path} ";
		}
		return parent::run("{$gitDir}{$command}", $args, $return);
	}
}
?>