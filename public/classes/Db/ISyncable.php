<?php
namespace Db;

interface ISyncable {
    /**
     * 获得需要被加载的表.
     * @return string
     */
    public function getTableName();

    /**
     * 获得需要被加载的数据.
     * @return array
     */
    public function getDataToLoad();

    /**
     * 加载数据.
     * @param $results array
     * @return void
     */
    public function loadData($results);

    /**
     * 将数据保存.
     * @return array
     */
    public function saveData();
}