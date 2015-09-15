<?php

namespace frontend\controllers;

class NewsController extends \yii\web\Controller
{
    public function actionIndex()
    {
        //$this->view->registerJsFile('js/jquery-min.js');
        //$this->view->registerCssFile('css/bootstrap.min.css');

        return $this->render('index.twig', []);
    }

}
