<?php

namespace plugin\struct\test;

use plugin\struct\meta;

spl_autoload_register(array('action_plugin_struct_autoloader', 'autoloader'));

/**
 * Tests handling dynamic search parameters
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class SearchConfigParameter_struct_test extends \DokuWikiTest {

    protected $pluginsEnabled = array('struct', 'sqlite');

    public function setUp() {
        parent::setUp();

        $sb = new meta\SchemaBuilder(
            'schema1',
            array(
                'new' => array(
                    'new1' => array('label' => 'first', 'class' => 'Text', 'sort' => 10, 'ismulti' => 0, 'isenabled' => 1),
                    'new2' => array('label' => 'second', 'class' => 'Text', 'sort' => 20, 'ismulti' => 1, 'isenabled' => 1),
                    'new3' => array('label' => 'third', 'class' => 'Text', 'sort' => 30, 'ismulti' => 0, 'isenabled' => 1),
                    'new4' => array('label' => 'fourth', 'class' => 'Text', 'sort' => 40, 'ismulti' => 0, 'isenabled' => 1),
                )
            )
        );
        $sb->build();

        $sb = new meta\SchemaBuilder(
            'schema2',
            array(
                'new' => array(
                    'new1' => array('label' => 'afirst', 'class' => 'Text', 'sort' => 10, 'ismulti' => 0, 'isenabled' => 1),
                    'new2' => array('label' => 'asecond', 'class' => 'Text', 'sort' => 20, 'ismulti' => 1, 'isenabled' => 1),
                    'new3' => array('label' => 'athird', 'class' => 'Text', 'sort' => 30, 'ismulti' => 0, 'isenabled' => 1),
                    'new4' => array('label' => 'afourth', 'class' => 'Text', 'sort' => 40, 'ismulti' => 0, 'isenabled' => 1),
                )
            )
        );
        $sb->build();

        $as = new mock\Assignments();

        $as->assignPageSchema('page01', 'schema1');
        $sd = new meta\SchemaData('schema1', 'page01', time());
        $sd->saveData(
            array(
                'first' => 'first data',
                'second' => array('second data', 'more data', 'even more'),
                'third' => 'third data',
                'fourth' => 'fourth data'
            )
        );

        $as->assignPageSchema('page01', 'schema2');
        $sd = new meta\SchemaData('schema2', 'page01', time());
        $sd->saveData(
            array(
                'afirst' => 'first data',
                'asecond' => array('second data', 'more data', 'even more'),
                'athird' => 'third data',
                'afourth' => 'fourth data'
            )
        );

        for($i=10; $i <=20; $i++) {
            $as->assignPageSchema("page$i", 'schema2');
            $sd = new meta\SchemaData('schema2', "page$i", time());
            $sd->saveData(
                array(
                    'afirst' => "page$i first data",
                    'asecond' => array("page$i second data"),
                    'athird' => "page$i third data",
                    'afourth' => "page$i fourth data"
                )
            );
        }
    }

    protected function tearDown() {
        parent::tearDown();

        /** @var \helper_plugin_struct_db $sqlite */
        $sqlite = plugin_load('helper', 'struct_db');
        $sqlite->resetDB();
    }

    public function test_constructor() {
        global $INPUT;

        $data = array(
            'schemas' => array(
                array('schema1', 'alias1'),
                array('schema2', 'alias2'),
            ),
            'cols' => array(
                '%pageid%',
                'first', 'second', 'third', 'fourth',
                'afirst', 'asecond', 'athird', 'afourth',
            )
        );

        // init with no parameters
        $expect = $data;
        $params = array();
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());

        // init with sort
        $INPUT->set(meta\SearchConfigParameters::$PARAM_SORT, '^alias2.athird');
        $expect['sort'] = array(array('schema2.athird', false));
        $params[meta\SearchConfigParameters::$PARAM_SORT] = '^schema2.athird';
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());

        // init with offset
        $INPUT->set(meta\SearchConfigParameters::$PARAM_OFFSET, 25);
        $expect['offset'] = 25;
        $params[meta\SearchConfigParameters::$PARAM_OFFSET] = 25;
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());

        // init with filters
        $_REQUEST[meta\SearchConfigParameters::$PARAM_FILTER]['alias1.first*~'] = 'test';
        $_REQUEST[meta\SearchConfigParameters::$PARAM_FILTER]['afirst='] = 'test2';
        $expect['filter'] = array(
            array('schema1.first', '*~', 'test', 'AND'),
            array('schema2.afirst', '=', 'test2', 'AND')
        );
        $params[meta\SearchConfigParameters::$PARAM_FILTER .'[schema1.first*~]'] = 'test';
        $params[meta\SearchConfigParameters::$PARAM_FILTER .'[schema2.afirst=]'] = 'test2';
        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $this->assertEquals($expect, $searchConfig->getConf());
        $this->assertEquals($params, $dynamic->getURLParameters());
    }

    public function test_filter() {
        $data = array(
            'schemas' => array(
                array('schema1', 'alias1'),
                array('schema2', 'alias2'),
            ),
            'cols' => array(
                '%pageid%',
                'first', 'second', 'third', 'fourth',
                'afirst', 'asecond', 'athird', 'afourth',
            )
        );

        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();
        $expect = array();
        $this->assertEquals($expect, $dynamic->getFilters());

        $dynamic->addFilter('first', '*~', 'test');
        $expect = array('schema1.first' => array('*~', 'test'));
        $this->assertEquals($expect, $dynamic->getFilters());

        $dynamic->addFilter('asecond', '*~', 'test2');
        $expect = array('schema1.first' => array('*~', 'test'), 'schema2.asecond' => array('*~', 'test2'));
        $this->assertEquals($expect, $dynamic->getFilters());

        // overwrite a filter
        $dynamic->addFilter('asecond', '*~', 'foobar');
        $expect = array('schema1.first' => array('*~', 'test'), 'schema2.asecond' => array('*~', 'foobar'));
        $this->assertEquals($expect, $dynamic->getFilters());

        // overwrite a filter with blank removes
        $dynamic->addFilter('asecond', '*~', '');
        $expect = array('schema1.first' => array('*~', 'test'));
        $this->assertEquals($expect, $dynamic->getFilters());

        // adding unknown filter does nothing
        $dynamic->addFilter('nope', '*~', 'foobar');
        $expect = array('schema1.first' => array('*~', 'test'));
        $this->assertEquals($expect, $dynamic->getFilters());

        // removing unknown column does nothing
        $dynamic->removeFilter('nope');
        $expect = array('schema1.first' => array('*~', 'test'));
        $this->assertEquals($expect, $dynamic->getFilters());

        $dynamic->removeFilter('first');
        $expect = array();
        $this->assertEquals($expect, $dynamic->getFilters());
    }

    public function test_sort() {
        $data = array(
            'schemas' => array(
                array('schema1', 'alias1'),
                array('schema2', 'alias2'),
            ),
            'cols' => array(
                '%pageid%',
                'first', 'second', 'third', 'fourth',
                'afirst', 'asecond', 'athird', 'afourth',
            )
        );

        $searchConfig = new meta\SearchConfig($data);
        $dynamic = $searchConfig->getDynamicParameters();

        $dynamic->setSort('%pageid%', true);
        $conf = $dynamic->updateConfig($data);
        $param = $dynamic->getURLParameters();
        $this->assertEquals(array(array('%pageid%', true)), $conf['sort']);
        $this->assertArrayHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
        $this->assertEquals('%pageid%', $param[meta\SearchConfigParameters::$PARAM_SORT]);

        $dynamic->setSort('%pageid%', false);
        $conf = $dynamic->updateConfig($data);
        $param = $dynamic->getURLParameters();
        $this->assertEquals(array(array('%pageid%', false)), $conf['sort']);
        $this->assertArrayHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
        $this->assertEquals('^%pageid%', $param[meta\SearchConfigParameters::$PARAM_SORT]);

        $dynamic->removeSort();
        $conf = $dynamic->updateConfig($data);
        $param = $dynamic->getURLParameters();
        $this->assertArrayNotHasKey('sort', $conf);
        $this->assertArrayNotHasKey(meta\SearchConfigParameters::$PARAM_SORT, $param);
    }
}