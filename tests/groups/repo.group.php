<?php
/**
 * Chaw : source code and project management
 *
 * @copyright  Copyright 2009, Garrett J. Woodworth (gwoohoo@gmail.com)
 * @license    GNU AFFERO GENERAL PUBLIC LICENSE v3 (http://opensource.org/licenses/agpl-v3.html)
 *
 */
/**
 * undocumented class
 *
 * @package default
 */
class RepoGroupTest extends GroupTest {

	/**
	 * label property
	 *
	 * @access public
	 */
	var $label = 'Test All Repo Types';

	/**
	 * RepoGroupGroupTest method
	 *
	 * @access public
	 * @return void
	 */
	function RepoGroupTest() {
		$path = dirname(dirname(__FILE__));
		TestManager::addTestCasesFromDirectory($this, $path . DS . 'cases' . DS . 'models');
	}
}