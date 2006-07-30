<?php
/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Copyright (c) 2006, Demian Turner                                         |
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
// | Seagull 0.6                                                               |
// +---------------------------------------------------------------------------+
// | ModuleMgr.php                                                             |
// +---------------------------------------------------------------------------+
// | Authors:   Demian Turner <demian@phpkitchen.com>                          |
// |            Michael Willemot <michael@sotto.be>                            |
// +---------------------------------------------------------------------------+
// $Id: ModuleMgr.php,v 1.37 2005/06/22 00:32:36 demian Exp $

require_once SGL_MOD_DIR . '/default/classes/DA_Default.php';
require_once 'DB/DataObject.php';

define('SGL_ICONS_PER_ROW', 3);

/**
 * Manages loading of modules.
 *
 * @package default
 * @author  Demian Turner <demian@phpkitchen.com>
 */
class ModuleMgr extends SGL_Manager
{
    function ModuleMgr()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        parent::SGL_Manager();

        $this->pageTitle    = 'Module Manager';
        $this->template     = 'moduleOverview.html';
        $this->da           = &DA_Default::singleton();

        $this->_aActionsMapping =  array(
            'add'       => array('add'),
            'detect'    => array('detect'),
            'insert'    => array('insert', 'redirectToDefault'),
            'install'   => array('install', 'redirectToDefault'),
            'edit'      => array('edit'),
            'update'    => array('update', 'redirectToDefault'),
            'delete'    => array('delete', 'redirectToDefault'),
            'uninstall' => array('uninstall', 'redirectToDefault'),
            'deregister'=> array('deregister', 'redirectToDefault'),
            'list'      => array('list'),
            'overview'  => array('overview'),
        );
    }

    function validate($req, &$input)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $this->validated        = true;
        $input->pageTitle       = $this->pageTitle;
        $input->masterTemplate  = 'masterMinimal.html';
        $input->template        = $this->template;

        //  default action is 'overview' unless paging through results,
        //  in which case default is 'list'
        $input->from            = $req->get('pageID');
        $input->totalItems      = $req->get('totalItems');

        $input->action = ($req->get('action')) ? $req->get('action') : 'list';

        if (!is_null($input->from) && $input->action == 'overview') {
            $input->action = 'list';
        }
        $input->aDelete         = $req->get('frmDelete');
        $input->moduleId        = $req->get('frmModuleId');
        $aModules               = $req->get('module');

        if (count($aModules)) {
            foreach ($aModules as $k => $module) {
                $input->module[$k] = (object)$module;
                $input->module[$k]->is_configurable = (isset($input->module[$k]->is_configurable)) ? 1 : 0;
            }
        }

        $input->submitted       = $req->get('submitted');

        //  validate fields
        $aErrors = array();
        if ($input->submitted || in_array($input->action, array('insert', 'update'))) {
            $aFields = array(
                'name' => 'Please, specify a name',
                'title' => 'Please, specify a title',
                'description' => 'Please, specify a description',
                'icon' => 'Please, specify the name of the icon-file'
            );
            if (!empty($input->module)) {
                foreach ($aFields as $field => $errorMsg) {
                    for ($x = 0; $x < count($input->module); $x++) {
                        if (empty($input->module[$x]->$field)) {
                            $aErrors[$x][$field] = $errorMsg;
                            $aErrors[$field] = $errorMsg;
                        }
                    }
                }
            } else {
                $aErrors['name'] = 'no module data supplied';
            }
        }
        //  if errors have occured -
#commented out because multi-module validation not solved
//        if (isset($aErrors) && count($aErrors)) {
//            SGL::raiseMsg('Please fill in the indicated fields');
//            $input->error = $aErrors;
//            $input->template = 'moduleEdit.html';
//            $input->aModules = $input->module;
//            for ($x = 0; $x < count($input->module); $x++) {
//                $input->isConfigurable = ($input->module[$x]->is_configurable) ? 'checked' : '';
//            }
//            $this->validated = false;
//        }
    }

    function display(&$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $output->aAdminUris = SGL_Util::getAllModuleDirs($onlyRegistered = false);
    }

    function _cmd_syncModules($aModules)
    {
        foreach ($aModules as $module) {
            $aModulesClean[] = $module->name;
        }

        $aSglModules = $this->da->getPackagesByChannel();
        foreach ($aSglModules as $module) {
            if ($module != 'seagull') {
                $aSglModulesClean[] = str_replace('seagull_', '', $module);
            }
        }
        //  determine which PEAR-installed modules are missing from db
        $aRes = array_diff($aSglModulesClean, $aModulesClean);

        if (count($aRes)) {
            foreach ($aRes as $module) {
                $oModule = $this->da->getModuleById();
                $oModule->name = $module;
                $ok = $this->da->addModule($oModule);
                unset($oModule);
            }
        }

    }

    function _cmd_overview(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $aModules = $this->da->getAllModules();
        if (!PEAR::isError($aModules)) {

            //  ensure modules installed with pear packager are in db
            #$this->_syncModules($aModules);

            $ret = array();
            foreach ($aModules as $k => $oModule) {

                //  split module/manager values out as object properties
                if (strpos($oModule->admin_uri, '/') !== false) {
                    list($oModule->module, $oModule->manager) = explode('/', $oModule->admin_uri);

                } elseif (!empty($oModule->admin_uri)) {
                    $oModule->module = $oModule->admin_uri;
                    $oModule->manager = '';
                } else {
                    $oModule->module = '';
                    $oModule->manager = '';
                }
                $oModule->bgnd = ($oModule->is_configurable) ? 'bgnd' : 'outline';
                $oModule->breakRow = !((count($ret)+1) % SGL_ICONS_PER_ROW);
                $ret[] = $oModule;
            }
            $output->aModules = $ret;
        } else {
            SGL::raiseError('getting module list failed', SGL_ERROR_NODATA);
        }
    }

    function _cmd_detect(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $aAllModules = SGL_Util::getAllModuleDirs($onlyRegistered = false);
        $aRegisteredModules = SGL_Util::getAllModuleDirs();
        $aDiff = array_diff($aAllModules, $aRegisteredModules);

        $aModules = array();
        foreach ($aDiff as $modulename) {
            $module = new stdClass();
            $module->name = $modulename;
            $module->description = 'your description here ...';
            $module->icon = "48/module_default.png";
            $aModules[] = $module;
        }

        $output->pageTitle = 'Module Manager :: Discovered';
        $output->action = 'insert';
        $output->mode = 'detect';
        $output->aModules = $aModules;
        $output->template  = 'modulesDetected.html';
    }

    function _cmd_insert(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $output->template = 'moduleList.html';

        foreach ($input->module as $module) {
            if (isset($module->register)) {
                $oModule = $this->da->getModuleById();
                $oModule->setFrom($module);
                $ok = $this->da->addModule($oModule);
            }
        }
        SGL::raiseMsg('Module successfully registered', true,
            SGL_MESSAGE_INFO);
    }

    function _cmd_add(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $output->pageTitle = 'Module Manager :: Add';
        $output->action = 'insert';
        $output->template  = 'moduleEdit.html';

        $module = new stdClass();
        $module->icon = "48/module_default.png";
        $output->aModules = array($module);
    }

    function _cmd_install(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        //  get module name
        $oModule = $this->da->getModuleById($input->moduleId);

        $data = array(
            'createTables' => 1,
            'insertSampleData' => 1,
            'aModuleList' => array($oModule->name),
            'moduleInstall' => true,
            );
        define('SGL_ADMIN_REBUILD', 1);// rename to HIDE_OUTPUT
        require_once SGL_CORE_DIR . '/Task/Install.php';
        $runner = new SGL_TaskRunner();
        $runner->addData($data);
        $runner->addTask(new SGL_Task_DefineTableAliases());
        $runner->addTask(new SGL_Task_DisableForeignKeyChecks());
        $runner->addTask(new SGL_Task_CreateTables());
        $runner->addTask(new SGL_Task_LoadDefaultData());
        $runner->addTask(new SGL_Task_SyncSequences());
        $runner->addTask(new SGL_Task_BuildNavigation());
        $runner->addTask(new SGL_Task_LoadBlockData());
        $runner->addTask(new SGL_Task_LoadSampleData());
        $runner->addTask(new SGL_Task_CreateConstraints());
        $runner->addTask(new SGL_Task_SyncSequences());
        $runner->addTask(new SGL_Task_EnableForeignKeyChecks());
        $runner->addTask(new SGL_Task_CreateDataObjectEntities());
        $runner->addTask(new SGL_Task_CreateDataObjectLinkFile());
        $ok = $runner->main();

        //  de-register original module placeholder
        if ($ok) {
            $rm = DB_DataObject::factory($this->conf['table']['module']);
            $rm->get($input->moduleId);
            $ok = $rm->delete();
        }
        SGL::raiseMsg('Module successfully installed', false, SGL_MESSAGE_INFO);
    }

    function _cmd_edit(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $output->pageTitle = $this->pageTitle . ' :: Edit';
        $output->action = 'update';
        $output->template  = 'moduleEdit.html';
        $oModule = DB_DataObject::factory($this->conf['table']['module']);
        $oModule->get($input->moduleId);
        $output->aModules = array($oModule);
        $output->isConfigurable = ($oModule->is_configurable) ? ' checked' : '';
    }

    function _cmd_update(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $output->template = 'moduleList.html';
        $newEntry = DB_DataObject::factory($this->conf['table']['module']);
        $newEntry->get($input->moduleId);
        $newEntry->setFrom($input->module[0]);
        $success = $newEntry->update();

        if ($success !== false) {
            SGL::raiseMsg('module successfully updated', true, SGL_MESSAGE_INFO);
        } else {
            SGL::raiseError('There was a problem inserting the record',
                SGL_ERROR_NOAFFECTEDROWS);
        }
    }

    function _cmd_deregister(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        //  de-register module
        $rm = DB_DataObject::factory($this->conf['table']['module']);
        $rm->get($input->moduleId);
        $ok = $rm->delete();

        SGL::raiseMsg('The module was successfully de-registered', false, SGL_MESSAGE_INFO);
    }

    function _cmd_delete(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        //  get module name
        $oModule = $this->da->getModuleById($input->moduleId);

        //  delete module files
        $msg = 'The module\'s directory does not appear to be writable and therefore
            could not be removed, please give the webserver permissions to write to it';
        $moduleDir =  SGL_MOD_DIR . '/' . $oModule->name;
        if (is_writable($moduleDir)) {
            require_once 'System.php';
            $success = System::rm(array('-r', $moduleDir));
        }

        //  de-register module
        if (isset($success) && $success == true) {
            $rm = DB_DataObject::factory($this->conf['table']['module']);
            $rm->get($input->moduleId);
            $ok = $rm->delete();
        }

        if (isset($ok) && $ok == true
                && isset($success) && $success == true) {
            SGL::raiseMsg('The module was successfully removed', false, SGL_MESSAGE_INFO);
        } else {
            SGL::raiseMsg($msg, false, SGL_MESSAGE_ERROR);
        }
    }

    function _cmd_uninstall(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        //  drop tables
        require_once SGL_CORE_DIR . '/Sql.php';
        $dbShortname = SGL_Sql::getDbShortnameFromType($this->conf['db']['type']);
        //  get all table defined in this module's schema
        $oModule = $this->da->getModuleById($input->moduleId);
        $dropFile =  SGL_MOD_DIR . '/' . $oModule->name . '/data/drop.'.$dbShortname.'.sql';
        if (is_file($dropFile)) {
            $sql = file_get_contents($dropFile);
            $res = SGL_Sql::execute($sql);
        }

        //  delete related navigation
        //  remove config keys

        SGL::raiseMsg('The module was successfully uninstalled', false,
            SGL_MESSAGE_INFO);
    }

    function _cmd_list(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $output->template = 'moduleList.html';
        $query = "SELECT * FROM {$this->conf['table']['module']} ORDER BY name";

        $limit = $_SESSION['aPrefs']['resPerPage'];
        $pagerOptions = array(
            'mode'     => 'Sliding',
            'delta'    => 3,
            'perPage'  => $limit,
            'spacesBeforeSeparator' => 0,
            'spacesAfterSeparator'  => 0,
            'curPageSpanPre'        => '<span class="currentPage">',
            'curPageSpanPost'       => '</span>',
        );
        $aPagedData = SGL_DB::getPagedData($this->dbh, $query, $pagerOptions);

        // if there are modules, determine whether installed
        if (count($aPagedData['data'])) {
            require_once SGL_CORE_DIR . '/Sql.php';
            foreach ($aPagedData['data'] as $k => $aModule) {
                $aPagedData['data'][$k]['isInstalled'] = $this->_isInstalled($aModule['name']);
            }
        }
        //  determine if pagination is required
        $output->aPagedData = $aPagedData;
        if (is_array($aPagedData['data']) && count($aPagedData['data'])) {
            $output->pager = ($aPagedData['totalItems'] <= $limit) ? false : true;
        }
        $output->addOnLoadEvent("switchRowColorOnHover()");
    }

    function _isInstalled($moduleName)
    {
        //  get installed tables to compare against
        static $aInstalledTables, $dbShortname;
        if (!isset($aInstalledTables)) {
            $aInstalledTables = $this->dbh->getListOf('tables');
            $dbShortname = SGL_Sql::getDbShortnameFromType($this->conf['db']['type']);
        }
        //  get all table defined in this module's schema
        $dataDir =  SGL_MOD_DIR . '/' . $moduleName . '/data';
        $schemaFile =  '/' . $dataDir . '/schema.'.$dbShortname.'.sql';
        //  Some modules, like export, don't have schema and don't need installing.
        //  is_file($dataDir) is for cases, on delete, where some web-writable files
        //  are deleted, but not all
        if (!is_file($schemaFile)) {
            return is_file($dataDir) ? true : false ;
        }
        $aTablesByModule = SGL_Sql::extractTableNamesFromSchemaFile($schemaFile);
        //  check to see tables in existing db correspond to those specified in schema
        foreach ($aTablesByModule as $tablename) {
            if (!in_array($tablename, $aInstalledTables)) {
                return false;
            }
        }
        return true;
    }
}
?>