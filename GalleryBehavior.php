<?php

namespace ramshresh\yii2\galleryManager;

use common\modules\file_management\models\TempUploadedFile;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use yii\base\Behavior;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\imagine\Image;
use yii\web\UploadedFile;
use yii\web\YiiAsset;

/**
 * Behavior for adding gallery to any model.
 *
 * @author Bogdan Savluk <savluk.bogdan@gmail.com>
 *
 * @property string $galleryId
 */
class GalleryBehavior extends Behavior
{
    /**
     * @var string Type name assigned to model in image attachment action
     * @see     GalleryManagerAction::$types
     * @example $type = 'Post' where 'Post' is the model name
     */
    public $type;
    /**
     * @var ActiveRecord the owner of this behavior
     * @example $owner = Post where Post is the ActiveRecord with GalleryBehavior attached under public function behaviors()
     */
    public $owner;
    /**
     * Widget preview height
     * @var int
     */
    public $previewHeight = 200;
    /**
     * Widget preview width
     * @var int
     */
    public $previewWidth = 200;
    /**
     * Extension for saved images
     * @var string
     */
    public $extension;
    /**
     * Path to directory where to save uploaded images
     * @var string
     */
    public $directory;

    /**
     * Path to temporary directory where to save uploaded images
     * @var string
     */
    public $tempDirectory;

    /**
     * Directory Url, without trailing slash
     * @var string
     */
    public $url;

    /**
     * Directory Route, without trailing slash
     * example: for www.site.com/app/uploads/images/gallery/123/8/small.jpg
     * /uploads/images/gallery will be the route
     * It is to be used to generate Url Dynamically using @see \yii\helpers\Url
     * $this->url = Url::to([$this->routeToBaseDir]);
     *          OR
     * For static function use
     * $url = GalleryBehavior::getUrlStatic('/uploads/images/gallery'); that returns Url::to('/uploads/images/gallery');
     * The idea is to save the relative route in database
     * @var string
     */
    public $route;

    /**
     * Temporary Directory Url, without trailing slash
     * @var string
     */
    public $tempUrl;

    /**
     * Temporary Files
     * @var array
     */
    public $temporaryFiles = [];

    /**
     * @var array Functions to generate image versions
     * @note Be sure to not modify image passed to your version function,
     *       because it will be reused in all other versions,
     *       Before modification you should copy images as in examples below
     * @note 'preview' & 'original' versions names are reserved for image preview in widget
     *       and original image files, if it is required - you can override them
     * @example
     * [
     *  'small' => function ($img) {
     *      return $img
     *          ->copy()
     *          ->resize($img->getSize()->widen(200));
     *  },
     *  'medium' => function ($img) {
     *      $dstSize = $img->getSize();
     *      $maxWidth = 800;
     * ]
     */
    public $versions;
    /**
     * name of query param for modification time hash
     * to avoid using outdated version from cache - set it to false
     * @var string
     */
    public $timeHash = '_';

    /**
     * Used by GalleryManager
     * @var bool
     * @see GalleryManager::run
     */
    public $hasName = true;
    /**
     * Used by GalleryManager
     * @var bool
     * @see GalleryManager::run
     */
    public $hasDescription = true;

    public $modelClass='ramshresh\yii2\galleryManager\GalleryImageAr';
    /**
     * @var string Table name for saving gallery images meta information
     */
    public $tableName;// = '{{%gallery_image}}';
    protected $_galleryId;

    /**
     * @param ActiveRecord $owner
     */
    public function attach($owner)
    {
        parent::attach($owner);
        if (!isset($this->versions['original'])) {
            $this->versions['original'] = function ($image) {
                return $image;
            };
        }
        if (!isset($this->versions['preview'])) {
            $this->versions['preview'] = function ($originalImage) {
                /** @var ImageInterface $originalImage */
                return $originalImage
                    ->thumbnail(new Box($this->previewWidth, $this->previewHeight));
            };
        }
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_INSERT =>'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
        ];
    }

    public function afterSave(){

        foreach($this->temporaryFiles as $temp){
            try{

                $this->addImage($this->tempDirectory.DIRECTORY_SEPARATOR.$temp['temp_name'].'.'.$temp['extension']);
                //$this->addImage('/var/www/html/girc/dmis/uploads/images/temp'.'/'.$temp['temp_name'].'.'.$temp['extension']);
                return true;
            }catch(Exception $e){
                throw $e;
                return false;
            }
        }
    }

    public function beforeDelete()
    {
        $images = $this->getImages();
        foreach ($images as $image) {
            $this->deleteImage($image->id);
        }
        $dirPath = $this->directory .DIRECTORY_SEPARATOR . $this->getGalleryId();
        @rmdir($dirPath);
    }

    public function afterFind()
    {
        $this->_galleryId = $this->getGalleryId();
    }

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub

        // check for required parameter
        if(!$this->modelClass){
            throw new Exception('invalid configuration : modelClass must be set');
        }

        if(!$this->url && $this->route ){
            $this->url = Url::to([$this->route]);
        }

        $this->tableName=(new $this->modelClass)->tableName();
    }

    public function afterUpdate()
    {
        $galleryId = $this->getGalleryId();
        if ($this->_galleryId != $galleryId) {
            $dirPath1 = $this->directory . DIRECTORY_SEPARATOR . $this->_galleryId;
            $dirPath2 = $this->directory . DIRECTORY_SEPARATOR . $galleryId;
            rename($dirPath1, $dirPath2);
        }
    }

    protected $_images = [];

    /**
     * @return GalleryImage[]
     */
    public function getImages()
    {
        if (empty($this->_images)) {
            $imagesData=$this->getImagesData();
            foreach ($imagesData as $imageData) {
                $this->_images[] = new GalleryImage($this, $imageData);
            }
        }

        return $this->_images;
    }

    protected function getFileName($imageId, $version = 'original')
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [
                $this->getGalleryId(),
                $imageId,
                $version . '.' . $this->extension,
            ]
        );
    }

    public function getUrl($imageId, $version = 'original')
    {
        $path = $this->getFilePath($imageId, $version);

        if (!file_exists($path)) {
            return null;
        }

        if (!empty($this->timeHash)) {

            $time = filemtime($path);
            $suffix = '?' . $this->timeHash . '=' . crc32($time);
        } else {
            $suffix = '';
        }

        return $this->url . DIRECTORY_SEPARATOR . $this->getFileName($imageId, $version) . $suffix;
    }

    public function getFilePath($imageId, $version = 'original')
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->getFileName($imageId, $version);
    }

    /**
     * Replace existing image by specified file
     *
     * @param $imageId
     * @param $path
     */
    public function replaceImage($imageId, $path)
    {
        /** @var ImageInterface $originalImage */
        $this->createFolders($this->getFilePath($imageId, 'original'));

        $originalImage = Image::getImagine()->open($path);
        //save image in original size

        //create image preview for gallery manager
        foreach ($this->versions as $version => $fn) {
            /** @var Image $image */
            call_user_func($fn, $originalImage)
                ->save($this->getFilePath($imageId, $version));
        }
    }

    private function removeFile($fileName)
    {
        if (file_exists($fileName)) {
            @unlink($fileName);
        }
    }

    /**
     * Get Gallery Id
     *
     * @return mixed as string or integer
     * @throws Exception
     */
    public function getGalleryId()
    {
        $pk = $this->owner->getPrimaryKey();
        if (is_array($pk)) {
            throw new Exception('Composite pk not supported');
        } else {
            return $pk;
        }
    }

    private function createFolders($filePath)
    {
        //$parts = explode('/', $filePath);
        $parts = explode(DIRECTORY_SEPARATOR, $filePath);
        // skip file name
        $parts = array_slice($parts, 0, count($parts) - 1);
        $targetPath = implode(DIRECTORY_SEPARATOR, $parts);
        $path = realpath($targetPath);

        if (!$path) {
            mkdir($targetPath, 0777, true);
        }
    }

    /////////////////////////////// ========== Public Actions ============ ///////////////////////////
    public function deleteImage($imageId)
    {
        foreach ($this->versions as $version => $fn) {
            $filePath = $this->getFilePath($imageId, $version);
            $this->removeFile($filePath);
        }
        $filePath = $this->getFilePath($imageId, 'original');
        $parts = explode(DIRECTORY_SEPARATOR, $filePath);
        $parts = array_slice($parts, 0, count($parts) - 1);
        $dirPath = implode(DIRECTORY_SEPARATOR, $parts);
        @rmdir($dirPath);

        $db = \Yii::$app->db;
        $db->createCommand()
            ->delete(
                $this->tableName,
                ['id' => $imageId]
            )->execute();
    }

    public function deleteImages($imageIds)
    {
        foreach ($imageIds as $imageId) {
            $this->deleteImage($imageId);
        }
        if (!empty($this->_images)) {
            $removed = array_combine($imageIds, $imageIds);
            $this->_images = array_filter(
                $this->_images,
                function ($image) use (&$removed) {
                    return !isset($removed[$image->id]);
                }
            );
        }
    }

    public function addTempUploadedImage($id){

        $record = TempUploadedFile::findOne(['id'=>intval($id)]);
        $fileName = $this->tempDirectory.DIRECTORY_SEPARATOR.$record['temp_name'].'.'.$record['extension'];
        $data=Json::decode($record->data);

        $galleryImage= $this->addImage($fileName,$data);
        $record->delete();
        return $galleryImage;
    }

    public function addUploadedImage($uploadName,$data){
        if(!$uploadName){
            throw new Exception('upLoadName must be specified for file');
        }
        $imageFile = UploadedFile::getInstanceByName($uploadName);
        $fileName = $imageFile->tempName;
        try{
            $image = $this->addImage($fileName,$data);
        }catch (Exception $e){
            throw $e;
        }
    }

    public function addImage($fileName,$data=null)
    {
        //$galleryImageAr = new GalleryImageAr();
        $galleryImageAr = new $this->modelClass;
        $galleryImageAr->setGalleryBehavior($this);
        $galleryImageAr->setAttributes([
            'type' => $this->type,
            'ownerId' => (string)$this->getGalleryId()
        ]);
        if($data){
            // Setting extra data for example
            // if data = {"latitude":27.86767,"longitude":87.923324}
            // then galleryImageAr must have attribute latitude, longitude in table and model
            $galleryImageAr->setAttributes($data);
        }

        try{
            $savingImage = \Yii::$app->db->beginTransaction();
            $galleryImageAr->save();
            $galleryImageAr->rank = $galleryImageAr->id;
            if($galleryImageAr->save()){
                $this->replaceImage($galleryImageAr->id, $fileName);
                $this->removeFile($fileName);
                $savingImage->commit();
            }else{
                throw new Exception(Json::encode($galleryImageAr->errors));
            }

        }catch (Exception $e){
            $savingImage->rollBack();
            throw $e;
        }
        /*$db->createCommand()
            ->update(
                $this->tableName,
                ['rank' => $id],
                ['id' => $id]
            )->execute();*/
        //$this->replaceImage($id, $fileName);

        // $galleryImage = new GalleryImage($this, ['id' => $id]);
        // $galleryImageAr = new GalleryImageAr($this, ['id' => $id]);

        if (!empty($this->_images)) {
            //$this->_images[] = $galleryImage;
            $this->_images[] = $galleryImageAr;
        }
        return $galleryImageAr;
    }


    public function arrange($order)
    {
        $orders = [];
        $i = 0;
        foreach ($order as $k => $v) {
            if (!$v) {
                $order[$k] = $k;
            }
            $orders[] = $order[$k];
            $i++;
        }
        sort($orders);
        $i = 0;
        $res = [];
        foreach ($order as $k => $v) {
            $res[$k] = $orders[$i];
            \Yii::$app->db->createCommand()
                ->update(
                    $this->tableName,
                    ['rank' => $orders[$i]],
                    ['id' => $k]
                )->execute();

            $i++;
        }

        // todo: arrange images if presented
        return $order;
    }

    /**
     * @param array $imagesData
     *
     * @return GalleryImage[]
     */
    public function updateImagesData($imagesData)
    {
        $imageIds = array_keys($imagesData);
        $imagesToUpdate = [];
        if (!empty($this->_images)) {
            $selected = array_combine($imageIds, $imageIds);
            foreach ($this->_images as $img) {
                if (isset($selected[$img->id])) {
                    $imagesToUpdate[] = $selected[$img->id];
                }
            }
        } else {
            $rawImages = (new Query())
                //->select(['id', 'name', 'description', 'rank'])
                ->select('*')
                ->from($this->tableName)
                ->where(['type' => $this->type, 'ownerId' => (string)$this->getGalleryId()])
                ->andWhere(['in', 'id', $imageIds])
                ->orderBy(['rank' => 'asc'])
                ->all();
            foreach ($rawImages as $image) {
                $imagesToUpdate[] = new GalleryImage($this, $image);
            }
        }

        foreach ($imagesToUpdate as $image) {
            if (isset($imagesData[$image->id]['name'])) {
                $image->name = $imagesData[$image->id]['name'];
            }
            if (isset($imagesData[$image->id]['description'])) {
                $image->description = $imagesData[$image->id]['description'];
            }
            \Yii::$app->db->createCommand()
                ->update(
                    $this->tableName,
                    ['name' => $image->name, 'description' => $image->description],
                    ['id' => $image->id]
                )->execute();
        }
        return $imagesToUpdate;
    }

    public function getImagesData(){
        /*Alternative Style
        $imagesData = $query
                ->select(['id', 'name', 'description', 'rank'])
                ->from($this->tableName)
                ->where(['type' => $this->type, 'ownerId' => (string)$this->getGalleryId()])
                ->orderBy(['rank' => 'asc'])
                ->all();
         */
        //select(['id', 'name', 'description', 'rank'])
        $query=new ActiveQuery($this->modelClass);
        $imagesData=$query->select('*')
            ->where(['type' => $this->type, 'ownerId' => (string)$this->getGalleryId()])
            ->orderBy(['rank' => 'asc'])
            ->asArray()->all();
        return $imagesData;
    }
}
