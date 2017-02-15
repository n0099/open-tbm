<?php
namespace Analyser;


abstract class AbstractHTMLAnalyser implements IAnalyser
{
    /**
     * 从一个URL地址中分析HTML数据.
     * @param $url string
     * @return mixed
     */
    function analyse($url) {
        \Constants::getLogger()->debug("Querying url $url");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $this->analyseHTML(curl_exec($curl));
    }

    abstract function analyseHTML($html);
}