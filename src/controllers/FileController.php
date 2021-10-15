<?php namespace nettonn\yii2filestorage\controllers;

use Yii;
use nettonn\yii2filestorage\models\FileModel;
use yii\base\InvalidParamException;
use yii\helpers\VarDumper;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

class FileController extends Controller
{
    public function actionCreate()
    {
        $model = new FileModel();

        $model->file = UploadedFile::getInstanceByName('file');

        if(!$model->file) {
            throw new BadRequestHttpException('No file to upload');
        }

        if(!$model->validate()) {
            throw new BadRequestHttpException('File is not valid: '.VarDumper::dumpAsString($model->getFirstErrors()));
        }

        if(!$model->save(false)) {
            throw new ServerErrorHttpException('Error saving model');
        }

//        Yii::$app->response->format = Response::FORMAT_RAW;
//        Yii::$app->response->headers->add('Content-Type', 'text/plain');
        return $model;
    }

    public function verbs()
    {
        return [
            'create'  => ['POST'],
        ];
    }

}