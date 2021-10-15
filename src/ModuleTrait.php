<?php namespace nettonn\yii2filestorage;

use Yii;
use yii\base\Exception;

trait ModuleTrait
{
    /**
     * @var null|Module
     */
    private static $_module = null;

    /**
     * @return null|Module
     * @throws Exception
     */
    protected static function getModule()
    {
        if(static::$_module === null) {
            static::$_module = Module::getInstance();
        }
        if (static::$_module === null) {
            static::$_module = Yii::$app->getModule('file-storage');
        }

        if (!static::$_module) {
            throw new Exception("Yii2 File Storage module is not configured");
        }

        return static::$_module;
    }
}