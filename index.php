<?php
$document = new DOMDocument("1.0", "utf-8");
$document->loadHTMLFile(file_get_contents("http://tieba.baidu.com/f?kw=模拟城市"));
$document->getElementById("search_logo_large");