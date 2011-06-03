<?php
/**
 *
 * ThinkUp/tests/TestOfExportServiceUserDataController.php
 *
 * Copyright (c) 2011 Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkupapp.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2011 Gina Trapani
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 */
require_once dirname(__FILE__).'/init.tests.php';
require_once THINKUP_ROOT_PATH.'webapp/_lib/extlib/simpletest/autorun.php';
require_once THINKUP_ROOT_PATH.'webapp/config.inc.php';
//require_once THINKUP_ROOT_PATH.'webapp/_lib/controller/class.BackupController.php';

class TestOfExportServiceUserDataController extends ThinkUpUnitTestCase {

    public function setUp() {
        parent::setUp();
        new ExportMySQLDAO();
        $this->config = Config::getInstance();
        $this->pdo = ExportMySQLDAO::$PDO;
        //$this->backup_file = THINKUP_WEBAPP_PATH . BackupDAO::CACHE_DIR . '/thinkup_db_backup.zip';
        $this->export_test = THINKUP_WEBAPP_PATH . BackupDAO::CACHE_DIR . '/thinkup_db_backup_test.zip';
        //$this->backup_dir = THINKUP_WEBAPP_PATH . BackupDAO::CACHE_DIR . '/backup';

        $session = new Session();
        $cryptpass = $session->pwdcrypt("secretpassword");

        $owner = array('id'=>1, 'email'=>'me@example.com', 'pwd'=>$cryptpass, 'is_activated'=>1, 'is_admin'=>1);
        $this->builders[] = FixtureBuilder::build('owners', $owner);

        $instance = array('id'=>1, 'network_username'=>'test_user', 'network'=>'twitter');
        $this->builders[] = FixtureBuilder::build('instances', $instance);

        $owner_instance = array('owner_id'=>1, 'instance_id'=>1);
        $this->builders[] = FixtureBuilder::build('owner_instances', $owner_instance);

        $this->builders[] = FixtureBuilder::build('users', array('user_id'=>10, 'network'=>'twitter',
        'user_name'=>'test_user'));
    }

    public function tearDown() {
        parent::tearDown();

        if(file_exists($this->export_test)) {
            unlink($this->export_test);
        }

        //set zip class requirement class name back
        BackupController::$zip_class_req = 'ZipArchive';

        $this->builders[] = null;
    }

    public function testConstructor() {
        $controller = new ExportServiceUserDataController(true);
        $this->assertTrue(isset($controller));
    }

    public function testNotLoggedIn() {
        $controller = new ExportServiceUserDataController(true);
        $results = $controller->go();
        $v_mgr = $controller->getViewManager();
        $config = Config::getInstance();
        $this->assertEqual('You must <a href="'.$config->getValue('site_root_path').
        'session/login.php">log in</a> to do this.', $v_mgr->getTemplateDataItem('errormsg'));
    }

    public function testNonAdminAccess() {
        $this->simulateLogin('me@example.com');
        $controller = new ExportServiceUserDataController(true);
        $this->expectException('Exception', 'You must be a ThinkUp admin to do this');
        $results = $controller->control();
    }

    public function testNoZipSupport() {
        BackupController::$zip_class_req = 'NoSuchZipArchiveClass';
        $this->simulateLogin('me@example.com', true);
        $controller = new ExportServiceUserDataController(true);
        $results = $controller->control();
        $this->assertPattern('/setup does not support a library/', $results);
    }

    public function testLoadExportView() {
        $this->simulateLogin('me@example.com', true);
        $controller = new ExportServiceUserDataController(true);
        $results = $controller->control();
        $this->assertPattern('/Export Service User Data/', $results);
    }

    public function testExport() {
        $this->simulateLogin('me@example.com', true);
        $controller = new ExportServiceUserDataController(true);
        $_POST['instance_id'] = 1;
        ob_start();
        $controller->go();
        $results = ob_get_contents();
        ob_end_clean();

        // write downloaded zip file to disk...
        $fh = fopen($this->export_test, 'wb');
        fwrite($fh, $results);
        fclose($fh);

        // verify contents of zip file...
        $za = new ZipArchive();
        $za->open($this->export_test);
        $zip_files = array();
        for ($i=0; $i<$za->numFiles;$i++) {
            $zfile = $za->statIndex($i);
            $zip_files[$zfile['name']] = $zfile['name'];
        }

        //verify we have create table file
        $this->assertTrue($zip_files["/README.txt"]);
        $this->assertTrue($zip_files["/posts.tmp"]);
        $this->assertTrue($zip_files["/links.tmp"]);
        $this->assertTrue($zip_files["/users_from_posts.tmp"]);
        $this->assertTrue($zip_files["/follows.tmp"]);
        $za->close();
    }
}