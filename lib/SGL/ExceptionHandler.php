<?php

class SGL_ExceptionHandler
{
    private static $instance = null;

    private function __construct()
    {
        set_exception_handler(array(__CLASS__, 'exceptionHandler'));
        ob_start();
    }

    public function __destruct()
    {
        restore_exception_handler();
    }

    public static function singleton()
    {
        if (!self::$instance) {
            self::$instance = new SGL_ExceptionHandler();
        }
        return self::$instance;
    }

    public static function exceptionHandler($exception)
    {
        ob_end_clean();
        if ($exception instanceof Exception) {
            SGL::logMessage($exception->getMessage(), $exception->getCode());
        }
        die('fatal error');
    }


}

?>