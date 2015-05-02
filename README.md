yii2-gallery-manager
====================
yii2-gallery-manager

Features
------------
1. AJAX image upload
2. Optional name and description for each image
3. Possibility to arrange images in gallery
4. Ability to generate few versions for each image with different configurations
5. Drag & Drop

Decencies
------------
1. Yii2
2. Twitter bootstrap assets (version 3)
3. Imagine library
4. JQuery UI (included with Yii)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist ramshresh/yii2-gallery-manager "*"
```

or add

```
"ramshresh/yii2-gallery-manager": "*"
```

to the require section of your `composer.json` file.

Optional
-----------
The next step is just for initial development, skip it if you directly publish the extension on packagist.org
Add the newly created repo to your composer.json.

"repositories":[
    {
        "type": "git",
        "url": "https://github.com/ramshresh/yii2-gallery-manager.git"
    }
]


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
<?= \ramshresh\yii2\galleryManager\AutoloadExample::widget(); ?>```