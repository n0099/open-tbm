<?php

namespace App\Eloquent;

/**
 * Trait InsertOnDuplicateKey
 *
 * Overriding \Yadakhov\InsertOnDuplicateKey methods to adapted for non-static calling.
 *
 * @package App\Tieba\Eloquent
 */
trait InsertOnDuplicateKey
{
    use \Yadakhov\InsertOnDuplicateKey;

    /**
     * Chunk big insert data to prevent SQL General error: 1390 Prepared statement contains too many placeholders
     *
     * @param array $data
     * @param array $updateColumns
     * @param int $chunkSize
     */
    public function chunkInsertOnDuplicate(array $data, $updateColumns, int $chunkSize)
    {
        foreach (array_chunk($data, $chunkSize) as $chunkData) {
            $this->insertOnDuplicateKey($chunkData, $updateColumns);
        }
    }

    public function insertOnDuplicateKey(array $data, array $updateColumns = null)
    {
        if (empty($data)) {
            return false;
        }

        // Case where $data is not an array of arrays.
        if (!isset($data[0])) {
            $data = [$data];
        }

        $sql = $this->buildInsertOnDuplicateSql($data, $updateColumns);

        $data = static::inLineArray($data);

        return $this->getConnection()->affectingStatement($sql, $data);
    }
    
    public function insertIgnore(array $data)
    {
        if (empty($data)) {
            return false;
        }

        // Case where $data is not an array of arrays.
        if (!isset($data[0])) {
            $data = [$data];
        }

        $sql = $this->buildInsertIgnoreSql($data);

        $data = static::inLineArray($data);

        return $this->getConnection()->affectingStatement($sql, $data);
    }
    
    public function replace(array $data)
    {
        if (empty($data)) {
            return false;
        }

        // Case where $data is not an array of arrays.
        if (!isset($data[0])) {
            $data = [$data];
        }

        $sql = static::buildReplaceSql($data);

        $data = static::inLineArray($data);

        return $this->getConnection()->affectingStatement($sql, $data);
    }
    
    protected function buildInsertOnDuplicateSql(array $data, array $updateColumns = null)
    {
        $first = static::getFirstRow($data);

        $sql  = 'INSERT INTO `' . $this->getConnection()->getTablePrefix() . $this->getTable() . '`(' . static::getColumnList($first) . ') VALUES' . PHP_EOL;
        $sql .=  static::buildQuestionMarks($data) . PHP_EOL;
        $sql .= 'ON DUPLICATE KEY UPDATE ';

        if (empty($updateColumns)) {
            $sql .= static::buildValuesList(array_keys($first));
        } else {
            $sql .= static::buildValuesList($updateColumns);
        }

        return $sql;
    }
    
    protected function buildInsertIgnoreSql(array $data)
    {
        $first = static::getFirstRow($data);

        $sql  = 'INSERT IGNORE INTO `' . $this->getConnection()->getTablePrefix() . $this->getTable() . '`(' . static::getColumnList($first) . ') VALUES' . PHP_EOL;
        $sql .=  static::buildQuestionMarks($data);

        return $sql;
    }
    
    protected function buildReplaceSql(array $data)
    {
        $first = static::getFirstRow($data);

        $sql  = 'REPLACE INTO `' . $this->getConnection()->getTablePrefix() . $this->getTable() . '`(' . static::getColumnList($first) . ') VALUES' . PHP_EOL;
        $sql .=  static::buildQuestionMarks($data);

        return $sql;
    }
}
