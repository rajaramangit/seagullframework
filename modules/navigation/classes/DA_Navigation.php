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
// | DA_Navigation.php                                                         |
// +---------------------------------------------------------------------------+
// | Authors:   Andrey Podshivalov  <planetaz@gmail.com>                       |
// +---------------------------------------------------------------------------+

require_once SGL_CORE_DIR . '/Delegator.php';
require_once SGL_CORE_DIR . '/NestedSet.php';

/**
 * Data access methods for the navigation module.
 *
 * @package Navigation
 * @author  Demian Turner <demian@phpkitchen.com>
 * @author  Andrey Podshivalov <demian@phpkitchen.com>
 */
class DA_Navigation extends SGL_Delegator
{
    var $_params  = array();
    var $_message = '';

    /**
     * Constructor - set default resources.
     *
     * @return DA_Navigation
     */
    function DA_Navigation()
    {
        $c             = &SGL_Config::singleton();
        $this->conf    = $c->getAll();
        $dataDefault   = &DA_Default::singleton();
        $this->add($dataDefault);
        $this->dbh     = $this->_getDb();
        $this->_params = array(
            'tableStructure' => array(
                'section_id'    => 'id',
                'title'         => 'title',
                'resource_uri'  => 'resource_uri',
                'perms'         => 'perms',
                'trans_id'      => 'trans_id',
                'root_id'       => 'rootid',
                'left_id'       => 'l',
                'right_id'      => 'r',
                'order_id'      => 'norder',
                'level_id'      => 'level',
                'parent_id'     => 'parent',
                'is_enabled'    => 'is_enabled',
                'is_static'     => 'is_static',
                'access_key'    => 'access_key',
                'rel'           => 'rel'
            ),
            'tableName'      => 'section',
            'lockTableName'  => 'table_lock',
            'sequenceName'   => 'section');
        $this->nestedSet = &new SGL_NestedSet($this->_params);

        //  detect if trans2 support required
        if ($this->conf['translation']['container'] == 'db') {
            $this->trans = &SGL_Translation::singleton('admin');
        }
    }

    function &_getDb()
    {
        $locator = &SGL_ServiceLocator::singleton();
        $dbh = $locator->get('DB');
        if (!$dbh) {
            $dbh = & SGL_DB::singleton();
            $locator->register('DB', $dbh);
        }
        return $dbh;
    }

    /**
     * Returns a singleton DA_Navigation instance.
     *
     * @access  public
     * @static
     * @return  DA_Navigation reference to DA_Navigation object
     */
    function &singleton()
    {
        static $instance;

        // If the instance is not there, create one
        if (!isset($instance)) {
            $instance = new DA_Navigation();
        }
        return $instance;
    }

    /**
     * Returns sections from parent section.
     *
     * @access  public
     *
     * @param   int $sectionId  Parent section id
     * @return  array
     */
   function getSectionsFromParent($sectionId = 0)
    {
        $query = "
            SELECT * FROM {$this->conf['table']['section']}
            WHERE parent_id = " . $sectionId . '
            ORDER BY order_id';

        $result = $this->dbh->query($query);
        if (DB::isError($result, DB_ERROR_NOSUCHTABLE)) {
            SGL::raiseError('The database exists, but does not appear to have any tables,
                please delete the config file from the var directory and try the install again',
                SGL_ERROR_DBFAILURE, PEAR_ERROR_DIE);
        }
        if (DB::isError($result)) {
            SGL::raiseError('Cannot connect to DB, check your credentials, exiting ...',
                SGL_ERROR_DBFAILURE, PEAR_ERROR_DIE);
        }
        return $result;
    }

    /**
     * Returns section by given id.
     *
     * @access  public
     *
     * @param   int $sectionId
     * @return  array
     */
    function getSectionById($sectionId = null)
    {
        $section = array();

        //  get raw section
        $section = $this->getRawSectionById($sectionId);

        //  passing a non-existent section id results in null or false $section
        if ($section) {

            //  setup article type, dropdowns built in display()
            if (preg_match('/(uriAlias:)([0-9]+:)(.*)/', $section['resource_uri'], $aMatches)) {
                $section['resource_uri']     = $aMatches[3];
                $section['uri_alias_enable'] = $uriAlias = true;
            }
            if (preg_match("@^publisher/wikiscrape/url@", $section['resource_uri'])) {
                $aElems  = explode('/', $section['resource_uri']);
                $wikiUrl = array_pop($aElems);
                $section['resource_uri'] = urldecode($wikiUrl);
                $section['uriType']      = 'wiki';

            } elseif (preg_match('/^uriExternal:(.*)$/', $section['resource_uri'], $aUri)) {
                $section['resource_uri'] = $aUri[1];
                $section['uriType']      = 'uriExternal';

            } elseif (preg_match('/^uriAddon:([^:]*):(.*)$/', $section['resource_uri'], $aUri)) {
                $section['addon']   = $aUri[1];
                $section['addonParams'] = $aUri[2];
                $section['uriType'] = 'uriAddon';

            } elseif (preg_match('/^uriNode:(.*)$/', $section['resource_uri'], $aUri)) {
                $section['uri_node'] = $aUri[1];
                $section['uriType']  = 'uriNode';

            } elseif ('uriEmpty:' == $section['resource_uri']) {
                $section['uriType'] = 'uriEmpty';

            } else {
                $section['uriType'] = ($section['is_static']) ? 'static' : 'dynamic';

                //  parse url details
                $parsed = SGL_Url::parseResourceUri($section['resource_uri']);
                $section = array_merge($section, $parsed);

                //  adjust friendly mgr name to class filename
                $c = &SGL_Config::singleton();
                $moduleConf = $c->load(SGL_MOD_DIR . '/' . $parsed['module'] . '/conf.ini', true);
                $c->merge($moduleConf);
                $className  = SGL_Inflector::getManagerNameFromSimplifiedName($section['manager']);
                if ($className) {
                    $section['manager'] = $className . '.php';
                } else {
                    SGL::raiseMsg('Manager was not found', true, SGL_MESSAGE_WARNING);
                }

                //  represent additional params as string
                if (array_key_exists('parsed_params', $parsed) && count($parsed['parsed_params'])) {
                    foreach ($parsed['parsed_params'] as $k => $v) {
                        $ret[] = $k . '/' . $v;
                    }
                    $section['add_params'] = implode('/', $ret);
                } else {
                    $section['add_params'] = null;
                }
                //  deal with static articles
                if ($section['is_static'] && $this->moduleIsRegistered('publisher')) {
                    if (isset($parsed['parsed_params'])) {
                        $section['staticArticleId'] = $parsed['parsed_params']['frmArticleID'];
                    }
                    $section['add_params'] = '';
                }
                //  split off anchor if exists
                if (stristr($section['resource_uri'], '#')) {
                    list(,$anchor) = split("#", $section['resource_uri']);
                    $section['anchor'] = $anchor;
                }
            }
            $section['uri_alias'] = $this->getAliasBySectionId($section['section_id']);
        }
        return $section;
    }

    /**
     * Returns raw section by given id.
     *
     * @access  public
     *
     * @param   int $sectionId
     * @return  array
     */
    function getRawSectionById($sectionId)
    {
        return $this->nestedSet->getNode($sectionId);
    }

    /**
     * Moves section.
     *
     * @access  public
     *
     * @param   int $sectionId
     * @param   int $targedId
     * @param   string $direction BE | AF
     */
    function moveSection($sectionId = 0, $targetId = 0, $direction = null)
    {
        $this->nestedSet->moveTree($sectionId, $targetId, $direction);
    }

    /**
     * Deletes section by given id.
     *
     * @access  public
     *
     * @param   int $sectionId
     */
    function deleteSectionById($sectionId = null)
    {
        //  deleting parent nodes automatically deletes chilren nodes, but user
        //  might have checked child nodes for deletion, in which case deleteNode()
        //  would try to delete nodes that no longer exist, after parent deletion,
        //  and therefore error, so test first to make sure they're still around
        if ($section = $this->nestedSet->getNode($sectionId)){

            //  remove translations
            if ($this->conf['translation']['container'] == 'db') {
                $this->trans->remove($section['trans_id'], 'nav');
            }

            //  remove section
            $this->nestedSet->deleteNode($sectionId);

            //  remove alias
            $this->deleteAliasBySectionId($sectionId);
        }
    }

    /**
     * Returns all sections.
     *
     * @access  public
     *
     * @return  array
     */
    function getSectionTree()
    {
        $this->nestedSet->setImage('folder', 'images/imagesAlt2/file.png');
        $sectionNodes = $this->nestedSet->getTree();

        //  fetch translations title
        if ($this->conf['translation']['container'] == 'db') {
            $lang          = SGL_Translation::getLangID();
            $aTranslations = SGL_Translation::getTranslations('nav', $lang);
            foreach ($sectionNodes as $k => $aValues) {
                if ($aValues['trans_id'] && array_key_exists($aValues['trans_id'], $aTranslations)) {
                    $sectionNodes[$k]['title'] = $aTranslations[$aValues['trans_id']];
                }
            }
        }

        //  remove first element of array which serves as a 'no section' fk
        //  for joins from the block_assignment table
        unset($sectionNodes[0]);

        $this->nestedSet->addImages($sectionNodes);
        return $sectionNodes;
    }

    /**
     * Returns sections are prepared for select.
     *
     * @access  public
     *
     * @return  array
     */
    function getSectionsForSelect()
    {
        $aTranslations     = array();
        $aSections         = array();
        $sectionNodesArray = $this->nestedSet->getTree();
        if ($this->conf['translation']['container'] == 'db') {
            $lang          = SGL_Translation::getLangID();
            $aTranslations = SGL_Translation::getTranslations('nav', $lang);
        }
        foreach ($sectionNodesArray as $k => $sectionNode) {
            if ($sectionNode['trans_id']
                    && array_key_exists($sectionNode['trans_id'], $aTranslations)) {
                $sectionNode['title'] = $aTranslations[$sectionNode['trans_id']];
            }
            $spacer = str_repeat('&nbsp;&nbsp;', $sectionNode['level_id']-1);
            $aSections[$sectionNode['section_id']] = $spacer . $sectionNode['title'];
        }
        unset($aSections[0]);
        return $aSections;
    }

    /**
     * Adds new section.
     *
     * @access  public
     *
     * @param   array $section
     * @return  boolean true | false
     */
    function addSection(&$section)
    {
        $this->_prepareSection($section);

        //  prepare resource_uri string for alias format
        if (!empty($section['uri_alias'])) {
            $nextAliasId = $this->dbh->nextId($this->conf['table']['uri_alias']);
            $section['resource_uri'] = 'uriAlias:' . $nextAliasId .':' . $section['resource_uri'];
        }

        //  fetch next id
        $sectionNextId = $this->dbh->nextID($this->conf['table']['section']) + 1;

        //  add translations
        if ($this->conf['translation']['container'] == 'db') {
            $lang = SGL_Translation::getFallbackLangID();
            $ok = $this->trans->add($sectionNextId, 'nav', array($lang => $section['title']));
        }

        //  set translation id for nav title
        $section['trans_id'] = $sectionNextId;

        if ($section['parent_id'] == 0) {    //  they want a root node
            $nodeId = $this->nestedSet->createRootNode($section);
        } elseif ((int)$section['parent_id'] > 0) { //    they want a sub node
            $nodeId = $this->nestedSet->createSubNode($section['parent_id'], $section);
        } else { //  error
            return false;
        }
        $this->_message = "Section successfully added";

        //  deal with potential alias
        if (!empty($section['uri_alias'])) {
            $aliasName = SGL_String::dirify($section['uri_alias']);
            $target = $nodeId;
            $ok = $this->addUriAlias($nextAliasId, $aliasName, $target);
            if (PEAR::isError($ok)) {
                $this->_message .= ' but alias creation failed as there can be no duplicates';
            }
        }
        return true;
    }

    /**
     * Updates section.
     *
     * @access  public
     *
     * @param   array $section
     * @return  boolean true | false
     */
    function updateSection(&$section)
    {
        $this->_prepareSection($section);

        //  prepare resource_uri string for alias format
        if (!empty($section['uri_alias'])) {
            $aliasId = $this->getAliasIdBySectionId($section['section_id']);

            if (is_null($aliasId)) {
                $aliasId = $this->dbh->nextId($this->conf['table']['uri_alias']);
                $aliasName = SGL_String::dirify($section['uri_alias']);
                $this->addUriAlias($aliasId, $aliasName, $section['section_id']);
            }
            $section['resource_uri'] = 'uriAlias:' . $aliasId.':'.$section['resource_uri'];
        }

        //  update translations
        if ($this->conf['translation']['container'] == 'db') {
            if (strcmp($section['title'], $section['title_original']) !== 0) {
                if ($section['trans_id']) {
                    $ok = $this->trans->add($section['trans_id'], 'nav', array($section['lang'] => $section['title']));
                }
                if ($section['lang'] != SGL_Translation::getFallbackLangID() && !empty($section['title_original'])) {
                    $section['title'] = $section['title_original'];
                }
            }
        }

        //  attempt to update section values
        if (!$parentId = $this->nestedSet->updateNode($section['section_id'], $section)) {
            return false;
        }

        //  If changing activation status, we need to enable/disable this node's children too
        if (($section['is_enabled'] != $section['is_enabled_original'])){
            $children = $this->nestedSet->getSubBranch($section['section_id']);
            if ($children) {
                foreach ($children as $child){

                    //  change the child's is_enabled status to that of its parent
                    if (!$this->nestedSet->updateNode($child['section_id'], array('is_enabled' => $section['is_enabled']))) {
                        return false;
                    }
                }
            }
        }

        //  move node if needed
        switch ($section['parent_id']) {
        case $section['parent_id_original']:
            //  usual case, no change => do nothing
            $this->_message = 'Section details successfully updated';
            break;

        case $section['section_id']:
            //  cannot be parent to self => display user error
            $this->_message = 'Section details updated, no data changed';
            break;

        case 0:
            //  move the section, make it into a root node, just above its own root
            $thisNode = $this->nestedSet->getNode($section['section_id']);
            $moveNode = $this->nestedSet->moveTree($section['section_id'], $thisNode['root_id'], 'BE');
            $this->_message = 'Section details successfully updated';
            break;

        default:
            //  move the section under the new parent
            $moveNode = $this->nestedSet->moveTree($section['section_id'], $section['parent_id'], 'SUB');
            $this->_message = 'Section details successfully updated';
        }
        //  deal with potential alias
        if (!empty($section['uri_alias_enable'])) {
            $aliasName = SGL_String::dirify($section['uri_alias']);
            $ok = $this->updateUriAlias($aliasName, $section['section_id']);
            if (PEAR::isError($ok)) {
                $this->_message .= ' but alias creation failed as there can be no duplicates';
            }
        }
        return true;
    }

    /**
     * Prepares section for insert or update operations.
     *
     * @access  private
     *
     * @param   array $section
     */
    function _prepareSection(&$section)
    {
        $separator = '/'; // can be configurable later

        //  if sectionType = static, append articleId, else build section url
        $section['is_static'] = 0;
        switch ($section['uriType']) {
        case 'static':
            $section['is_static'] = 1;
            $section['resource_uri'] =  'publisher/articleview/frmArticleID/' .
                $section['staticArticleId'] . '/';
            break;

        case 'wiki':
            $string = 'publisher/wikiscrape/url/' . urlencode($section['resource_uri']);
            $section['resource_uri'] = $string;
            break;

        case 'uriExternal':
            $string = 'uriExternal:' . $section['resource_uri'];
            $section['resource_uri'] = $string;
            break;

        case 'uriNode':
            $string = 'uriNode:' . $section['uri_node'];
            $section['resource_uri'] = $string;
            break;

        case 'uriEmpty':
            $string = 'uriEmpty:';
            $section['resource_uri'] = $string;
            break;

        case 'uriAddon':
            $string = 'uriAddon:' . $section['addon'] . ':' . @serialize($input->aParams);
            $section['resource_uri'] = $string;
            break;

        case 'dynamic':

            //  strip extension and 'Mgr'
            $simplifiedMgrName = SGL_Inflector::getSimplifiedNameFromManagerName($section['manager']);
            $actionPair = (!(empty($section['actionMapping'])) && ($section['actionMapping'] != 'none'))
                ? 'action' . $separator . $section['actionMapping'] . $separator
                : '';

            $section['resource_uri'] =
                $section['module'] . $separator .
                $simplifiedMgrName . $separator .
                $actionPair;
            break;
        }

        //  deal with additional params
        if (!(empty($section['add_params']))) {

            //  handle params abstractly to later accomodate traditional urls
            //  also strip blank array elements caused by input like '/foo/bar/'
            $params = array_filter(explode('/', $section['add_params']), 'strlen');
            $section['resource_uri'] .= implode($separator, $params);
        }

        //  add anchor if necessary
        if (!(empty($section['anchor']))) {
            $section['resource_uri'] .= '#' . $section['anchor'];
        }

        //  remove trailing slash/ampersand if one is present
        if ($section['uriType'] != 'uriExternal' && substr($section['resource_uri'], -1) == $separator) {
            $section['resource_uri'] = substr($section['resource_uri'], 0, -1);
        }
    }

    /**
     * Returns message.
     *
     * @access  private
     *
     * @return  string
     */
    function getMessage()
    {
        return $this->_message;
    }
}
?>