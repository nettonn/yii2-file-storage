<?php namespace nettonn\yii2filestorage;

use Imagick;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Point;
use nettonn\yii2filestorage\models\FileModel;
use yii\imagine\Image;
use Yii;
use yii\base\InvalidParamException;
use yii\helpers\FileHelper;

class Module extends \yii\base\Module
{
    public $controllerNamespace = 'nettonn\yii2filestorage\controllers';

    public $originalImageMaxWidth = 1920;
    public $originalImageMaxHeight = 1920;

    public $fileModelTableName = '{{%file_model}}';

    public $deleteNotAttachedFileModelsAfter = 3600;

    /**
     * If change exists files may be lost
     * @var string
     */
    protected $_webroot = '@webroot';

    /**
     * If change exists files may be lost
     * @var string public setter getter
     */
    protected $_privateStoragePath = '@app/storage/files';

    /**
     * If change exists files may be lost
     * @var string public setter getter
     */
    protected $_publicStoragePath = '@webroot/files';

    /**
     * If change exists files may be lost
     * @var int
     */
    public $directoryLevel = 1;

    /**
     * If change exists files may be lost
     * @var string
     */
    public $salt = 'kagkjgkjg-asgljkgsadg-sadgklhieutkbn';

    public $imageExt = ['jpg', 'jpeg', 'gif', 'bmp', 'png'];

    /**
     * Variants of generated image thumbs - width, height, quality, watermark, adaptive
     * имя \w+
     * @var array
     */
    public $variants = [
        'thumb'=> [400, 300, 85, false, true],
        'normal' => [1280, 1280, 80, false, false],
        'original'=> [1920, 1920, 80, false, false],
//        'orgwm'=> [1920, 1920, 80, DOCROOT.'/media/img/watermark.png', false],
    ];

    public $defaultVariant = 'normal';

    public $useModelPathCache = true;

    /**
     * @var string
     */
    protected $watermark;

    public function init()
    {
        $this->setAliases(['@nettonn/yii2filestorage' => __DIR__]);
        parent::init();
    }

    public function generateImage($filename, $saveFilename, $toWidth, $toHeight, $adaptive = false, $quality = 80, $watermark = false)
    {
        if($adaptive) {
            $image = Image::thumbnail($filename, $toWidth, $toHeight, ManipulatorInterface::THUMBNAIL_OUTBOUND);
        } else {
            $image = Image::resize($filename, $toWidth, $toHeight);
        }
        if ($watermark) {
            $watermarkObj = Image::getImagine()->open(Yii::getAlias($watermark));
            $iSize = $image->getSize();
            $wSize = $watermarkObj->getSize();
            $image->paste($watermarkObj, new Point(($iSize->getWidth() - $wSize->getWidth())/2, ($iSize->getHeight() - $wSize->getHeight())/2));
        }

        if(class_exists('Imagick', false)) {
            $imagick = $image->getImagick();
            $imagick->stripImage();
            $imagick->setImageCompressionQuality($quality);
            $format = pathinfo($filename, \PATHINFO_EXTENSION);

            if (in_array($format, array('jpeg', 'jpg', 'pjpeg')))
            {
                $imagick->setSamplingFactors(array('2x2', '1x1', '1x1'));
                $profiles = $imagick->getImageProfiles("icc", true);
                $imagick->stripImage();

                if(!empty($profiles)) {
                    $imagick->profileImage('icc', $profiles['icc']);
                }

                $imagick->setInterlaceScheme(Imagick::INTERLACE_JPEG);
                $imagick->setColorspace(Imagick::COLORSPACE_SRGB);
            }
            elseif (in_array($format, array('png'))) {
                $imagick->setimagecompressionquality(75);
                $imagick->setcompressionquality(75);
            }
        }

        return $image->save($saveFilename, ['jpeg_quality' => $quality]);
    }

    public function setWebroot($path)
    {
        $this->_webroot = $path;
        $this->_webrootCached = null;
    }

    private $_webrootCached = null;

    public function getWebroot()
    {
        if(null === $this->_webrootCached)
            $this->_webrootCached = rtrim(Yii::getAlias($this->_webroot), '/');
        return $this->_webrootCached;
    }

    public function setPrivateStoragePath($path)
    {
        $this->_privateStoragePath = $path;
        $this->_privateStoragePathCached = null;
    }

    private $_privateStoragePathCached = null;

    public function getPrivateStoragePath()
    {
        if(null === $this->_privateStoragePathCached)
            $this->_privateStoragePathCached = rtrim(Yii::getAlias($this->_privateStoragePath), '/');
        return $this->_privateStoragePathCached;
    }

    public function setPublicStoragePath($path)
    {
        $this->_publicStoragePath = $path;
        $this->_publicStoragePathCached = null;
    }

    private $_publicStoragePathCached = null;

    public function getPublicStoragePath()
    {
        if(null === $this->_publicStoragePathCached)
            $this->_publicStoragePathCached = rtrim(Yii::getAlias($this->_publicStoragePath), '/');
        return $this->_publicStoragePathCached;
    }

    public function getPrivateToPublicPath($path)
    {
        if(strpos($path, $this->getPrivateStoragePath()) !== 0)
            throw new InvalidParamException('No such path in rules: '.$path);
        return $this->getPublicStoragePath().str_replace($this->getPrivateStoragePath(), '', $path);
    }

    public function getPublicToPrivatePath($path)
    {
        if(strpos($path, $this->getPublicStoragePath()) !== 0)
            throw new InvalidParamException('No such path in rules: '.$path);
        return $this->getPrivateStoragePath().str_replace($this->getPublicStoragePath(), '', $path);
    }

    public function removePublicPath($privatePath)
    {
        $thumbPath = $this->getPrivateToPublicPath($privatePath);
        if($thumbPath !== $this->getPublicStoragePath()
            && file_exists($thumbPath)
            && is_dir($thumbPath))
            FileHelper::removeDirectory($thumbPath);
    }

    public function removeThumbs($basename, $privatePath)
    {
        $thumbPath = $this->getPrivateToPublicPath($privatePath);

        if($thumbPath !== $this->getPublicStoragePath()
            && file_exists($thumbPath)
            && is_dir($thumbPath)) {
            $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);
            foreach(FileHelper::findFiles($thumbPath) as $one) {
                if(strpos(basename($one), $nameWithoutExt) === 0)
                    @unlink($one);
            }
        }
    }

    /**
     * Get new public filename for private filename
     * @param $filename
     * @param false $variant
     * @param true $relative
     * @return string|string[]
     * @throws \Exception
     */
    public function getThumb($filename, $variant = false, $relative = true)
    {
        if(!file_exists($filename))
            throw new InvalidParamException('File not exists '.$filename);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if(in_array($ext, $this->imageExt) && !isset($this->variants[$variant]))
            throw new InvalidParamException('Wrong variant for image: '.$variant);

        $newFilename = $this->getPublicFilename($filename, $variant);
        if($relative) {
            return str_replace($this->getWebroot(), '', $newFilename);
        }

        return $newFilename;
    }

    protected function getPublicFilename($filename, $variant = false)
    {
        $pathParts = pathinfo($filename);
        $path = $pathParts['dirname'];
        $publicPath = $this->getPrivateToPublicPath($path);

        $ext = $pathParts['extension'];
        $name = $pathParts['filename'];

        if ($variant) {
            $hash = $this->generateHash($filename, $variant);

            return $publicPath.DIRECTORY_SEPARATOR.$name.'-'.$variant.'-'.$hash.'.'.$ext;
        }

        return $publicPath.DIRECTORY_SEPARATOR.$name.'.'.$ext;
    }

    protected function generateHash($filename, $variant)
    {
        $id = basename(dirname($filename));

        return substr(md5($id.$this->salt.basename($filename).$this->salt.$variant), 0, 5);
    }

    public function generateFromUrl($url)
    {
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if(!$ext)
            throw new InvalidParamException('Invalid url');
        $ext = strtolower($ext);
        if(in_array($ext, $this->imageExt)) {
            return $this->generateImageFromUrl($url);
        }
        return $this->generateFileFromUrl($url);
    }

    protected function generateImageFromUrl($url)
    {
        preg_match('~^(.*)-(\w+)-(\w+)\.(\w+)$~', $url, $m);
        if(!$m) return false;
        list($all, $pathPart, $variant, $hash, $ext) = $m;

        if(!isset($this->variants[$variant])) return false;

        $filePath = $pathPart.'.'.$ext;
        if($hash !== $this->generateHash($filePath, $variant))
            return false;

        $newFilename = $this->getWebroot().$url;
        $fromPath = $this->getPublicToPrivatePath($newFilename);
        $fromPath = pathinfo($fromPath, PATHINFO_DIRNAME);

        $filename = $fromPath.DIRECTORY_SEPARATOR.basename($filePath);

        if(!file_exists($filename))
            return false;

        $newPath = pathinfo($newFilename, PATHINFO_DIRNAME);
        FileHelper::createDirectory($newPath);

        $width = $this->variants[$variant][0];
        $height = $this->variants[$variant][1];
        $quality = isset($this->variants[$variant][2]) ? $this->variants[$variant][2] : 90;
        $watermark = isset($this->variants[$variant][3]) ? $this->variants[$variant][3] : false;
        $adaptive = isset($this->variants[$variant][4]) ? $this->variants[$variant][4] : false;

        usleep(mt_rand(500, 3000)); // For hostings who not allow many generates at once

        $this->generateImage($filename, $newFilename, $width, $height, $adaptive, $quality, $watermark);
        return $newFilename;
    }

    protected function generateFileFromUrl($url)
    {
        preg_match('~^(.*)\.(\w+)$~', $url, $m);
        if(!$m) return false;
        list($all, $pathPart, $ext) = $m;

        $filePath = $pathPart.'.'.$ext;

        $newFilename = $this->getWebroot().$url;
        $fromPath = $this->getPublicToPrivatePath($newFilename);
        $fromPath = pathinfo($fromPath, PATHINFO_DIRNAME);

        $filename = $fromPath.DIRECTORY_SEPARATOR.basename($filePath);

        if(!file_exists($filename))
            return false;

        $newPath = pathinfo($newFilename, PATHINFO_DIRNAME);
        FileHelper::createDirectory($newPath);

        copy($filename, $newFilename);

        return $newFilename;

    }

    public function findOldNotAttachedFileModelsQuery()
    {
        return FileModel::find()
            ->andWhere(['or', ['link_type' => null], ['link_id' => null], ['link_attribute' => null]])
            ->andWhere(['<', 'updated_at', time()-$this->deleteNotAttachedFileModelsAfter]);
    }

    public function deleteOldNotAttachedFileModels()
    {
        foreach($this->findOldNotAttachedFileModelsQuery()->each() as $model) {
            $model->delete();
        }
    }
}
