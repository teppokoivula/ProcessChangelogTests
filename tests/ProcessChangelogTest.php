<?php

/**
 * PHPUnit tests for Process Changelog ProcessWire module
 * 
 * Intended to be run against a clean installation of ProcessWire with Process
 * Changelog included. Most of the tests included depend on each other, which
 * is why they're grouped together in one file and use depends annotation.
 * 
 * DO NOT run these tests against production site, as they will add, edit and
 * remove pages when necessary, thus potentially seriously damaging your site!
 * 
 * @author Teppo Koivula, <teppo@flamingruby.com>
 * @copyright Copyright (c) 2013, Teppo Koivula
 * @license GNU/GPL v2, see LICENSE
 */

class ProcessChangelogTest extends PHPUnit_Framework_TestCase {

    /**
     * Static properties shared by all tests; these are part helpers and part
     * requirement set by certain features of ProcessWire.
     *
     */
    protected static $operations;
    protected static $page_id;
    protected static $module_name;

    /**
     * Executed once before tests
     *
     * Set test environment up by removing old data, bootstrapping ProcessWire
     * and making sure that module undergoing tests is uinstalled.
     * 
     */
    public static function setUpBeforeClass() {
        
        // Bootstrap ProcessWire
        require '../../../index.php';

        // Messages and errors
        $messages = array();
        $errors = array();

        // Set module name (unless already set)
        if (!self::$module_name) {
            self::$module_name = substr(__CLASS__, 0, strlen(__CLASS__)-4);
        }

        // Uninstall module (if installed)
        if (wire('modules')->isInstalled(self::$module_name)) {
            if (wire('modules')->isUninstallable(self::$module_name)) {
                wire('modules')->uninstall(self::$module_name);
                $messages[] = "Module '" . self::$module_name . "' uninstalled.";
            } else {
                $errors[] = "Module '" . self::$module_name . "' not uninstallable, please uninstall manually before any new tests.";
            }
        }

        // Messages and errors
        if ($messages) echo "\n" . implode($messages, " ") . "\n\n";
        if ($errors) die("\n" . implode($errors, " ") . "\n\n");

    }

    /**
     * Executed once after all tests are finished
     *
     * Cleanup; remove any pages created but not removed during tests (and
     * uninstall the module) in order to prepare this site for new tests.
     * Also clear all collected data from custom database table.
     *
     */
    public static function tearDownAfterClass() {

        // Messages and errors
        $messages = array();
        $errors = array();

        // Remove any pages created but not removed during tests
        foreach (wire('pages')->find("title^='a test page', include=all") as $page) {
            $page->delete();
            $messages[] = "Page '{$page->url}' deleted.";
        }

        // Uninstall module (if installed)
        if (wire('modules')->isInstalled(self::$module_name)) {
            if (wire('modules')->isUninstallable(self::$module_name)) {
                wire('modules')->uninstall(self::$module_name);
                $messages[] = "Module '" . self::$module_name . "' uninstalled.";
            } else {
                $errors[] = "Module '" . self::$module_name . "' not uninstallable, please uninstall manually before any new tests.";
            }
        }

        // Messages and errors
        if ($messages) echo "\n\n" . implode($messages, " ") . "\n";
        if ($errors) die("\n\n" . implode($errors, " ") . "\n");

    }

    /**
     * Executed after each test
     *
     * Almost all tests end with same assertion, so it makes sense to move it
     * somewhere where it gets executed automatically after each test.
     *
     */
    public function tearDown() {
        if (!in_array($this->getName(), array("testModuleIsInstallable", "testUninstallModule"))) {
            $sql = "SELECT COUNT(*) FROM " . constant(self::$module_name . "Hooks::TABLE_NAME");
            $row = wire('db')->query($sql)->fetch_row();
            $this->assertEquals(count(self::$operations), reset($row));
        }
    }

    /**
     * Make sure that module is installable
     *
     * @return string module name
     */
    public function testModuleIsInstallable() {
        $this->assertTrue(wire('modules')->isInstallable(self::$module_name));
        return self::$module_name;
    }

    /**
     * Install module
     * 
     * @depends testModuleIsInstallable
     * @param string $module_name
     * @return string module name
     */
    public function testInstallModule($module_name) {
        
        // Install the module
        wire('modules')->install($module_name);
        wire('modules')->triggerInit();
        $this->assertTrue(wire('modules')->isInstalled($module_name));
        
        return $module_name;

    }

    /**
     * Add new page
     *
     * @depends testInstallModule
     * @return Page
     */
    public function testAddPage()
    {
        $page = new Page;
        $page->parent = wire('pages')->get('/');
        $page->template = wire('templates')->get('basic-page');
        $page->title = "a test page";
        $page->save();
        self::$page_id = $page->id;
        self::$operations[] = "added";
        
        return $page;
    }

    /**
     * Make a change to previously added page
     *
     * @depends testAddPage
     * @param Page $page
     * @return Page
     */
    public function testEditPage(Page $page)
    {
        $page->setOutputFormatting(false);
        $page->title = $page->title . " 2";
        $page->save();
        self::$operations[] = "edited";
        
        return $page;
    }

    /**
     * Unpublish previously added page
     *
     * @depends testEditPage
     * @param Page $page
     * @return Page
     */
    public function testUnpublishPage(Page $page)
    {
        $page->addStatus(Page::statusUnpublished);
        $page->save();
        self::$operations[] = "unpublished";
        
        return $page;
    }

    /**
     * Publish previously added page
     *
     * @depends testUnpublishPage
     * @param Page $page
     * @return Page
     */
    public function testPublishPage(Page $page)
    {
        $page->removeStatus(Page::statusUnpublished);
        $page->save();
        self::$operations[] = "published";
        
        return $page;
    }

    /**
     * Edit and unpublish previously added page
     *
     * @depends testPublishPage
     * @param Page $page
     * @return Page
     */
    public function testEditAndUnpublishPage(Page $page)
    {
        $page->addStatus(Page::statusUnpublished);
        $page->sidebar = "sidebar test";
        $page->save();
        self::$operations[] = "edited";
        self::$operations[] = "unpublished";
        
        return $page;
    }

    /**
     * Move previously added page 
     *
     * @depends testEditAndUnpublishPage
     * @param Page $page
     * @return Page
     */
    public function testMovePage(Page $page)
    {
        $page->parent = wire('pages')->get("/")->child();
        $page->save();
        self::$operations[] = "moved";

        return $page;
    }

    /**
     * Trash previously added page
     *
     * @depends testMovePage
     * @param Page $page
     * @return Page
     */
    public function testTrashPage(Page $page)
    {
        $page->trash();
        self::$operations[] = "trashed";
        
        return $page;
    }

    /**
     * Restore previously trashed page
     *
     * @depends testTrashPage
     * @param Page $page
     * @return Page
     */
    public function testRestorePage(Page $page) {
        $page->parent = wire('pages')->get("/");
        wire('pages')->restore($page);
        self::$operations[] = "restored";
        
        return $page;
    }

    /**
     * Delete previously added page
     *
     * @depends testRestorePage
     * @param Page $page
     * @return Page
     */
    public function testDeletePage(Page $page)
    {
        $page->delete();
        self::$operations[] = "deleted";
    }

    /**
     * Make sure that data collected in database matches performed operations
     *
     * @depends testInstallModule
     */
    public function testTableOperations()
    {
        $sql = "SELECT GROUP_CONCAT(operation) FROM " . ProcessChangelogHooks::TABLE_NAME;
        $row = wire('db')->query($sql)->fetch_row();
        $this->assertEquals(implode(",", self::$operations), reset($row));
    }

    /**
     * Make sure that database contains correct data
     *
     * @depends testInstallModule
     * @dataProvider providerForTestTableData
     * @param int $key ID number of current database table row
     * @param string $data Expected database table row data
     */
    public function testTableData($key, $data)
    {
        // When page is trashed, it's name is prefixed with it's ID. We're using
        // "?" as placeholder for that ID in the data provided by data provider.
        if (strpos($data, "?") !== false) $data = str_replace("?", self::$page_id, $data);

        $sql = "SELECT data FROM " . ProcessChangelogHooks::TABLE_NAME . " LIMIT $key,1";
        $row = wire('db')->query($sql)->fetch_row();
        $this->assertEquals($data, reset($row));
    }

    /**
     * Data provider for testTableData
     *
     * @return array Array of expected database rows, each an array of it's own
     */
    public static function providerForTestTableData() {
        return array(
            array(0, '{"Page title":"a test page","Page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/"}'),
            array(1, '{"Page title":"a test page 2","Page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/","Fields edited":"title"}'),
            array(2, '{"Page title":"a test page 2","Page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/"}'),
            array(3, '{"Page title":"a test page 2","Page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/"}'),
            array(4, '{"Page title":"a test page 2","Page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/","Fields edited":"sidebar"}'),
            array(5, '{"Page title":"a test page 2","Page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/"}'),
            array(6, '{"Page title":"a test page 2","Page name":"a-test-page","Template name":"basic-page","Page URL":"/about/a-test-page/","Previous page URL":"/a-test-page/"}'),
            array(7, '{"Page title":"a test page 2","Page name":"?_a-test-page","Previous page name":"a-test-page","Template name":"basic-page","Page URL":"/trash/?_a-test-page/","Previous page URL":"/about/a-test-page/"}'),
            array(8, '{"Page title":"a test page 2","Page name":"a-test-page","Previous page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/","Previous page URL":"/trash/a-test-page/"}'),
            array(9, '{"Page name":"a-test-page","Previous page name":"a-test-page","Template name":"basic-page","Page URL":"/a-test-page/","Previous page URL":"/trash/a-test-page/"}'),
        );
    }

    /**
     * Make sure that module is uninstallable
     *
     * @depends testInstallModule
     * @param string $module_name
     * @return string module name
     */
    public function testModuleIsUninstallable($module_name) {
        $this->assertTrue(wire('modules')->isUninstallable($module_name));
        return $module_name;
    }

    /**
     * Uninstall module
     *
     * @depends testModuleIsUninstallable
     * @param string $module_name
     */
    public function testUninstallModule($module_name) {
        wire('modules')->uninstall($module_name);
        $this->assertFalse(wire('modules')->isInstalled($module_name));
    }

}
