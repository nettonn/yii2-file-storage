<?php namespace nettonn\yii2filestorage\models;


use nettonn\yii2filestorage\ModuleTrait;
use Yii;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\BaseStringHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;


/**
 * This is the model class for table "file_model".
 *
 * @property int $id
 * @property string $name
 * @property string $ext
 * @property string $mime
 * @property int $size
 * @property bool $is_image
 * @property string|null $link_type
 * @property int|null $link_id
 * @property string|null $link_attribute
 * @property int|null $sort
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property string $filename_cache
 */
class FileModel extends ActiveRecord
{
    use ModuleTrait;

    /**
     * @var UploadedFile
     */
    public $file;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return self::getModule()->fileModelTableName;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['file', 'file', 'maxFiles' => 1 , 'skipOnEmpty' => true],
        ];
    }

    public function fields()
    {
        $fields = parent::fields();

        $fields['file_thumb'] = function ($model) {
            return $model->getFileThumb();
        };
        $fields['image_thumbs'] = function ($model) {
            return $model->getImageThumbs();
        };

        return $fields;
    }

    public static function find()
    {
        return Yii::createObject(FileModelQuery::className(), [get_called_class()]);
    }

    public function beforeSave($insert)
    {
        $this->updated_at = time();
        if($insert) {
            $this->created_at = $this->updated_at;
        }

        if($this->file) {
            $this->mime = FileHelper::getMimeType($this->file->tempName);
            $exts = FileHelper::getExtensionsByMimeType($this->mime);
            $this->ext = end($exts);
            $this->name = $this->prepareName($this->file->baseName).'.'.$this->ext;
            $this->size = $this->file->size;
            $imageExt = self::getModule()->imageExt;
            $this->is_image = in_array($this->ext, $imageExt);
            // TODO check is image by validator
        }

        return parent::beforeSave($insert);
    }


    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if($this->file) {
            try {
                $filename = $this->getFilename();
                $path = pathinfo($filename, PATHINFO_DIRNAME);

                if(!file_exists($path) && !is_dir($path)) {
                    FileHelper::createDirectory($path);
                }

                $saved = $this->file->saveAs($filename);

                if(!$saved) {
                    $this->delete();
                    throw new ServerErrorHttpException('Error saving file model');
                }
            } catch (Exception $e) {
                $this->delete();
                throw new ServerErrorHttpException('Error saving file model');
            }

            $this->updateAttributes([
                'filename_cache' => $this->getFilename(),
            ]);
        } else {
            if(!file_exists($this->getFilename())) {
                $this->delete();
            }
        }
        // TODO check max image width height
        // TODO check file exists
        // TODO update meta
    }

    public function beforeDelete()
    {
        FileHelper::removeDirectory($this->getPrivateStoragePath());
        FileHelper::removeDirectory($this->getPublicStoragePath());

        return parent::beforeDelete();
    }

    public function getFilename($useCache = false)
    {
        if($useCache) {
            if($this->filename_cache && file_exists($this->filename_cache))
                return $this->filename_cache;
            $this->filename_cache = $this->getFilename();
            $this->updateAttributes(['filename_cache' => $this->filename_cache]);
            return $this->filename_cache;
        }

        return $this->getPrivateStoragePath().DIRECTORY_SEPARATOR.$this->name;
    }

    public function getModelPath()
    {
        $path = '';
        $directoryLevel = self::getModule()->directoryLevel;
        $hash = md5($this->id);
        if ($directoryLevel > 0) {
            for ($i = 0; $i < $directoryLevel; ++$i) {
                $subDirectory = substr($hash, $i + $i, 2);
                if($subDirectory == 'ad') // ad blockers
                    continue;

                if ($subDirectory !== false) {
                    $path .= DIRECTORY_SEPARATOR . $subDirectory;
                }
            }
        }
        return trim($path.DIRECTORY_SEPARATOR.$this->id, DIRECTORY_SEPARATOR);
    }

    public function getPrivateStoragePath()
    {
        return self::getModule()->getPrivateStoragePath() . DIRECTORY_SEPARATOR . $this->getModelPath();
    }

    public function getPublicStoragePath()
    {
        return self::getModule()->getPublicStoragePath() . DIRECTORY_SEPARATOR . $this->getModelPath();
    }

    public function getThumbs()
    {
        if($this->is_image) {
            return $this->getImageThumbs();
        }
        return $this->getFileThumb();
    }

    public function getFileThumb()
    {
        if($this->is_image)
            return null;
        return self::getModule()->getThumb($this->getFilename());
    }

    public function getImageThumbs()
    {
        if(!$this->is_image)
            return null;
        $variants = array_keys(self::getModule()->variants);
        $result = [];
        $filename = $this->getFilename();
        foreach($variants as $variant) {
            $result[$variant] = self::getModule()->getThumb($filename, $variant);
        }
        return $result;
    }

    protected function prepareName($name)
    {
        $name = Inflector::transliterate($name);
        $name = preg_replace('/[^a-zA-Z0-9=\s_—–-]+/u', '', $name);
        $name = preg_replace('/[=\s_—–-]+/u', '-', $name);
        return strtolower(trim($name, '-'));
    }
}