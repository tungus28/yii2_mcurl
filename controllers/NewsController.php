<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use frontend\models\News;
use yii\data\ActiveDataProvider;
use yii\widgets\LinkPager;

class NewsController extends Controller
{
    public function actionIndex()
    {

        $session = Yii::$app->session;
        if ($session->has('visits')) {
            $session->set('visits', $session->get('visits') + 1);
            $session->close();
        } else {
            $session->set('visits', 1);
            $session->close();
        }

        $query = News::find();//->where(['status' => 1]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => (int)Yii::$app->request->get('per-page', 20),//second arg - default value
            ],
            'sort' => [
                'defaultOrder' => [
                    'name' => SORT_ASC,
                ]
            ],
        ]);

        $news = $provider->getModels();
        $pages = $provider->getPagination();//to be called after getModels()
      //  $pager = new LinkPager(['pagination' => $pages]);

        //echo "<pre>"; var_dump((abs(Yii::$app->request->get('per-page', 20)))); exit;

        return $this->render('index.twig', [
            'visits' => $session->get('visits'),
            'news' => $news,
            'pages' => $pages,
           // 'pager' => $pager,
        ]);
    }

}
