<?php

namespace frontend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ra_news".
 *
 * @property string $title
 * @property string $content
 * @property boolean $active
 * @property integer $freq
 * @property integer $id
 * @property string $created
 */
class News extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'news.ra_news';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title', 'content', 'active', 'freq', 'id', 'created'], 'required'],
            [['title', 'content'], 'string'],
            [['active'], 'boolean'],
            [['freq', 'id'], 'integer'],
            [['created'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'title' => 'Title',
            'content' => 'Content',
            'active' => 'Active',
            'freq' => 'Freq',
            'id' => 'ID',
            'created' => 'Created',
        ];
    }
}
