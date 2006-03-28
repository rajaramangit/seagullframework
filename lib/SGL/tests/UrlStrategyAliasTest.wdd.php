<?php

require_once dirname(__FILE__) . '/../UrlParser/AliasStrategy.php';

/**
 * Test suite.
 *
 * @package SGL
 * @author  Demian Turner <demian@phpkitchen.net>
 * @version $Id: UrlTest.ndb.php,v 1.1 2005/06/23 14:56:01 demian Exp $
 */
class UrlStrategyAliasTest extends UnitTestCase
{

    function UrlStrategySefTest()
    {
        $this->UnitTestCase('alias strategy test');
    }

    function setup()
    {
        $this->strategy = new SGL_UrlParser_AliasStrategy();
        $c = &SGL_Config::singleton();
        $this->conf = $c->getAll();
        $this->obj = new stdClass();
        $this->exampleUrl = 'http://example.com/';
    }

    function tearDown()
    {
        unset($this->strategy, $this->obj);
    }
}
?>