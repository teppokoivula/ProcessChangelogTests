<?php

/**
 * PHPUnit tests for Process Changelog ProcessWire module
 * 
 * Intended to be run against a clean installation of ProcessWire with Process
 * Changelog installed. Most of the tests included depend on each other, which
 * is why they're grouped together in one file and use depends annotation.
 * 
 * DO NOT run these tests against production site, as they will add, edit and
 * remove pages when necessary, thus potentially seriously damaging your site!
 * 
 * @author Teppo Koivula
 */

class ProcessChangelogTest extends PHPUnit_Framework_TestCase {

    /**
     * Static properties shared by all tests; these are part helpers and part
     * requirement set by certain features of ProcessWire.
     *
     */
    protected static $operations = array();
    protected static $page_id;

    /**
     * Executed once before tests
     *
     * Set test environment up by removing old data, bootstrapping ProcessWire
     * and making sure that module undergoing tests is properly installed.
     * 
     */
    public static function setUpBeforeClass() {
        
        // Bootstrap ProcessWire
        require '../../../index.php';

        // Install module (if possible and not already installed)
        $module = substr(__CLASS__, 0, strlen(__CLASS__)-4);
        if (!wire('modules')->isInstalled($module)) {
        	if (wire('modules')->isInstallable($module)) {
        		wire('modules')->install($module);
        		exit("Module $module installed, please rerun tests.");
        	} else {
        		exit("Module $module not installable, please install manually and rerun tests.");
        	}
        }

        // Delete all rows from changelog database table
        wire('db')->query("DELETE FROM " . ProcessChangelogHooks::TABLE_NAME);

    }

    /**
     * Executed once after tests
     *
     * Cleanup; remove any pages created but not removed during tests and
     * clear collected changelog data.
     *
     */
    public static function tearDownAfterClass() {
        foreach (wire('pages')->find("title^='a test page', include=all") as $page) {
        	$page->delete();
        }
        wire('db')->query("DELETE FROM " . ProcessChangelogHooks::TABLE_NAME);
    }

    /**
     * Executed after each test
     *
     * At the moment all tests end with same assertion, so it makes sense to
     * move it somewhere where it gets executed automatically after each test.
     *
     */
    public function tearDown() {
        $sql = "SELECT COUNT(*) FROM " . ProcessChangelogHooks::TABLE_NAME;
        $row = wire('db')->query($sql)->fetch_row();
        $this->assertEquals(count(self::$operations), reset($row));
    }

    /**
     * Add new page
     *
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

}