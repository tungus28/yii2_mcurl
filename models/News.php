<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "ra_news".
 *
 * @property string $name
 * @property string $content_text
 * @property boolean $active
 * @property integer $freq
 * @property integer $id
 * @property string $date_create
 */
class News extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ra_news';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'content_text', 'active', 'freq', 'id', 'date_create'], 'required'],
            [['name', 'content_text'], 'string'],
            [['active'], 'boolean'],
            [['freq', 'id'], 'integer'],
            [['date_create'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => 'Name',
            'content_text' => 'Content Text',
            'active' => 'Active',
            'freq' => 'Freq',
            'id' => 'ID',
            'date_create' => 'Date Create',
        ];
    }
}
