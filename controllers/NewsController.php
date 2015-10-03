<?php

namespace frontend\controllers;

use Yii;
use yii\base\ErrorException;
use yii\db\Expression;
use yii\web\Controller;
use frontend\models\News;
use yii\data\ActiveDataProvider;
use yii\web\HttpException;


class NewsController extends Controller
{
    public $timer;

    public function saveInSession($name, $data)
    {
        Yii::$app->session->set($name, $data);
        Yii::$app->session->close();
    }

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
                    'title' => SORT_ASC,
                ]
            ],
        ]);

        $news = $provider->getModels();
        $pages = $provider->getPagination();//to be called after getModels()

        //echo "<pre>"; var_dump((abs(Yii::$app->request->get('per-page', 20)))); exit;

        return $this->render('index.twig', [
            'visits' => $session->get('visits'),
            'news' => $news,
            'pages' => $pages,
            'csrfToken' => Yii::$app->request->getCsrfToken(),
        ]);
    }

    public function actionGetOneNews()
    {

        if (!Yii::$app->request->isAjax) {
            throw new HttpException(404, 'Ajax only');
        }

        if(Yii::$app->request->post('getNews')) {
            $this->timer = $this->microtime_float();
            $newsGot = $this->getMainNews();

            if ( $newsGot['title'] != '' && $this->isUnique($newsGot['title']) ) {
                $this->saveOneNews($newsGot);
                exit($this->timeCut() . "<br>новость добавлена базу");
            } else {
                exit($this->timeCut() . "<br>такая новость есть в базе");
            }
        }
    }

    /**
     * Функция вывода времени в секундах в конкретный момент
     * @return float
     */
    public function microtime_float() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * Функция получения и обработки одной новости
     * (вырезка тэгов - на выходе загаловок и текст)
     * @param string $url
     * @return array
     */
    public function getMainNews($url = 'http://ria.ru/') {
        if($url == 'http://ria.ru/') {//парсим url с главной страницы
            $this->timer = $this->microtime_float();
            $c = curl_init($url);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
            $this->saveInSession('one_news_progress', 'соединение с сайтом: ');

            //обработка ошибки соединения
            $text = curl_exec($c);
            if ($text === false ) {
                echo $this->timeCut() . "<br>Произошла ошибка: " . curl_error($c);
                curl_close($c);
                exit;
            }

            curl_close($c);

            $this->saveInSession('one_news_progress', $this->timeCut() . "<br>обработка новости: ");

            $start = strpos($text, '<div class="more">');
            $end = strpos($text, 'Читать далее');
            $text = substr($text, $start, $end - $start);
            $urlN = substr($text, strpos($text, 'href')+6, strpos($text, 'html')- strpos($text, 'href') - 2);
            $url = 'http://ria.ru' . $urlN;
        }
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
        $text = curl_exec($c);
        $title = substr($text, strpos($text, 'article_header_title'), strpos($text, 'article_header_story') - strpos($text, 'article_header_title'));
        $title = substr($title, strpos($title, '>') + 1, strpos($title, '<') - strpos($title, '>') - 1);
        $text = substr($text, strpos($text, 'articleBody') + 13);
        //убрать из статьи <div class="media_copyright">© AP Photo/ Alexander Zemlianichenko</div>
        $text = substr($text, 0, strpos($text, 'facebook'));
        $text = $this->strip_tags_content($text, '<a>', true);
        //sleep(3);
        $text = strip_tags($text);
        $smth = array('|', '&nbsp;', '&ndash;','&mdash;', '&raquo;', '&laquo;');
        $forSmth = array(' ', ' ', '-', '-', '\"', '\"');
        $text = str_replace($smth, $forSmth, $text);
        $news = array();
        $news['title'] = $title;
        $news['content'] = $text;

        $this->saveInSession('one_news_progress', $this->timeCut() . "<br>обработка новости: ");

        return $news;
    }

    /**
     * Функция обработки html - различные варианты обрезки тэгов
     * @param $text
     * @param string $tags
     * @param bool $invert
     * @return mixed
     */
    function strip_tags_content($text, $tags = '', $invert = FALSE) {

        preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
        $tags = array_unique($tags[1]);

        if(is_array($tags) AND count($tags) > 0) {
            if($invert == FALSE) {
                return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
            }
            else {
                return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
            }
        }
        elseif($invert == FALSE) {
            return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
        }
        return $text;
    }

    /**
     * Функция
     * выдает округленные временные отрезки
     */
    public function timeCut() {
        $timerCut = $this->microtime_float() - $this->timer;
        $this->timer = $this->microtime_float();
        return round($timerCut, 2);
    }

    /**
     * Функция проверки на уникальность записи
     * по заголовку в базе
     * true - можно писать
     * @param $title
     * @return bool
     */
    public function isUnique($title)
    {
        $found = News::find()
            ->where('title=:title', [':title' => $title])
            ->one();

        if($found) {
            return false;
        } else {
            return true;
        }

    }

    public function saveOneNews($news)
    {
        $oneNews = new News();
        $oneNews->title = $news['title'];
        $oneNews->content = $news['content'];
        $oneNews->created = new Expression('NOW()');
        $oneNews->active = 0;
        $oneNews->freq = 0;

        //echo "<pre>"; print_r($oneNews); exit;

        if(!$oneNews->save()) {
            $msg = "ошибка записи: " . serialize($oneNews->errors);
            throw new ErrorException($msg);
        }
    }

    public function actionClearDb()
    {
        News::deleteAll(/*['status' => Customer::STATUS_INACTIVE]*/);
    }

}
