<?php namespace nettonn\yii2filestorage\behaviors;


use nettonn\yii2filestorage\models\FileModel;
use nettonn\yii2filestorage\ModuleTrait;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\validators\Validator;

class FileBehavior extends Behavior
{
    use ModuleTrait;

    public $attributes = [
        'images' => [
            'multiple' => true,
            'image' => true,
            'extensions' => ['jpg', 'jpeg', 'png'], // if image is true will use from module config
        ]
    ];

    protected $_related = [];

    protected $_values = [];

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
//            BaseActiveRecord::EVENT_INIT          => 'afterFind',
//            BaseActiveRecord::EVENT_AFTER_FIND    => 'afterFind',
//            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
//            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_INSERT  => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE  => 'afterSave',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
        ];
    }

    /**
     * @param BaseActiveRecord $owner
     */
    public function attach($owner)
    {
        parent::attach($owner);

        if(is_array($this->owner->getPrimaryKey())) {
            throw new InvalidConfigException('Composite primary keys not allowed');
        }

        $attributes = [];
        foreach ($this->attributes as $attribute => $options) {
            $options['multiple'] = isset($options['multiple']) ? $options['multiple'] : false;
            $options['image'] = isset($options['image']) ? $options['image'] : true;
            if($options['image']) {
                $options['extensions'] = self::getModule()->imageExt;
            } else {
                $options['extensions'] = isset($options['extensions']) ? $options['extensions'] : [];
            }

            $attributes[$attribute] = $options;

        }
        $this->attributes = $attributes;
    }

    /**
     * @param ModelEvent $event
     */
    public function beforeValidate($event)
    {
        /* @var $owner ActiveRecord */
        $owner = $this->owner;
        foreach ($this->attributes as $attribute => $options) {
//            $owner->validators[] = Validator::createValidator('safe', $owner, $attribute);
            $owner->validators[] = Validator::createValidator('safe', $owner, $attribute.'_id');
        }
    }

    /**
     * @param \yii\db\AfterSaveEvent $event
     */
    public function afterSave($event)
    {
        foreach($this->attributes as $attribute => $options) {
            if(!isset($this->_values[$attribute]))
                continue;

            $newIds = [];

            if(is_array($this->_values[$attribute])) {
                $newIds = $this->_values[$attribute];
            } else {
                $newIds[] = $this->_values[$attribute];
            }

            if($options['multiple']) {
                $currentIds = $this->getRelation($attribute)->select('id')->column();
            } else {
                if(count($newIds) >1) {
                    $newIds = array_slice($newIds, 0, 1);
                }

                $currentIds = [$this->getRelation($attribute)->select('id')->scalar()];
            }

            if($deleteIds = array_filter(array_diff($currentIds, $newIds))) {
                foreach(FileModel::find()->where(['in', 'id', $deleteIds])->all() as $model) {
                    $model->delete();
                }
            }

            $extensions = $this->attributes[$attribute]['extensions'];

            $sort = 1;
            foreach($newIds as $id) {
                $model = FileModel::find()->where(['id' => $id])->andWhere(['in', 'ext', $extensions])->one();
                if($model) {
                    $model->link_type = get_class($this->owner);
                    $model->link_id = $this->owner->id;
                    $model->link_attribute = $attribute;
                    $model->sort = $sort++;
                    if(!$model->save()) {
                        \Yii::error('Cant save FileModel with id '.$id.' '.VarDumper::dumpAsString($model->getFirstErrors()));
                    }
                } else {
                    \Yii::error('Cant find FileModel with id '.$id);
                }

            }
            unset($this->_related[$attribute]);
            unset($this->_values[$attribute]);
            unset($this->owner->{$attribute});
            if($options['multiple']) {
                $this->owner->populateRelation($attribute, $this->getRelation($attribute)->all());
            } else {
                $this->owner->populateRelation($attribute, $this->getRelation($attribute)->one());
            }
        }

    }

    public function beforeDelete($event)
    {
        $models = FileModel::find()
            ->where(['link_type' => get_class($this->owner), 'link_id' => $this->owner->id])
            ->all();
        foreach($models as $model) {
            $model->delete();
        }
    }

    /**
     * @param $attribute
     * @return string[]
     * @throws \yii\base\Exception
     */
    public function filesGet($attribute)
    {
        if(!$this->attributes[$attribute]['multiple']) {
            return [$this->fileGet($attribute)];
        }

        $useFilenameCache = self::getModule()->useFilenameCache;
        $filenames = [];
        foreach($this->owner->{$attribute} as $model) {
            $filenames[] = $model->getFilename($useFilenameCache);
        }

        return $filenames;
    }

    /**
     * @param $attribute
     * @return string|null
     * @throws \yii\base\Exception
     */
    public function fileGet($attribute)
    {
        $model = $this->owner->{$attribute};
        $useFilenameCache = self::getModule()->useFilenameCache;
        if(!$model)
            return null;
        if(is_array($model)) {
            $first = reset($model);
            return $first ? $first->getFilename($useFilenameCache) : null;
        }
        return $model->getFilename($useFilenameCache);
    }

    /**
     * return for all files [[['variant1' => 'path/to/variant1'], ['variant2' => 'path/to/variant2']], [...], ...]
     */
    public function filesThumbsGet(String $attribute, $variants = false, $relative = true)
    {
        $module = self::getModule();
        $result = [];
        foreach($this->filesGet($attribute) as $filename) {
            $thumbs = [];
            foreach($variants as $variant) {
                $thumbs[$variant] = $module->getThumb($filename, $variant, $relative);
            }
            $result[] = $thumbs;
        }
        return $result;
    }

    /**
     * return for all files [['path/to/variant1', 'path/to/variant2'], [...], ...]
     */
    public function filesThumbGet(String $attribute, String $variant, $relative = true)
    {
        $module = self::getModule();
        $result = [];
        foreach($this->filesGet($attribute) as $filename) {
            $result[] = $module->getThumb($filename, $variant, $relative);
        }
        return $result;
    }

    /**
     * return for one first file [['variant1' => 'path/to/variant1'], ['variant2' => 'path/to/variant2']]
     */
    public function fileThumbsGet(String $attribute, $variants = false, $relative = true)
    {
        $filename = $this->fileGet($attribute);
        if(!$filename)
            return null;
        $module = self::getModule();
        $thumbs = [];
        foreach($variants as $variant) {
            $thumbs[$variant] = $module->getThumb($filename, $variant, $relative);
        }
        return $thumbs;
    }

    /**
     * return for one first file 'path/to/variant'
     */
    public function fileThumbGet(String $attribute, $variant = false, $relative = true)
    {
        $filename = $this->fileGet($attribute);
        if(!$filename)
            return null;
        return self::getModule()->getThumb($filename, $variant, $relative);
    }

    protected function getRelation($attribute)
    {
        if(!isset($this->attributes[$attribute]))
            return false;

        if(!isset($this->_related[$attribute])) {
            $extensions = $this->attributes[$attribute]['extensions'];

            if($this->attributes[$attribute]['multiple']) {
                $this->_related[$attribute] = $this->owner
                    ->hasMany(FileModel::class, ['link_id' => 'id'])
                    ->andWhere(['link_attribute' => $attribute])
                    ->andWhere(['link_type' => get_class($this->owner)])
                    ->andWhere(['in', 'ext', $extensions])
                    ->orderBy('sort ASC');
            } else {
                $this->_related[$attribute] = $this->owner
                    ->hasOne(FileModel::class, ['link_id' => 'id'])
                    ->andWhere(['link_attribute' => $attribute])
                    ->andWhere(['link_type' => get_class($this->owner)])
                    ->andWhere(['in', 'ext', $extensions])
                    ->orderBy('sort ASC');
            }
        }
        return $this->_related[$attribute];
    }

    public function canGetProperty($name, $checkVars = true)
    {
        if(isset($this->attributes[$name])) {
            return true;
        }

        $attribute = $this->filterSuffix($name, '_id');
        if(isset($this->attributes[$attribute]))
            return true;

        return parent::canGetProperty($name, $checkVars);
    }

    public function canSetProperty($name, $checkVars = true)
    {
        $attribute = $this->filterSuffix($name, '_id');
        if(isset($this->attributes[$attribute]))
            return true;

        return parent::canSetProperty($name, $checkVars);
    }

    public function __set($name, $value)
    {
        if(isset($this->attributes[$this->filterSuffix($name, '_id')])) {
            $attribute = $this->filterSuffix($name, '_id');
            $this->_values[$attribute] = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    public function __get($name)
    {
        $relation = $this->getRelation($name);
        if($relation) {
            return $relation->findFor($name, $this->owner);
        }
        $attribute = $this->filterSuffix($name, '_id');
        if(isset($this->attributes[$attribute])) {
            if ($this->attributes[$attribute]['multiple']) {
                $attributeValue = $this->owner->{$attribute};
                return ArrayHelper::getColumn($attributeValue, 'id');
            } else {
                $attributeValue = $this->owner->{$attribute};
                return isset($attributeValue['id']) ? $attributeValue['id'] : null;
            }
        }
        return parent::__get($name);
    }

    public function __call($name, $params)
    {
        if(strlen($name) > 3 && 0 === strpos(strtolower($name), 'get')) {
            $attribute = lcfirst(substr($name, 3));
            $relation = $this->getRelation($attribute);
            if($relation) {
                return $relation;
            }
        }
        parent::__call($name, $params);
    }

    public function hasMethod($name)
    {
        if(strlen($name) > 3 && 0 === strpos(strtolower($name), 'get')) {
            $attribute = lcfirst(substr($name, 3));
            if(isset($this->attributes[$attribute]))
                return true;
        }
        return parent::hasMethod($name);
    }

    protected function filterSuffix($value, $suffix)
    {
        return substr($value, 0, strlen($value) - strlen($suffix));
    }
}