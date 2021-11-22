<?php namespace nettonn\yii2filestorage\controllers;

use nettonn\yii2filestorage\actions\ThumbAction;
use yii\web\Controller;

class ThumbController extends Controller
{
    public function actions()
    {
        return [
            'get' => [
                'class' => ThumbAction::class,
            ],
        ];
    }

    public function verbs()
    {
        return [
            'get'  => ['GET', 'HEAD'],
        ];
    }
}
