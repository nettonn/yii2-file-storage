<?php namespace nettonn\yii2filestorage\models;


use yii\db\ActiveQuery;

class FileModelQuery extends ActiveQuery
{
    public function onlyFirst()
    {
        $this->andWhere(['sort' => 1]);
        return $this;
    }
}