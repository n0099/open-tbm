<?php
require __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('UTC');
spl_autoload_register(function ($class_name) {
    include __DIR__ . '/public/classes/'. str_replace('\\', '/', $class_name) . '.php';
});
Constants::init();