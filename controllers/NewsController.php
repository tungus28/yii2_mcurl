<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use frontend\models\News;

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

        $news = News::find()
            ->orderBy('id')
            ->all();

        //echo "<pre>"; var_dump($news); exit;

        return $this->render('index.twig', [
            'visits' => $session->get('visits'),
            'news' => $news,
        ]);
    }

}
