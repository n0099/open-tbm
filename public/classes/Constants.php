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
     * @var MeekroDB
     */
    private static $mdb;

    /**
     * Constants constructor.
     */
    public static function init()
    {
        $dotenv = new \Dotenv\Dotenv($_SERVER['DOCUMENT_ROOT']);
        $dotenv->load();
        DB::$host = $_ENV['DATABASE_HOST'];
        DB::$dbName = $_ENV['DATABASE_NAME'];
        DB::$user = $_ENV['DATABASE_USER'];
        DB::$password = $_ENV['DATABASE_PASS'];
        self::$mdb = DB::getMDB();
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

    /**
     * @return MeekroDB
     */
    public static function getMDB()
    {
        return self::$mdb;
    }
}