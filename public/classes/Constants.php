<?php

class Constants
{
    /**
     * @var Smarty
     */
    public static $_SMARTY;

    /**
     * Constants constructor.
     */
    public function __construct()
    {
        $dotenv = new \Dotenv\Dotenv($_SERVER['DOCUMENT_ROOT']);
        $dotenv->load();
        self::$_SMARTY = new Smarty();
        self::$_SMARTY->setCacheDir("public/temp/templates_c");
        self::$_SMARTY->setTemplateDir("public/templates");
    }
}