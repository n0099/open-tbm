<?php
namespace Db;

class SyncHelper
{
    /**
     * @param $syncable ISyncable
     * @param $option string
     */
    public static function fetchSyncable(&$syncable, $option = "")
    {
        $syncable->loadData(\Constants::getMDB()->query("SELECT ".implode(",", $syncable->getDataToLoad())." FROM ".$syncable->getTableName()." ".$option));
    }

    /**
     * @param $syncable ISyncable
     * @param $param mixed
     */
    public static function uploadSyncable($syncable, $param = null)
    {
        if (is_null($param))
            \Constants::getMDB()->insertIgnore($syncable->getTableName(), $syncable->saveData());
        else
            \Constants::getMDB()->insertIgnore($syncable->getTableName(), $syncable->saveData($param));
    }
}