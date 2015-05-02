<?php

namespace  ramshresh\yii2\galleryManager\rest;
use yii\data\ActiveDataProvider;
use yii\db\Exception;
use yii\db\Query;
use yii\rest\Controller;

class GalleryImageController extends Controller
{
    public $modelClass = 'ramshresh\yii2\galleryManager\GalleryImageAr';

    public function init()
    {
        parent::init();
        header("Access-Control-Allow-Origin: *");
    }

    public function actions()
    {
        return array_merge(parent::actions(), [
            'index' => [
                'class' => 'yii\rest\IndexAction',
                'modelClass' => $this->modelClass,
                'prepareDataProvider' => function ($action) {
                    /* @var $model  \common\modules\tracking\models\search\Status */
                    /* @var $query \yii\db\ActiveQuery */
                    $model = new $this->modelClass;

                    $query = $model::find();

                    if (!empty($_GET)) {
                        // Filter based on model attribute values with like operator
                        foreach ($_GET as $key => $value) {

                            //Compare with model attributes
                            if ($model->hasAttribute($key)) {
                                $query->andFilterWhere(['=', $key, $value]);
                            } else {
                                //try exploding attributes
                                $param = explode('->', $key, 2);
                                if (in_array($param[0], $model->extraFields())) {
                                    if ($model->getRelation($param[0])->multiple) {
                                        //The relation is: current model has_many related models
                                        //@todo Implement
                                    } else {
                                        //The relation is: current model has_one related models
                                        $query->joinWith($param[0]);
                                        $key = $param[0] . '.' . $param[1];
                                        $query->andFilterWhere(['=', $key, $value]);
                                    }

                                }
                            }
                        }
                    }
                    $dataProvider = new ActiveDataProvider(['query' => $query]);
                    return $dataProvider;
                }
            ],

        ]);
    }
}
