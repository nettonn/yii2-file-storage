<?php

if(!function_exists('thumb')) {
    function thumb($filename, $variant = false, $relative = true) {
        static $module;
        if(null === $module) {
            $module = \nettonn\yii2filestorage\Module::getInstance();
        }

        return $module->getThumb($filename, $variant, $relative);
    }
}