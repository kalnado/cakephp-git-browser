<?php
/**
 * Chaw : source code and project management
 *
 * @copyright  Copyright 2009, Garrett J. Woodworth (gwoohoo@gmail.com)
 * @license    GNU AFFERO GENERAL PUBLIC LICENSE v3 (http://opensource.org/licenses/agpl-v3.html)
 *
 */

App::import('Model', array('Repo.Git', 'Repo.Svn'), false);

class TestRepo extends Repo {

	var $cacheSources = false;
}

class RepoTest extends CakeTestCase {

	function setUp() {
		$this->__repos[1] = array(
			'class' => 'TestRepo',
			'type' => 'git',
			'path' => TMP . 'tests/git/repo/test.git',
			'working' => TMP . 'tests/git/working/repo/test'
		);

		$this->__repos['Git'] = array(
			'class' => 'Repo.Git',
			'type' => 'git',
			'path' => TMP . 'tests/git/repo/test.git',
			'working' => TMP . 'tests/git/working/repo/test'
		);
	}

	function testInit() {
		$Repo = new TestRepo($this->__repos[1]);

		$result = $Repo->config();

		$expected = array(
			'class' => 'TestRepo',
			'type' => 'git',
			'path' => TMP . 'tests/git/repo/test.git',
			'working' => TMP . 'tests/git/working/repo/test',
			'username' => null, 'password' => null,
			'chmod' => 0755,
			'alias' => 'TestRepo'
		);

		$this->assertEqual($result, $expected);
	}

	function testExecute() {
		$Repo = new TestRepo($this->__repos[1]);

		$result = $Repo->execute('ls', array(TMP), true);
		$expected = "ls " . TMP;
		$this->assertEqual($result, $expected);

		$result = $Repo->run('ls', array('--git-dir', TMP), true);
		$expected = "git ls --git-dir " . TMP;
		$this->assertEqual($result, $expected);

		$Repo->before("cd {$Repo->path}");
		$result = $Repo->run('ls', array('--git-dir', TMP), true);
		$expected = "cd " . $this->__repos[1]['path'] . " && git ls --git-dir " . TMP;
		$this->assertEqual($result, $expected);
	}

	function testmagicMethods() {
		$Repo = ClassRegistry::init($this->__repos['Git']);
		$result = $Repo->checkout(array('somthing', 'else'), true);
		$expected = "git --git-dir={$Repo->path} checkout somthing else";
		$this->assertEqual($result, $expected);
	}
}

?>