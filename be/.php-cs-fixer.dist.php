<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
    ])
    ->setFinder((new PhpCsFixer\Finder())
        ->in(__DIR__)
        ->ignoreVCSIgnored(true)
        ->ignoreDotFiles(false));
