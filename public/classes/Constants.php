<?php

class Constants
{
    /**
     * @var Smarty
     */
    private static $smarty;

    /**
     * @var Logger
     */
    private static $logger;

    /**
     * Constants constructor.
     */
    public static function init()
    {
        $dotenv = new \Dotenv\Dotenv($_SERVER['DOCUMENT_ROOT']);
        $dotenv->load();
        self::$smarty = new Smarty();
        self::$smarty->setCacheDir("public/temp/templates_c");
        self::$smarty->setTemplateDir("public/templates");
        self::$smarty = Logger::getLogger("Tieba Monitor");
    }

    /**
     * @return Smarty
     */
    public static function getSmarty()
    {
        return self::$smarty;
    }

    /**
     * @return Logger
     */
    public static function getLogger()
    {
        return self::$logger;
    }
}