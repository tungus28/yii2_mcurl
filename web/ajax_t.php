<?php
/** обрабока запросов ajax */

if(session_id() == '') {
    session_start();
}

if (isset($_GET['check_progress'])) {
    $progress = 'undefined';
    if (!isset($_SESSION['one_news_progress'])) {
        $progress = 'not yet';
    } else {
        $progress = $_SESSION['one_news_progress'];
    }

    echo $progress; return;
}

if (isset($_GET['check_lot_news_progress'])) {
    echo json_encode(
        array(
            'cnt' => $_SESSION['news_cnt'],
            'timer' => $_SESSION['curl_timer'],
            'all' => $_SESSION['cnt_all'],
            'repeat' => $_SESSION['cnt_repeat'],
        ));
    //$_SESSION['test'] = date('H:i s /d-m-Y');

    return;
}

