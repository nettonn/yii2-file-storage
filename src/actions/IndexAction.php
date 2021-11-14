<?php namespace nettonn\yii2filestorage\actions;

use nettonn\yii2filestorage\models\FileModel;
use yii\base\Action;

class IndexAction extends Action
{
    public $checkAccess;

    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }

        $ids = \Yii::$app->getRequest()->get('ids');

        if(!$ids) {
            return [];
        }
        if(!is_array($ids)) {
            $ids = array_map('intval', explode(',', $ids));
        }

        $query = FileModel::find()->where(['in', 'id', $ids])->orderBy('sort ASC');

        return $query->all();
    }
}
