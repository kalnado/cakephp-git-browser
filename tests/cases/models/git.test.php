<?php
/**
 * Chaw : source code and project management
 *
 * @copyright  Copyright 2009, Garrett J. Woodworth (gwoohoo@gmail.com)
 * @license    GNU AFFERO GENERAL PUBLIC LICENSE v3 (http://opensource.org/licenses/agpl-v3.html)
 *
 */
App::import('Model', 'Repo.Git', false);

class GitTest extends CakeTestCase {

	function startTest() {
		Configure::write('Content.git', TMP . 'tests/git/');
		$this->__repos[1] = array(
			'class' => 'Repo.Git',
			'type' => 'git',
			'path' => TMP . 'tests/git/repo/test.git',
			'working' => TMP . 'tests/git/working/test',
			'chmod' => 0777
		);
	}

	function endTest() {
		$this->__cleanUp();
	}

	function __cleanUp() {
		$Cleanup = new Folder(TMP . 'tests/git');
		if ($Cleanup->pwd() == TMP . 'tests/git') {
			$Cleanup->delete();
		}
	}

	function testCreate() {
		$Git = new Git($this->__repos[1]);
		$Git->logDebug = true;
		$Git->logResponse = true;
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		//pr($Git->debug);
		//pr($Git->response);
		//die();
	}

	function testHook() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->hook('post-receive'));
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git/hooks/post-receive'));
		unlink(TMP . 'tests/git/repo/test.git/hooks/post-receive');
	}

	function igetTests() {
		$this->endTest();
		return array('start', 'testRead', 'end');
	}

	function testRead() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$result = $Git->read();
		$this->assertEqual($result['message'], 'Initial Project Commit');


		//pr($Git->debug);
		//pr($Git->response);

		//die();

		$File = new File(TMP . 'tests/git/working/test/master/.gitignore');
		$this->assertTrue($File->write('this is something new'));

		$Git->commit('Updating git ignore');

		$File = new File(TMP . 'tests/git/working/test/master/.gitignore');
		$this->assertTrue($File->write('this is something new again'));

		$Git->commit('Updating git ignore again');
		$Git->push();

		$data = $Git->read();
		
		$this->assertEqual($data['message'], 'Updating git ignore again');
		// array(
		// 		'hash' => $data['hash'],
		// 		'revision' => $data['revision'],
		// 		'commit_date' => date('Y-m-d H:i:s O', strtotime('now')),
		// 		'author' => 'gwoo',
		// 		'email' => 'gwoo@cakephp.org',
		// 		'committer' => 'gwoo',
		// 		'committer_email' => 'gwoo@cakephp.org',
		// 		'message' => 'Updating git ignore again',
		// 		'subject' => 'Updating git ignore again',
		// 	));
	
		$result = $Git->find('all', array('conditions' => array($result['revision'] . '..' . $data['revision'])));

		$this->assertEqual($result[0]['Repo']['message'], 'Updating git ignore again');
		$this->assertEqual($result[1]['Repo']['message'], 'Updating git ignore');

		$commits = $Git->find('all', array(
			'conditions' => array($data['revision']),
			'limit' => 1
		));

		$this->assertEqual($result[0]['Repo']['message'], 'Updating git ignore again');


		//$this->end();
	}

	function testBranch() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$Git->logResponse = true;
		$result = $Git->branch("new");
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/new/'));

		//pr($Git->debug);
		//pr($Git->response);
		//die();
	}

	function testFork() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());

		$File = new File(TMP . 'tests/git/working/test/master/.gitignore');
		$this->assertTrue($File->write('this is something new'));
		$Git->commit('Updating git ignore');
		$Git->push();

		$File = new File(TMP . 'tests/git/working/test/master/new_file.txt', true, 0777);
		$this->assertTrue($File->write('this is something new'));
		$Git->commit('Adding new file');
		$Git->push();


		$Git->logResponse = true;
		$result = $Git->fork("gwoo");
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/forks/gwoo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/forks/gwoo/test/master/'));

		$result = $Git->find('all');
		$this->assertEqual($result[0]['Repo']['message'], 'Adding new file');
		$this->assertEqual($result[1]['Repo']['message'], 'Updating git ignore');


		$Git->config($this->__repos[1]);
		$Git->branch = null;
		$result = $Git->fork("bob");
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/forks/bob/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/forks/bob/test/master/'));

		$result = $Git->find('all');
		$this->assertEqual($result[0]['Repo']['message'], 'Adding new file');
		$this->assertEqual($result[1]['Repo']['message'], 'Updating git ignore');

		// pr($Git->debug);
		//pr($Git->response);
		//die();
	}

	function testFindCount() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$File = new File(TMP . 'tests/git/working/test/master/.gitignore');
		$this->assertTrue($File->write('this is something new'));

		//$Git->pull();
		$Git->commit('Updating git ignore');
		$Git->push();

		$result = $Git->find('count', array('path' => TMP . 'tests/git/working/test/master/.gitignore'));

		$this->assertEqual($result, 2);

		$Git->update();
		$results = $Git->find('all', array(
			'branch' => 'master', 'order' => 'asc'
		));

		$oldrev = $results[0]['Repo']['revision'];
		$newrev = $results[1]['Repo']['revision'];

		$count = $Git->find('count', array(
			'conditions' => array($oldrev . '..' . $newrev),
			'order' => 'asc'
		));

		$this->assertEqual(1, $count);

	//pr($Git->working);
	//	pr($Git->debug);
	//	pr($Git->response);
	//	die();
	}

	function testFindAll() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$File = new File(TMP . 'tests/git/working/test/master/.gitignore');
		$File->write('this is something new');

		$Git->commit(array("-m", "'Updating git ignore'"));
		$Git->push();

		$result = $Git->find('all', array('path' => TMP . 'tests/git/working/test/master/.gitignore'));

		$this->assertEqual($result[0]['Repo']['message'], 'Updating git ignore');
		$this->assertEqual($result[1]['Repo']['message'], 'Initial Project Commit');


		$result = $Git->find('all', array('path' => TMP . 'tests/git/working/test/master/.gitignore', 'limit' => 1));

		$this->assertEqual($result[0]['Repo']['message'], 'Updating git ignore');
		$this->assertTrue(empty($result[1]['Repo']['message']));

		$result = $Git->find('all', array('path' => TMP . 'tests/git/working/test/master/.gitignore', 'limit' => 1, 'page' => 2));
		//pr($result);
		$this->assertEqual($result[0]['Repo']['message'], 'Initial Project Commit');
		$this->assertTrue(empty($result[1]['Repo']['message']));

		//pr($Git->debug);

	}

	function testFindWithFields() {
		$this->__cleanUp();
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$File = new File(TMP . 'tests/git/working/test/master/.gitignore');
		$File->write('this is something new');

		$Git->commit(array("-m", "'Updating git ignore'"));
		$Git->push();

		$result = $Git->find('first', array('fields' => array('message')));
		$this->assertEqual($result, array(
			'message' => 'Updating git ignore',
		));

		$result = $Git->find('first');
		$newrev = $result['hash'];
		$this->assertEqual($result['message'], 'Updating git ignore');
		// array(
		// 	'hash' => $result['hash'],
		// 	'revision' => $result['revision'],
		// 	'commit_date' => date('Y-m-d H:i:s O', strtotime('now')),
		// 	'email' => 'gwoo@cakephp.org',
		// 	'author' => 'gwoo',
		// 	'committer' => 'gwoo',
		// 	'committer_email' => 'gwoo@cakephp.org',
		// 	'message' => 'Updating git ignore',
		// 	'subject' => 'Updating git ignore'
		// )

		//Test Find By Commit
		$result = $Git->find(array('hash' => $newrev, 'fields' => array('hash')));
		$this->assertEqual($result, array(
			'hash' => $newrev,
		));

		/*
		$Git = new Git(array(
			'class' => 'Repo.Git',
			'type' => 'git',
			'path' => '/Volumes/Home/htdocs/chaw_content/git/repo/renan.git',
			'working' => '/Volumes/Home/htdocs/chaw_content/git/working/renan',
			'chmod' => 0777
		));
		$result = $Git->find(array('commit' => 'fdb86255e698e9d873620ca5e14470eca60a7560'), array('email', 'author'));
		$this->assertEqual($result, array('email' => 'renan.saddam@gmail.com', 'author' => 'renan.saddam'));
		*/
	}

	function testCommitIntoBranch() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$Git->cd();
		$Git->checkout(array('-b', 'new'));
		$Git->push('origin', 'new');

		$Git->branch('new', true);
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/new/.git'));

		$Git->cd();
		$Git->checkout(array('-b', 'new', 'origin/new'));
		$Git->cd();
		$Git->run('pull');

		$File = new File(TMP . 'tests/git/working/test/new/a.txt');
		$this->assertTrue($File->write('this is something new'));

		$Git->commit(array("-m", "'Adding a.txt'"));
		$Git->push('origin', 'new');

		$Git->cd();
		$result = $Git->read();
		$this->assertEqual($result['message'], "Adding a.txt");

		// pr($Git->debug);
		// pr($Git->response);
		// die();
	}

	function testFastForward() {
		$Cleanup = new Folder(TMP . 'tests/git');
		if ($Cleanup->pwd() == TMP . 'tests/git') {
			$Cleanup->delete();
		}
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$result = $Git->fork("gwoo");
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/forks/gwoo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/forks/gwoo/test/master/'));

		//pr($Git->debug);
		//pr($Git->response);

		$File = new File(TMP . 'tests/git/working/test/master/new.text', true);
		$File->write('this is something new');

		$Git = new Git($this->__repos[1]);
		$Git->logResponse = true;
		$Git->commit('Pushing to parent');
		$Git->push('origin', 'master');

		$Git->fork("gwoo");
		$Git->merge("test");

		$this->assertTrue(file_exists(TMP . 'tests/git/working/forks/gwoo/test/master/new.text'));

		$Git->cd();
		$result = $Git->run('log');
		$this->assertTrue(strpos($result, "Merge from test.git") !== false);

		//pr($Git->debug);
		//pr($Git->response);
		//die();
	}

	function testMerge() {
		Configure::write('Content.git', TMP . 'tests/git/');
		$Git =& new Git(array(
			'class' => 'Repo.Git',
			'type' => 'git',
			'path' => TMP . 'tests/git/repo/test.git',
			'working' => TMP . 'tests/git/working/test',
			'chmod' => 0777
		));

		$this->assertTrue($Git->create());

		$Git->logResponse = true;

		$Git->fork('gwoo');
		$Folder = new Folder(TMP . 'tests/git/working/forks/gwoo/test/master/folder', true);
		$File = new File(TMP . 'tests/git/working/forks/gwoo/test/master/folder/file.txt', true);

		$Git->commit('this is a new message');
		$Git->push();

		$Git->config(array(
			'path' => TMP . 'tests/git/repo/test.git',
			'working' => TMP . 'tests/git/working/test',
		));

		$Git->merge('test', 'gwoo');
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/folder'));

		$data = $Git->read();
		$this->assertEqual($data['message'], 'Merge from forks/gwoo/test.git');
	}

	function testMergeFromFork() {
		$Cleanup = new Folder(TMP . 'tests/git');
		if ($Cleanup->pwd() == TMP . 'tests/git') {
			$Cleanup->delete();
		}
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$result = $Git->fork("gwoo");
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/forks/gwoo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/forks/gwoo/test/master/.git'));

		$File = new File(TMP . 'tests/git/working/forks/gwoo/test/master/new.text', true);
		$File->write('this is something new');
		$Git->commit(array("-m", "'Pushing to fork'"));
		$Git->push('origin', 'master');

		$Git = new Git($this->__repos[1]);

		$File = new File(TMP . 'tests/git/working/test/master/other.text', true);
		$File->write('this is something elese is new');
		$Git->commit(array("-m", "'Pushing to parent'"));
		$Git->push('origin', 'master');
		/*
		$Git->update('origin', 'master');
		$Git->before(array("cd {$Git->working}"));
		$Git->remote(array('add', 'gwoo', TMP . 'tests/git/repo/forks/gwoo/test.git'));
		$Git->update('gwoo', 'master');
		$Git->push('origin', 'master');
		*/
		$Git->logResponse = true;

		$Git->merge('test', 'gwoo');
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/new.text'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/other.text'));

		$data = $Git->read();
		$this->assertTrue($data['message'], 'Merge from forks/gwoo/test.git');

		//pr($Git->debug);
		//pr($Git->response);
	}

	function testMultipleCommitsInSinglePush() {
		$Cleanup = new Folder(TMP . 'tests/git');
		if ($Cleanup->pwd() == TMP . 'tests/git') {
			$Cleanup->delete();
		}
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$File = new File(TMP . 'tests/git/working/test/new.text', true);
		$File->write('this is something new');
		$Git->commit(array("-m", "'Pushing to fork'"));

		$File = new File(TMP . 'tests/git/working/test/new.text', true);
		$File->write('this is something new again');
		$Git->commit(array("-m", "'Pushing to fork again'"));
		$Git->push('origin', 'master');

		$data = $Git->read();
		$this->assertTrue($data['message'], 'Pushing to fork again');
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/new.text'));

		$File = new File(TMP . 'tests/git/working/test/new.text', true);
		$File->write('this is something new again');
		$Git->commit(array("-m", "'Pushing to fork again again'"));
		$Git->push('origin', 'master');

		$data = $Git->read();
		$this->assertTrue($data['message'], 'Pushing to fork again again');

		//pr($Git->run('log', array('--pretty=oneline')));
		//$info = $Git->run('log', array("--pretty=format:%P%x00%H%x00%an%x00%ai%x00%s"), 'capture');
		//list($parent, $revision, $author, $commit_date, $message) = explode(chr(0), $info[0]);

		//pr($Git->run('rev-list', array($parent, $revision)));

		//pr($Git->run('log', array($revision, "--pretty=format:%P%x00%H%x00%an%x00%ai%x00%s"), 'capture'));
		//pr($Git->debug);

	}

	function testFindBranches() {
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$Git->run('branch new');
		$Git->update();


		$results = $Git->find('branches');
		$this->assertEqual($results, array('master', 'new'));

		//pr($Git->debug);
	}

	function testDelete() {
		$Git->logReponse = true;
		$Git = new Git($this->__repos[1]);
		$this->assertTrue($Git->create());
		$this->assertTrue(file_exists(TMP . 'tests/git/repo/test.git'));
		$this->assertTrue(file_exists(TMP . 'tests/git/working/test/master/.git'));

		$Git->run('branch new');
		$Git->update();
		$Git->branch('new', true);
		$this->assertTrue($Git->delete());

		$Git->branch('master', true);
		$results = $Git->find('branches');
		$this->assertEqual($results, array('master'));

		//pr($Git->debug);
		//pr($Git->response);

		//die();
	}

	function testPathInfo() {
		//pr($Git->pathInfo());
	}


	function testPull() {
		//$Git->pull('origin', 'master');
	}

	function testUpdate() {
		//pr($Git->commit("6a4766a9766652f92c0dfe0f0b990408bda91cee"));
		//pr($Git->update());
	}

	function testTree() {
	//	pr($Git->tree('master'));
	}
}
?>
}
?>