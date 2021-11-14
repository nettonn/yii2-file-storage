<?php namespace nettonn\yii2filestorage\controllers;

use nettonn\yii2filestorage\actions\CreateAction;
use nettonn\yii2filestorage\actions\IndexAction;
use nettonn\yii2filestorage\Module;
use Yii;
use nettonn\yii2filestorage\models\FileModel;
use yii\helpers\VarDumper;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

class FileController extends Controller
{
    public function actions()
    {
        return [
            'index' => [
                'class' => IndexAction::class,
            ],
            'create' => [
                'class' => CreateAction::class,
            ],
            'create-image' => [
                'class' => CreateAction::class,
                'onlyImage' => true,
            ],
        ];
    }


    public function verbs()
    {
        return [
            'index'  => ['GET'],
            'create'  => ['POST'],
            'create-image'  => ['POST'],
        ];
    }

}
