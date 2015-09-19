<?php

namespace frontend\controllers;

use yii\web\Controller;

class NewsController extends Controller
{
    public function actionIndex()
    {
        //$this->view->registerJsFile('js/jquery-min.js', ['position' => View::POS_HEAD]);
        //$this->view->registerCssFile('css/bootstrap.min.css');

        return $this->render('index.twig', []);
    }

}
