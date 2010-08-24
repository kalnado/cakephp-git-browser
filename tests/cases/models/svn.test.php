<?php
/**
 * Chaw : source code and project management
 *
 * @copyright  Copyright 2009, Garrett J. Woodworth (gwoohoo@gmail.com)
 * @license    GNU AFFERO GENERAL PUBLIC LICENSE v3 (http://opensource.org/licenses/agpl-v3.html)
 *
 */

App::import('Model', 'Repo.Svn', false);

class SvnTest extends CakeTestCase {

	function setUp() {
		$this->__repos[1] = array(
			'class' => 'Repo.Svn',
			'type' => 'svn',
			'path' => TMP . 'tests/svn/repo/test',
			'working' => TMP . 'tests/svn/working/test',
			'chmod' => 0777
		);
	}

	function end() {
		parent::end();
		$Cleanup = new Folder(TMP . 'tests/svn');
		if ($Cleanup->pwd() == TMP . 'tests/svn') {
			$Cleanup->delete();
		}
	}
/*
	function getTests() {
		return array_merge(
			array('start', 'startCase'),
			array(
				'testCreate', 'testHook', 'testRead', 'testCommit', 'testFind',
			),
			array('end', 'endCase')
		);
	}
*/

	function testCreate() {
		$Svn = new Svn($this->__repos[1]);
		$this->assertTrue($Svn->create());
		$this->assertTrue(file_exists($Svn->config['path']));
		$this->assertTrue(file_exists($Svn->config['working']));

		$this->assertTrue(file_exists($Svn->config['path'] . DS .'conf' . DS . 'svnserve.conf'));
		$result = file_get_contents($Svn->config['path'] . DS .'conf' . DS . 'svnserve.conf');
		$expected = "[general]\nauthz-db = ../permissions.ini\n";
		$this->assertEqual($result, $expected);

		//pr($Svn->debug);
		//pr($Svn->response);
	}

	function testHook() {
		$Svn = new Svn($this->__repos[1]);
		$Svn->hook('post-commit');
		$this->assertTrue(file_exists($Svn->path . DS . 'hooks' . DS . 'post-commit'));
	}

	function testRead() {
		$Svn = new Svn($this->__repos[1]);
		$result = $Svn->read(1);
		$this->assertEqual($result['revision'], 1);
		$this->assertEqual($result['message'], 'Initial Project Import');

		//var_dump($result);
		//var_dump($Svn->debug);
		//var_dump($Svn->response);
	}


	function testCommit() {
		$Svn = new Svn($this->__repos[1]);

		$File = new File($Svn->working . '/branches/demo_1.0.x.x/index.php', true);
		$File->write("this is a new php file with plain text");

		$result = $Svn->run('add', array(dirname($File->pwd())));
		//var_dump($result);

		$result = $Svn->run('commit', array($Svn->working, '--message "Adding index.php"'));
		//var_dump($result);

		$result = $Svn->info('/branches/demo_1.0.x.x/index.php');
		//var_dump($result);
	}

	function testFind() {
		$Svn = new Svn($this->__repos[1]);
		$result = $Svn->find();

		$this->assertEqual($result[0]['Repo']['revision'], 2);
		$this->assertEqual($result[0]['Repo']['message'], 'Adding index.php');

		$this->assertEqual($result[1]['Repo']['revision'], 1);
		$this->assertEqual($result[1]['Repo']['message'], 'Initial Project Import');

		//var_dump($result);
		//var_dump($Svn->debug);
		//var_dump($Svn->response);
	}

	function testHistory() {
		$Svn = new Svn($this->__repos[1]);

		$File = new File($Svn->working . '/branches/demo_1.0.x.x/index.php', true);
		$File->write("this is a new php file with plain text tha is being changed");

		$result = $Svn->run('commit', array($Svn->working, '--message "Updating index.php"'));


		$result = $Svn->find('all', array('path' => '/branches/demo_1.0.x.x/index.php'));

		$this->assertEqual($result[0]['Repo']['revision'], 3);
		$this->assertEqual($result[0]['Repo']['message'], 'Updating index.php');
		$this->assertEqual($result[1]['Repo']['revision'], 2);
		$this->assertEqual($result[1]['Repo']['message'], 'Adding index.php');

		//var_dump($result);
		//var_dump($Svn->debug);
		//var_dump($Svn->response);
	}

	/*
	function testInfo() {
		pr($Svn->info());

		pr($Svn->look('author', $Svn->repo));
	}


	function testCheckout() {
		pr($Svn->run('co', array(
			'https://svn.cakephp.org/repo/branches/1.2.x.x/cake',
			$Svn->workingCopy .'/branches/demo_1.0.x.x/cake', '--force'
		)));
	}

	function testBlame() {
		$Svn = new Svn($this->__repos[1]);
		pr($Svn->run('blame', $Svn->working . '/cake/libs/file.php'));
	}
	*/
}
?>