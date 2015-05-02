<?php

namespace ramshresh\yii2\galleryManager;

use yii\base\ErrorException;
use yii\base\Object;
use yii\helpers\Json;

class GalleryImage extends Object
{
    public $name;
    public $description;
    public $id;
    public $rank;
    /**
     * @var GalleryBehavior
     */
    protected $galleryBehavior;

    /**
     * @param GalleryBehavior $galleryBehavior
     * @param array           $props
     */
    function __construct(GalleryBehavior $galleryBehavior=null, array $props=null)
    {

        $this->galleryBehavior = $galleryBehavior;
        /*foreach($props as $prop=>$value){
            if($this->canGetProperty($prop))
                $this->$prop = $value;
        }*/

        $this->name = isset($props['name']) ? $props['name'] : '';
        $this->description = isset($props['description']) ? $props['description'] : '';
        $this->id = isset($props['id']) ? $props['id'] : '';
        $this->rank = isset($props['rank']) ? $props['rank'] : '';
    }

    /**
     * @param string $version
     *
     * @return string
     */
    public function getUrl($version)
    {
        return $this->galleryBehavior->getUrl($this->id, $version);
    }
}
