<?php
use yii\grid\GridView;

/* @var $this yii\web\View */
//$this->title = 'My Yii Application';
?>
<div id="test"></div>
<div style="float: right; margin: 10px;">Количество посещений: <?= $visits ?> </div>
<form method="post" action="">
    <div style="margin-bottom: 30px;">

        <button id="getNews" class="btn btn-primary"  type="button" value="<?= $csrfToken ?>" >Получить новость</button>
        <button id="getLotNews" class="btn btn-primary" type="button">Получить много новостей</button>
        <button class="btn btn-primary">Статистика по буквам</button>
        <button id="clearDB" class="btn btn-primary" type="button">Очистить базу</button>

        <div style="margin-top: 15px;">
            <label>Введите слово для фильтра&nbsp;</label>
            <input placeholder="слово" type="text" name="searchWord" >
            <input  class="btn btn-primary" type = "submit" name='get_freq' value="Отчет по словам">
        </div>
        <div id="oneNewsStatus" style="font-size: 16px;"></div>
        <div id="lotNewsStatus" style="font-size: 16px;"></div>
        <div>&nbsp;<?= $timeLetters ?></div>
        <div>&nbsp;<?= $timeWord ?></div>
    </div>
</form>

<!-- настройки пагинации -->
<table style="width: 100%; margin-bottom: 20px;">
    <tr>
        <!--td style="width: 20%">Всего страниц: {{ pages.pageCount }}</td>
        <td style="width: 30%; text-align: center;">{{ link_pager_widget({'pagination': pages}) }}</td>
        <td style="width: 10%; text-align: center;"> Всего: {{ pages.totalCount  }}</td-->

        <td style="width: 40%; text-align: right;">Показывать
            <a href="?per-page=20">20</a>
            <a href="?per-page=50">50</a>
            <a href="?per-page=100">100</a> на страницу</td>
    </tr>
</table>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        ['attribute' => "title", 'label' => 'Заголовок'],
        ['attribute' => "content", 'label' => 'Текст статьи', 'value' => [Yii::$app->controller, 'getShortText']],
        ['attribute' => "created", 'label' => 'Дата загрузки', 'format' => ['date', 'php:d/m/Y']],
    ]
]); ?>

