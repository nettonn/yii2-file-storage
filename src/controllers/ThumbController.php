<?php namespace nettonn\yii2filestorage\controllers;


use nettonn\yii2filestorage\ModuleTrait;
use Yii;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class ThumbController extends Controller
{
    use ModuleTrait;

    public function actionGet()
    {
        try {
            $filename = self::getModule()->generateFromUrl(Yii::$app->getRequest()->getUrl());
        } catch(\Exception $e) {
            sleep(1);
            return $this->redirect(Yii::$app->getRequest()->getUrl());
        }

        if(!$filename)
            throw new NotFoundHttpException();

        return Yii::$app->getResponse()->sendFile($filename, null, [
            'mimeType'=> FileHelper::getMimeType($filename),
            'inline'=>true,
        ]);
    }
}