<?php


namespace Db;


use PDO;
use PDOStatement;

class Db
{
    private $dbuser = "root";
    private $dbpass = "123456";
    private $dbname = "";
    private $dsn = "mysql:host=localhost";
    private $dboptions = array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY
    );
    private $pdo;

    /**
     * Db constructor.
     */
    public function __construct()
    {
        $this->dbuser = $_ENV['DATABASE_USER'];
        $this->dbpass = $_ENV['DATABASE_PASS'];
        $this->dbname = $_ENV['DATABASE_NAME'];;
        $this->pdo=new PDO($this->dsn.";dbname=$this->dbname", $this->dbuser, $this->dbpass, $this->dboptions);
    }

    /**
     * Get created PDO.
     * @return PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Insert data into a table.
     * @param $table string
     * @param $data array
     * @return int
     */
    public function insert($table, $data)
    {
        foreach ($data as $column => $value)
            $this->getPdo()->exec("INSERT INTO $table ($column) VALUES ($value)");
    }

    public function createTable($name)
    {

    }

    /**
     * Query a SQL statement.
     * @param string $statement a SQL statement
     * @return PDOStatement a query result
     */
    public function query($statement)
    {
        return $this->pdo->query($statement);
    }
}