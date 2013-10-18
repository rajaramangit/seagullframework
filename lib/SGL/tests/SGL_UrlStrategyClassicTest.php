<?php

/**
 * Test suite.
 *
 * @package SGL
 * @author  Demian Turner <demian@phpkitchen.net>
 * @version $Id: UrlTest.ndb.php,v 1.1 2005/06/23 14:56:01 demian Exp $
 */
class SGL_UrlStrategyClassicTest extends PHPUnit_Framework_TestCase
{

    function testClassicParserSimple()
    {
        $uri = 'http://example.com?moduleName=user&managerName=account';
        $url = new SGL_Url($uri, true, new SGL_UrlParser_ClassicStrategy());
        $url->init();
        $ret = $url->getQueryData();

        //  assert expected keys present
        $this->assertTrue(array_key_exists('moduleName', $ret));
        $this->assertTrue(array_key_exists('managerName', $ret));

        //  assert expected values present
        $this->assertEquals($ret['moduleName'], 'user');
        $this->assertEquals($ret['managerName'], 'account');
    }

    function testClassicParserNoQueryStr()
    {
        $uri = 'http://example.com';
        $url = new SGL_Url($uri, true, new SGL_UrlParser_ClassicStrategy());
        $url->init();
        $ret = $url->getQueryData();

        //  assert expected keys present
        $this->assertTrue(!array_key_exists('moduleName', $ret));
        $this->assertTrue(!array_key_exists('managerName', $ret));
        $this->assertEquals($ret, array());
    }

    function testClassicParserSefQueryStr()
    {
        $uri = 'http://example.com/index.php/foo/bar/frmUserName/123';
        $url = new SGL_Url($uri, true, new SGL_UrlParser_ClassicStrategy());
        $url->init();
        $ret = $url->getQueryData();

        //  assert expected keys present
        $this->assertTrue(!array_key_exists('moduleName', $ret));
        $this->assertTrue(!array_key_exists('managerName', $ret));
        $this->assertEquals($ret, array());
    }
}
?>