$(function(){
    //console.log('news.js is ok');       
    var buttonGetNewsPressed = false;
    //кнопка получить новость
    $('#getNews').click(function(){//одна новость
        if (buttonGetNewsPressed === true) return;//защита от повторных нажатий
        buttonGetNewsPressed = true;
        $('#getNews').css("background-color", "#286090"); //change btn color
        $('#oneNewsStatus').html('');

        //проверка прогресса загрузки новости
        var oneNewsStatus = '';
        var flagOneNews = '';
        function checkOneNews() {
            $.get("ajax_t.php", {check_progress: 1})
                .done(function(response){
                    if (oneNewsStatus !== response) {
                        var time= response.match(/\d*\.\d*/);
                        var msg = response.match(/[а-яА-Я\s:]+/);
                        if (time !== null) $('#oneNewsStatus').append(" " + time);
                        setTimeout(function() {
                            $('#oneNewsStatus').append("<br>" + msg);
                        }, 300);//вывод названия обрабатываемого пункта через 0.3 с

                        oneNewsStatus = response;
                    }
                    if (flagOneNews !=='done') {
                        setTimeout(function(){
                            checkOneNews();//рекурсия запросов
                        }, 310);//отсылка повторных запросов не ранее .31 с от получения ответа
                    }
                });
        }

        //обработка одной новости
        $.post( "/news/get-one-news", { getNews: 1, _csrf: $('#getNews').attr('value')})//TODO - add yii object to use yii.getCsrfToken()
            .done(function( resp ) {
                flagOneNews = 'done';
                $('#getNews').css("background-color", "#337ab7");
                setTimeout(function(){
                    $('#oneNewsStatus').append(resp);
                    buttonGetNewsPressed = false;
                }, 500);//держим клавишу еще нажатой 0.5 с
            });
        setTimeout(function () {
            checkOneNews();//прогресс
        }, 300);//начинаем проверку прогресса через 0.3 с

        //ограничение запроса на сервер
//                    setTimeout(function(){
//                        flagOneNews='done';
//                        $('#oneNewsStatus').html('Ошибка: сервер не ответил');
//                        buttonGetNewsPressed = false;
//                        $('#getNews').css("background-color", "#337ab7");
//                    }, 30000);//через 5 с

    });

    //кнопка получить много новостей
    $('#getLotNews').click(function(){
        var newsCnt = 0;
        function checkLotNews(){
            $.get("ajax_t.php", {check_lot_news_progress: 1})
                .done(function( resp ) {
                    if (newsCnt !== resp) {
                        $('#lotNewsStatus').html('<br>Добавлено новостей: ' + resp);
                    }
                    newsCnt = resp;
                });
        }
        $.post( "/news/get-lot-news", { getLotNews: 1, _csrf: $('#getNews').attr('value')} )
            .done(function( resp ) {
                setTimeout(clearInterval(intervalCheck), 1001);//отмена проверки прогресса через 1с от конца запроса
            });
        //проверка прогресса добавления новостей
        var intervalCheck = setInterval(function() {
            checkLotNews();
        }, 500);

    });
    //очистить базу
    $('#clearDB').click(function() {
            $.get("/news/clear-db",{_csrf: $('#getNews').attr('value')})
                .done(function(data) {
                    //alert(data);

                });
        }

    );

});
