<?php

namespace Acme\NewsBundle\Controller;

//use Acme\NewsBundle\Entity\News;
//use Symfony\Bundle\FrameworkBundle\Controller\Controller;
//use Symfony\Component\HttpFoundation\RedirectResponse;
//use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\HttpFoundation\Response;
//use Acme\NewsBundle\Form\ContactType;
use \Curl\Curl;
use Curl\MultiCurl;

// these import the "@Route" and "@Template" annotations
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

//for native sql
//use Doctrine\ORM\Query\ResultSetMapping;



class NewsController extends Controller
{
    /**
     * @var
     */
    public $repository;
    public $timer;
    public $urlsToRepeat = array();
    public $piece = 2; //загружать по n новостей одновременно

    /**
     * @Route("/", name="_news")
     * @Template()
     *
     */
    public function indexAction()
    {

        isset($_SESSION['visits']) ? $_SESSION['visits']++ : $_SESSION['visits'] = 1;
        session_write_close();
		

        $em = $this->get('doctrine.orm.entity_manager');
        $query = $em->createQuery("SELECT n FROM AcmeNewsBundle:News n");

        /*
         * $req->query->get() для Get
         * $req->request->() для Post
         * $req->cookies->get('PHPSESSID');
         * $req->isSecure(); //проверяет используется ли https для подключения
         *
         */
        //обрабока запроса
        $req = $this->get('request');//создаем объект Symfony\Component\HttpFoundation\Request

       /* $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $req->query->get('page', 1),/*page number*/
            /*$req->query->get('maxItemPerPage', 20)/*limit per page*/
        /*);*/

        $timeLetters = '';
        $timeWord = '';

        //подсчитываем статистику по слову
        if(isset($_POST['get_freq']) && ($_POST['searchWord'] != '')) {
            $time_start = $this->microtime_float();
            $searchWord = trim(strip_tags($_POST['searchWord']));

            if(!isset($_POST['active'])) {
                for ($i = 0; $i<count($news); $i++) {
                    $news[$i]->setFreq(substr_count($news[$i]->getContentText(), $searchWord));
                }
            } else {
                for ($i = 0; $i<count($_POST['active']); $i++) {
                    $news[$_POST['active'][$i]]->setFreq(substr_count($news[$_POST['active'][$i]]->getContentText(), $searchWord));
                }
            }

            $time_end = $this->microtime_float();
            $timeWord = " Время выполнения по слову " . ($time_end - $time_start);
        }

        $tht = '';
		//xhprof
		my_xhprof_disable();
        //render
        return array(
                    'news' => $pagination,
                    'timeLetters' => $timeLetters,
                    'timeWord' => $timeWord,
                    'visits' => $_SESSION['visits'],
                    );
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function getOneNewsAction(Request $request)
    {
        //проверка на ajax запрос
        if (!$request->isXmlHttpRequest())
            return new Response('ajax only!:-)');

        if(isset($_POST['getNews'])) {
            $this->timer = $this->microtime_float();
            $newsGot = $this->getMainNews();

            if ( $newsGot['title'] != '' && $this->isUnique($newsGot['title']) ) {
                $this->saveOneNews($newsGot);
                return new Response($this->timeCut() . "<br>новость добавлена базу");
            } else {
                return new Response($this->timeCut() . "<br>такая новость есть в базе");
            }
        }
    }

    public function getLotNewsAction(Request $request)
    {
        //проверка на ajax запрос
        if (!$request->isXmlHttpRequest())
            return new Response('ajax only!:-)');

        //очист базу
        $this->clearDBAction();

        if(isset($_POST['getLotNews'])) {
            $urlsAll = $this->getLotNewsUrls();//список ссылок новостей
            
            $cnt = count($urlsAll);
            if (session_id() == "") 
				session_start();
            $_SESSION['cnt_all'] = $cnt;
            session_write_close();

            $this->multiCurlByParts($urlsAll, $this->piece);

            //прогоняем неудачные загрузки
            if (count($this->urlsToRepeat) > 0 ) {
                $this->multiCurlByParts($this->urlsToRepeat, 1);
            }
        }

        return new Response($_SESSION['cnt_repeat']);

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
        $newsGot['text'] = $text;

        return $newsGot;
    }

    public function clearDBAction(/*Request $request*/)
    {
        //проверка на ajax запрос
        /*if (!$request->isXmlHttpRequest())
            return new Response('ajax only!:-)');*/

        $em = $this->getDoctrine()->getManager();

        $qb = $em->createQueryBuilder();
        $qb->delete('AcmeNewsBundle:News', 'n');

        $qb->getQuery()->execute();

        return new Response( 'table is cleaned' );



    }

    public function saveOneNews($newsGot)
    {
        $oneNews = new News();
        $oneNews->setName($newsGot['title']);
        $oneNews->setContentText($newsGot['text']);
        $oneNews->setDateCreate(new \DateTime('now'));
        $oneNews->setActive(0);
        $oneNews->setFreq(0);
        $em = $this->getDoctrine()->getManager();
        $em->persist($oneNews);
        $em->flush();
    }

    /**
     * Функция получения и обработки одной новости
     * (вырезка тэгов - на выходе загаловок и текст)
     * @param string $url
     * @return array
     */
    public function getMainNews($url = 'http://ria.ru/') {
        if($url == 'http://ria.ru/') {//парсим url с главной страницы
            //$this->timer = $this->microtime_float();
            $c = curl_init($url);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
            session_start();
            $_SESSION['one_news_progress'] = "соединение с сайтом: ";
            session_write_close();

            //обработка ошибки соединения
            $text = curl_exec($c);
            if ($text === false ) {
                echo $this->timeCut() . "<br>Произошла ошибка: " . curl_error($c);
                curl_close($c);
                exit;
            }

            curl_close($c);

            session_start();
            $_SESSION['one_news_progress'] = $this->timeCut() . "<br>обработка новости: ";
            session_write_close();


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
        $news['text'] = $text;

        session_start();
        $_SESSION['one_news_progress'] = $this->timeCut() . "<br> обработка закончена: ";
        session_write_close();

        return $news;
    }

    /**
     * Функция получения ссылок на множество новостей
     * @param string $url1
     * @return array
     */
   public function getLotNewsUrls($url1='http://ria.ru/world/') {

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
     * Функция вывода времени в секундах в конкретный момент
     * @return float
     */
    public function microtime_float() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * выдавать округленные временные отрезки
     */
    public function timeCut() {
        $timerCut = $this->microtime_float() - $this->timer;
        $this->timer = $this->microtime_float();
        return round($timerCut, 2);
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
     * Функция проверки на уникальность записи по заголовку в базе true - можно писать
     * @param $title
     * @return bool
     */
    public function isUnique($title){
        $repository = $this->getDoctrine()
            ->getRepository('AcmeNewsBundle:News');
        $query = $repository->createQueryBuilder('n')
            ->where('n.name = :title')
            ->setParameter('title', $title)
            ->getQuery();
        $found = $query->getResult();
        if($found)
            return false;
        return true;
    }

    /**
     * Функция загрузки и обработки множества новостей
     * @param array $urls
     * @return Response
     */
    public function multiCurl_old($urls=array(), $connectTime, $waitTime){
        $this->timer = $this->microtime_float();
        $mh = curl_multi_init(); // инициируем мультикурл хэндл


        $aCurlHandles = array(); // создаем массив для индивидуальных хендлов

        foreach ($urls as $id=>$url) {//добавляем хэндл на каждый урл

            $ch = curl_init($url); // init curl, and then setup your options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // returns the result - very important
            curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTime);  //таймаут соединения
            curl_setopt($ch, CURLOPT_TIMEOUT, $waitTime);// таймаут ожидания

            $aCurlHandles[$url] = $ch;
            curl_multi_add_handle($mh,$ch);
        }

        $active = null; //~ количество активных соединений

        //execute the handles
        do {
            $mrc = curl_multi_exec($mh, $active);
        }
        while ($mrc == CURLM_CALL_MULTI_PERFORM);//заменить на $active > 0

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }


        // получение содержимого
        foreach ($aCurlHandles as $url=>$ch) {
            $text = curl_multi_getcontent($ch); // get the content
            // do wha
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
            $newsGot['text'] = $text;

            //проверка на наличие в базе и запись новости в базу
            if($newsGot['text'] != ''
               && strpos($newsGot['text'], 'GLOBAL.ad') == false
               && $this->isUnique($newsGot['title']) == true) {
                    //сохраняем новость и увеличиваем счетчик
                    $this->saveOneNews($newsGot);
                    session_start();
                    $_SESSION['news_cnt']++;
                    session_write_close();
            }

            curl_multi_remove_handle($mh, $ch); // remove the handle (assuming  you are done with it);
        }
        /* End of the relevant bit */

        curl_multi_close($mh); // close the curl multi handler
        session_start();
        $_SESSION['curl_timer'] = $this->timeCut();
        session_write_close();
    }





    /**
     * @Template()
     */
//    public function socketAction()
//    {
//        $socket = stream_socket_server("tcp://127.0.0.1:54321", $errno, $errstr);
//        if (!$socket) {
//            echo "$errstr ($errno)<br />\n";
//        } else {
//            while ($conn = stream_socket_accept($socket)) {
//                fwrite($conn, 'Локальное время ' . date('n/j/Y g:i a') . "\n");
//                fclose($conn);
//            }
//            fclose($socket);
//        }
//        return new Response('Socket is ok');
//    }

}
