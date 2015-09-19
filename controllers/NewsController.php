<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;

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

        //echo "<pre>"; var_dump($session); exit;

        return $this->render('index.twig', [
            'visits' => $session->get('visits'),
        ]);
    }

}
