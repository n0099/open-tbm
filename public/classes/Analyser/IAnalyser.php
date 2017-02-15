<?php
namespace Analyser;

interface IAnalyser
{
    /**
     * 分析一个数据.
     * @param $data string
     * @return mixed
     */
    function analyse($data);
}