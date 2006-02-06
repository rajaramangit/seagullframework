<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Copyright (c) 2005, Demian Turner                                         |
// | All rights reserved.                                                      |
// |                                                                           |
// | Redistribution and use in source and binary forms, with or without        |
// | modification, are permitted provided that the following conditions        |
// | are met:                                                                  |
// |                                                                           |
// | o Redistributions of source code must retain the above copyright          |
// |   notice, this list of conditions and the following disclaimer.           |
// | o Redistributions in binary form must reproduce the above copyright       |
// |   notice, this list of conditions and the following disclaimer in the     |
// |   documentation and/or other materials provided with the distribution.    |
// | o The names of the authors may not be used to endorse or promote          |
// |   products derived from this software without specific prior written      |
// |   permission.                                                             |
// |                                                                           |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS       |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT         |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR     |
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT      |
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,     |
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT          |
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,     |
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY     |
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT       |
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE     |
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.      |
// |                                                                           |
// +---------------------------------------------------------------------------+
// | Seagull 0.5                                                               |
// +---------------------------------------------------------------------------+
// | AppController.php                                                         |
// +---------------------------------------------------------------------------+
// | Author:   Demian Turner <demian@phpkitchen.com>                           |
// +---------------------------------------------------------------------------+
// $Id: Controller.php,v 1.49 2005/06/23 19:15:25 demian Exp $

require_once dirname(__FILE__)  . '/Registry.php';
require_once dirname(__FILE__)  . '/Request.php';
require_once dirname(__FILE__)  . '/Misc.php';
require_once dirname(__FILE__)  . '/../SGL.php';
require_once dirname(__FILE__)  . '/Config.php';
require_once dirname(__FILE__)  . '/Tasks/Process.php';
require_once dirname(__FILE__)  . '/Tasks/Init.php';
require_once dirname(__FILE__)  . '/TaskRunner.php';

/**
 * Application controller.
 *
 * @package SGL
 * @author  Demian Turner <demian@phpkitchen.com>
 * @version $Revision: 1.49 $
 */
class SGL_AppController
{
    function init()
    {
        SGL_AppController::setupMinimumEnv();

        $autoLoad = (file_exists(SGL_VAR_DIR  . '/INSTALL_COMPLETE.php'))
            ? true
            : false;
        $c = &SGL_Config::singleton($autoLoad);

        $init = new SGL_TaskRunner();
        $init->addData($c->getAll());
        $init->addTask(new SGL_Task_SetupConstantsFinish());
        $init->addTask(new SGL_Task_ModifyIniSettings());
        $init->addTask(new SGL_Task_SetBaseUrl());
        $init->addTask(new SGL_Task_SetGlobals());
        $init->addTask(new SGL_Task_RegisterTrustedIPs());
        $init->addTask(new SGL_Task_EnsureBC());
        $init->main();
        define('SGL_INITIALISED', true);
    }

    function setupMinimumEnv()
    {
        $init = new SGL_TaskRunner();
        $init->addTask(new SGL_Task_SetupPaths());
        $init->addTask(new SGL_Task_SetupConstantsStart());
        $init->main();
    }

    /**
     * Main invocation, init tasks plus main process.
     *
     */
    function run()
    {
        if (!defined('SGL_INITIALISED')) {
            SGL_AppController::init();
        }
        //  assign request to registry
        $input = &SGL_Registry::singleton();
        $input->setRequest($req = SGL_Request::singleton());

        $process =  new SGL_Process_Init(
                    new SGL_Process_SetupErrorHandling(
                    new SGL_Process_SetupORM(
        			new SGL_Process_StripMagicQuotes(
                    new SGL_Process_DiscoverClientOs(
                    new SGL_Process_ResolveManager(
                    new SGL_Process_CreateSession(
                    new SGL_Process_SetupLangSupport(
                    new SGL_Process_SetupPerms(
                    new SGL_Process_AuthenticateRequest(
                    new SGL_Process_BuildHeaders(
                    new SGL_Process_SetupLocale(
                    new SGL_Process_DetectDebug(
                    new SGL_Process_DetectBlackListing(
                    new SGL_MainProcess()
                   ))))))))))))));

        $process->process($input);
    }

    /**
     * Adds pages to a Wizard queue.
     *
     * @access  public
     * @param   string  $pageName   the name of the calling script
     * @param   array   $param      params to be appended to URL
     * @return  void
     */
    function addPage($pageName, $param=null)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $aPages = SGL_Session::get('wiz_sequence');
        if (isset($pageName)) {

            //  pagename, isCurrent, param
            $aPages[] = array(  'pageName'  => $pageName,
                                'current'   => false,
                                'param'     => $param);
        }
        SGL_Session::set('wiz_sequence', $aPages);
        return true;
    }

    /**
     * Loads sequence of pages from Wizard queue and starts execution.
     *
     * @access  public
     * @return  void
     */
    function startWizard()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $aPages = SGL_Session::get('wiz_sequence');

        //  set first page to enabled
        $aPages[0]['current'] = true;
        SGL_Session::set('wiz_sequence', $aPages);
        SGL_HTTP::redirect($aPages[0]['pageName'],$aPages[0]['param']);
        return true;
    }
}

/**
 * Abstract renderer strategy
 *
 * @abstract
 */
class SGL_OutputRendererStrategy
{
    /**
     * Prepare renderer options.
     *
     */
    function initEngine() {}

    /**
     * Abstract render method.
     *
     * @param SGL_View $view
     */
    function render($view) {}
}

/**
 * Container for output data and renderer strategy.
 *
 * @abstract
 *
 */
class SGL_View
{
	/**
	 * Output object.
	 *
	 * @var SGL_Output
	 */
	var $data;

    /**
     * Reference to renderer strategy.
     *
     * @var SGL_OutputRendererStrategy
     */
    var $rendererStrategy;

    /**
     * Constructor.
     *
     * @param SGL_Output $data
     * @param SGL_OutputRendererStrategy $rendererStrategy
     * @return SGL_View
     */
    function SGL_View($data, $rendererStrategy)
    {
    	$this->data = $data;
    	$this->rendererStrategy = $rendererStrategy;
    }

    /**
     * Post processing tasks specific to view type.
     *
     * @abstract
     * @return boolean
     */
    function postProcess() {}


    /**
     * Delegates rendering strategy based on view.
     *
     * @return string   Rendered output data
     */
    function render()
    {
    	return $this->rendererStrategy->render($this);
    }
}

class SGL_HtmlView extends SGL_View
{
    /**
     * Html specific implementation of view object.
     *
     * @param SGL_Output $data
     * @param SGL_OutputRendererStrategy $outputRendererStrategy
     * @return SGL_HtmlView
     */
    function SGL_HtmlView(&$data, $outputRendererStrategy)
    {
    	parent::SGL_View($data, $outputRendererStrategy);
    }

    function postProcess(/*SGL_View*/ &$view)
    {
        $process =  new SGL_Process_BuildOutputData(
                    new SGL_Process_SetupWysiwyg(
                    new SGL_Process_GetPerformanceInfo(
                    new SGL_Process_SetupGui(
                    new SGL_Process_SetupNavigation(
                    new SGL_Process_SetupBlocks()
                   )))));

        $process->process($view);
    }
}

class SGL_CliView extends SGL_View
{
    /**
     * Html specific implementation of view object.
     *
     * @param SGL_Output $data
     * @param SGL_OutputRendererStrategy $outputRendererStrategy
     * @return SGL_HtmlView
     */
    function SGL_CliView(&$data, $outputRendererStrategy)
    {
    	parent::SGL_View($data, $outputRendererStrategy);
    }

    function postProcess(/*SGL_View*/ &$view)
    {
        $process =  new SGL_Process_BuildOutputData(
                    new SGL_Process_GetPerformanceInfo(
                    new SGL_Void()
                   ));

        $process->process($view);
    }
}

class SGL_HtmlSimpleView extends SGL_View
{
    /**
     * HTML renderer decorator
     *
     * @param SGL_Output $data
     * @return string   Rendered output data
     */
    function SGL_HtmlSimpleView(&$data)
    {
        //  prepare renderer class
        $c              = &SGL_Config::singleton();
        $conf           = $c->getAll();
        $templateEngine = ucfirst($conf['site']['templateEngine']);
        $rendererClass = 'SGL_HtmlRenderer_'.$templateEngine.'Strategy';
        $rendererFile = $templateEngine.'Strategy.php';

        if (file_exists(SGL_LIB_DIR .'/SGL/HtmlRenderer/'. $rendererFile)) {
        	require_once SGL_LIB_DIR .'/SGL/HtmlRenderer/'. $rendererFile;
        } else {
        	PEAR::raiseError('Could not find renderer',
        		SGL_ERROR_NOFILE, PEAR_ERROR_DIE);
        }

    	parent::SGL_View($data, new $rendererClass);
    }
}

/**
 * Abstract request processor.
 *
 * @abstract
 *
 */
class SGL_ProcessRequest
{
    function process(/*SGL_Output*/ $data) {}
}

/**
 * Decorator.
 *
 * @abstract
 */
class SGL_DecorateProcess extends SGL_ProcessRequest
{
    var $processRequest;

    function SGL_DecorateProcess(/* SGL_ProcessRequest */ $pr)
    {
        $this->processRequest = $pr;
        $this->c = &SGL_Config::singleton();
        $this->conf = $this->c->getAll();
    }
}
?>