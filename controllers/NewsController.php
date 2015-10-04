<?php

namespace frontend\controllers;

use Yii;
use yii\base\ErrorException;
use yii\db\Expression;
use yii\web\Controller;
use frontend\models\News;
use yii\data\ActiveDataProvider;
use yii\web\HttpException;
use \Curl\Curl;
use Curl\MultiCurl;


class NewsController extends Controller
{
    public $timer;
    public $piece = 2;
    public $urlsToRepeat;

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

    public function actionGetLotNews()
    {
        //проверка на ajax запрос
        if (!Yii::$app->request->isAjax) {
            throw new HttpException(404, 'Ajax only');
        }

        $this->saveInSession('all', 0);
        $this->saveInSession('cnt_repeat', 0);
        $this->saveInSession('news_cnt', 0);
        //очист базу
        $this->actionClearDb();

        if(Yii::$app->request->post('getLotNews')) {
            $urlsAll = $this->getLotNewsUrls();//список ссылок новостей

            $cnt = count($urlsAll);

            $this->saveInSession('cnt_all', $cnt);

            $this->multiCurlByParts($urlsAll, $this->piece);

            //прогоняем неудачные загрузки
            if (count($this->urlsToRepeat) > 0 ) {
                $this->multiCurlByParts($this->urlsToRepeat, 1);//ошибочные загрузки повторяем по одной
            }
        }

        return Yii::$app->session->get('cnt_repeat');

    }

    public function multiCurlByParts($urlsAll, $piece = 2)
    {
        $cnt = count($urlsAll);
        for ( $i = 0; $i < $cnt; $i = $i + $piece ) {
            $this->multiCurl(array_slice($urlsAll, $i, $piece));
        }
        return true;
    }

    public function multiCurl($urls)
    {
        $this->timer = $this->microtime_float();

        $multi_curl = new MultiCurl();

        $multi_curl->setOpt(CURLOPT_ENCODING , 'gzip');

        $multi_curl->success(function($instance) {
            session_start();
            $_SESSION['news_cnt']++;//количество удачных загрузок новостей
            session_write_close();
            //echo 'call to "' . $instance->url . '" was successful.' . "\n";
            //echo 'response: ' . $instance->response . "\n";
            $newsGot = $this->getContent($instance->response);
            if ($this->isUnique($newsGot['title'])) {
                $this->saveOneNews($newsGot);
            }
        });

        $multi_curl->error(function($instance) {
            echo 'call to "' . $instance->url . '" was unsuccessful.' . "\n";
            $this->urlsToRepeat[] = $instance->url;
            session_start();
            $_SESSION['cnt_repeat']++;
            session_write_close();
            //echo 'error code: ' . $instance->errorCode . "\n";
            //echo 'error message: ' . $instance->errorMessage . "\n";
        });

        $multi_curl->complete(function($instance) {
            //echo 'call completed' . "\n";
        });

        foreach($urls as $url) {
            $multi_curl->addGet($url);
        }

        $multi_curl->start();

        //таймер на конец функции
        session_start();
        $_SESSION['curl_timer'] = $this->timeCut();
        session_write_close();

    }

    public function getLotNewsUrls($url1='http://ria.ru/world/')
    {
        $c = curl_init($url1);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);

        $text = curl_exec($c);//примерно 15 с

        curl_close($c);

        $arrOut = array();
        preg_match_all("#\/\w+\/\d+\/\d+.html#", $text, $arrOut);
        $arrOut = array_unique($arrOut[0]);//массив ссылок на новости - обработка с regexp ~0.02c


        $urlsAll = array();
        foreach($arrOut as $k=>$v){
            $urlsAll[] = 'http://ria.ru' . $v;
        }

        return $urlsAll;

    }

    /**
     * @param string $text
     * @return array
     */
    public function getContent($text)
    {
        $title = substr($text, strpos($text, 'article_header_title'), strpos($text, 'article_header_story') - strpos($text, 'article_header_title'));
        $title = substr($title, strpos($title, '>') + 1, strpos($title, '<') - strpos($title, '>') - 1);
        $text = substr($text, strpos($text, 'articleBody') + 13);
        //убрать из статьи <div class="media_copyright">© AP Photo/ Alexander Zemlianichenko</div>
        $text = substr($text, 0, strpos($text, 'facebook'));
        $text = $this->strip_tags_content($text, '<a>', true);
        $text = strip_tags($text);
        $smth = array('|', '&nbsp;', '&ndash;','&mdash;', '&raquo;', '&laquo;');
        $forSmth = array(' ', ' ', '-', '-', '\"', '\"');
        $text = str_replace($smth, $forSmth, $text);
        $newsGot = array();
        $newsGot['title'] = $title;
        $newsGot['content'] = $text;

        return $newsGot;
    }

}
