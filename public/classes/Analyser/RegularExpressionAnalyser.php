<?php
namespace Analyser;


class RegularExpressionAnalyser implements IAnalyser
{
    private $regular;

    /**
     * RegularExpressionAnalyser constructor.
     * @param $regular
     */
    public function __construct($regular)
    {
        $this->regular = $regular;
    }

    /**
     * 分析一个正则表达式.
     * @param $data string
     * @return array
     */
    function analyse($data)
    {
        $ret = array();
        preg_match($this->regular, $data, $ret);
        return $ret;
    }
}