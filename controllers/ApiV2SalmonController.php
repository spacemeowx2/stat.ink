<?php
/**
 * @copyright Copyright (C) 2015-2018 AIZAWA Hina
 * @license https://github.com/fetus-hina/stat.ink/blob/master/LICENSE MIT
 * @author AIZAWA Hina <hina@bouhime.com>
 */

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\actions\api\v2\salmon\IndexAction;
use app\actions\api\v2\salmon\PostSalmonAction;
use app\actions\api\v2\salmon\ViewAction;
use yii\filters\ContentNegotiator;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;
use yii\web\Response;

class ApiV2SalmonController extends Controller
{
    public $enableCsrfValidation = false;

    public function init()
    {
        parent::init();
        Yii::$app->language = 'en-US';
        Yii::$app->timeZone = 'Etc/UTC';
    }

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            'authenticator' => [
                'class' => HttpBearerAuth::class,
                'except' => [ 'options' ],
                'optional' => [ 'index', 'index-with-auth', 'view' ],
            ],
        ]);
    }

    protected function verbs()
    {
        return [
            'index'   => ['GET', 'HEAD'],
            'index-with-auth' => ['GET', 'HEAD'],
            'view'    => ['GET', 'HEAD'],
            'create'  => ['POST'],
            'options' => ['OPTIONS'],
        ];
    }

    public function actions()
    {
        return [
            'create' => [
                'class' => PostSalmonAction::class,
            ],
            'index' => [
                'class' => IndexAction::class,
                'isAuthMode' => false,
            ],
            'index-with-auth' => [
                'class' => IndexAction::class,
                'isAuthMode' => true,
            ],
            'view' => [
                'class' => ViewAction::class,
            ],
        ];
    }

    public function actionOptions($id = null)
    {
        $res = Yii::$app->response;
        if (Yii::$app->request->method !== 'OPTIONS') {
            $res->statusCode = 405;
            return $res;
        }
        $res->statusCode = 200;
        $header = $res->getHeaders();
        $header->set('Allow', implode(
            ', ',
            $id === null
                ? [ 'GET', 'HEAD', 'POST', 'OPTIONS' ]
                : [ 'GET', 'HEAD', /* 'PUT', 'PATCH', 'DELETE', */ 'OPTIONS']
        ));
        $header->set('Access-Control-Allow-Origin', '*');
        $header->set('Access-Control-Allow-Methods', $header->get('Allow'));
        $header->set('Access-Control-Allow-Headers', 'Content-Type, Authenticate');
        $header->set('Access-Control-Max-Age', '86400');
        return $res;
    }
}
