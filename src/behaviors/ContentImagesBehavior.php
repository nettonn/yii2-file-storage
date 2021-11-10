<?php namespace nettonn\yii2filestorage\behaviors;

use nettonn\yii2filestorage\ModuleTrait;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;


/**
 * Behavior attach uploaded FileModel images from content attributes to model
 * and later handle change thumb url
 */
class ContentImagesBehavior extends Behavior
{
    use ModuleTrait;

    public $contentAttributes = ['content'];

    public $imagesAttribute = 'content_images';

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
//            BaseActiveRecord::EVENT_INIT          => 'afterFind',
//            BaseActiveRecord::EVENT_AFTER_FIND    => 'afterFind',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
//            BaseActiveRecord::EVENT_AFTER_INSERT  => 'afterSave',
//            BaseActiveRecord::EVENT_AFTER_UPDATE  => 'afterSave',
//            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    public function beforeSave($event)
    {
        $owner = $this->owner;
        $imageIdsAttribute = $this->imagesAttribute.'_id';

        $ids = [];

        foreach($this->contentAttributes as $attribute) {
            $content = $owner->{$attribute};
            $ids = array_merge($ids, $this->getImageIdsFromContent($content));
        }

        $owner->{$imageIdsAttribute} = $ids;
    }

    protected function getImageIdsFromContent($content)
    {
        preg_match_all($this->getUrlPregPatternPart(), $content, $matches);

        if(isset($matches[1]))
            return $matches[1];
        return [];
    }

    protected function getUrlPregPatternPart()
    {
        $module = self::getModule();

        $pattern = '';

        $pattern .= str_replace($module->getWebroot(), '', $module->getPublicStoragePath()); // /files

        $pattern .= str_repeat('/\w+', $module->directoryLevel);

        $pattern .= '/(\d+)';

        $pattern .= '/[\w\-_]+\.\w+';

        return '~[\]?[\"\']'.$pattern.'[\]?[\"\']~';
    }
}