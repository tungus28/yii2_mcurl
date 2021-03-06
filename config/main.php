<?php
$params = array_merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'app-frontend',
    'language' => 'ru-Ru',
    'sourceLanguage'=>'ru',
    /*'i18n' => [
        'translations' => [
            '*' => [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => '@frontend/messages',
                'sourceLanguage' => 'en',
                'fileMap' => [
                    //'main' => 'main.php',
                ],
            ],
        ],
    ],*/
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
	'defaultRoute' => 'news',
    'controllerNamespace' => 'frontend\controllers',
	//'layout' => 'main.twig',
	'modules' => [		
		'webshell' => [
			'class' => 'samdark\webshell\Module',
			'allowedIPs' => ['*'],
		],		
    ],	
    'components' => [
		'urlManager' => [
			'enablePrettyUrl' => true,
			'rules' => [
                //'<controller:\w+>/<action:\w+>' => '<controller>/<action>',
			],    
		],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
		'view' => [
            'renderers' => [
                'twig' => [
                    'class' => 'yii\twig\ViewRenderer',
                    // set cachePath to false in order to disable template caching
                    'cachePath' => '@runtime/Twig/cache',
                    // Array of twig options:
                    'options' => [
                        'auto_reload' => true,
                    ],
                    // add Yii helpers or widgets here or {{ use('yii/web/LinkPager') }} in template.twig
                    /*'globals' => [
                        'LinkPager' => '\yii\widgets\LinkPager',
                    ],*/
                    // ... see ViewRenderer for more options
                ],
            ],
        ],
    ],
    'params' => $params,
];
