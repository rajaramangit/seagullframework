<?php
require_once dirname(__FILE__) . '/lib/SGL/ParamHandler.php';

class SGL_Config
{
    var $aProps = array();
    
    function &singleton()
    {
        static $instance;
        if (!isset($instance)) {
            $class = __CLASS__;
            $instance = new $class();
        }
        return $instance;
    }
    
    function get($key)
    {
        if (is_array($key)) {
            $key1 = key($key);
            $key2 = $key[$key1];
            return $this->aProps[$key1][$key2];
        } else {
            return $this->aProps[$key];
        }
    }
    
    function set($key, $value)
    {
        if (is_array($this->aProps[$key]) && is_array($value)) {
            $key2 = key($value);
            $this->aProps[$key][$key2] = $value[$key2];
        } else {
            $this->aProps[$key] = $value;
        }
    }
    
    /**
     * Return an array of all Config properties.
     *
     * @return array
     */
    function getAll()
    {
        return $this->aProps;
    }
    
    function load($file)
    {
        $ph = &SGL_ParamHandler::singleton($file);
        $data = $ph->read();
        if ($data !== false) {
            $this->aProps = $data;
        } else {
            return SGL::raiseError('Problem reading config file', 
                SGL_ERROR_INVALIDFILEPERMS);    
        }
        return $this->getAll();
    }
    
    function save($file)
    {
        $ph = &SGL_ParamHandler::singleton($file);
        return $ph->write($this->aProps);
    }
    
    function merge($aConf)
    {
        $this->aProps = array_merge_recursive($this->aProps, $aConf); 
    }
}