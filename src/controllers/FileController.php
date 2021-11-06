<?php namespace nettonn\yii2filestorage\controllers;

use Yii;
use nettonn\yii2filestorage\models\FileModel;
use yii\helpers\VarDumper;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

class FileController extends Controller
{
    public function actionIndex()
    {
        $ids = \Yii::$app->request->get('ids');

        if(!$ids) {
            return [];
        }
        if(!is_array($ids)) {
            $ids = array_map('intval', explode(',', $ids));
        }

        $query = FileModel::find()->where(['in', 'id', $ids])->orderBy('sort ASC');

        return $query->all();
    }

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

        return $model;
    }

    public function verbs()
    {
        return [
            'index'  => ['GET'],
            'create'  => ['POST'],
        ];
    }

}