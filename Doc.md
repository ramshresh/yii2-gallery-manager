## Usage

Add migration to create table for images:

```php
class m150318_154933_gallery_ext
    extends ramshresh\yii2\galleryManager\migrations\m140930_003227_gallery_manager
{

}
```

Add GalleryBehavior to your model, and configure it, create folder for uploaded files.

```php
public function behaviors()
{
    return [
         'galleryBehavior' => [
             'class' => GalleryBehavior::className(),
             'type' => 'product',
             'extension' => 'jpg',
             'directory' => Yii::getAlias('@contentRoot') . '/images/product/gallery',
             'url' => Yii::getAlias('@web') . '/images/product/gallery',
             'versions' => [
                 'small' => function ($img) {
                     /** @var ImageInterface $img */
                     return $img
                         ->copy()
                         ->thumbnail(new Box(200, 200));
                 },
                 'medium' => function ($img) {
                     /** @var ImageInterface $img */
                     $dstSize = $img->getSize();
                     $maxWidth = 800;
                     if ($dstSize->getWidth() > $maxWidth) {
                         $dstSize = $dstSize->widen($maxWidth);
                     }
                     return $img
                         ->copy()
                         ->resize($dstSize);
                 },
             ]
         ]
    ];
}
```


Add GalleryManagerAction in controller somewhere in your application. Also on this step you can add some security checks for this action.

```php
public function actions()
{
    return [
       'galleryApi' => [
           'class' => GalleryManagerAction::className(),
           // mappings between type names and model classes (should be the same as in behaviour)
           'types => [
               'product' => Product::className()
           ]
       ],
    ];
}
```
        
Add ImageAttachmentWidget somewhere in you application, for example in editing from.

```php
if ($model->isNewRecord) {
    echo 'Can not upload images for new record';
} else {
    echo GalleryManager::widget(
        [
            'model' => $model,
            'behaviorName' => 'galleryBehavior',
            'apiRoute' => 'product/galleryApi'
        ]
    );
}
```
        
Done!
 
Now, you can use uploaded images from gallery like following:

```php
foreach($model->getBehavior('galleryBehavior')->getImages() as $image) {
    echo Html::img($image->getUrl('medium'));
}
```


