<?php

namespace App\Controllers;

use App\Models\InviteCode;
use App\Services\Auth;
use App\Services\Config;
use App\Utils\AliPay;
use App\Utils\TelegramSessionManager;
use App\Utils\TelegramProcess;
use App\Utils\Geetest;
use App\Utils\Tools;
use Slim\Http\{Request, Response};
use Psr\Http\Message\ResponseInterface;

/**
 *  HomeController
 */
class HomeController extends BaseController
{
    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function index($request, $response, $args): ResponseInterface
    {
        $GtSdk = null;
        $recaptcha_sitekey = null;
        if (Config::get('captcha_provider') != '') {
            switch (Config::get('captcha_provider')) {
                case 'recaptcha':
                    $recaptcha_sitekey = Config::get('recaptcha_sitekey');
                    break;
                case 'geetest':
                    $uid = time() . random_int(1, 10000);
                    $GtSdk = Geetest::get($uid);
                    break;
            }
        }

        if (Config::get('enable_telegram')) {
            $login_text = TelegramSessionManager::add_login_session();
            $login = explode('|', $login_text);
            $login_token = $login[0];
            $login_number = $login[1];
        } else {
            $login_token = '';
            $login_number = '';
        }


        if ( !Config::get('newIndex') && Config::get('theme') == 'material') {
            return $response->write($this->view()->display('indexold.tpl'));
        } else {
            return $response->write($this->view()
                ->assign('geetest_html', $GtSdk)
                ->assign('login_token', $login_token)
                ->assign('login_number', $login_number)
                ->assign('telegram_bot', Config::get('telegram_bot'))
                ->assign('enable_logincaptcha', Config::get('enable_login_captcha'))
                ->assign('enable_regcaptcha', Config::get('enable_reg_captcha'))
                ->assign('base_url', Config::get('baseUrl'))
                ->assign('recaptcha_sitekey', $recaptcha_sitekey)
                ->fetch('index.tpl'));
        }
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function indexold($request, $response, $args): ResponseInterface
    {
        return $response->write($this->view()->fetch('indexold.tpl'));
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function code($request, $response, $args): ResponseInterface
    {
        $codes = InviteCode::where('user_id', '=', '0')->take(10)->get();
        return $response->write($this->view()->assign('codes', $codes)->fetch('code.tpl'));
    }

    public function down()
    { }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function tos($request, $response, $args): ResponseInterface
    {
        return $response->write($this->view()->fetch('tos.tpl'));
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function staff($request, $response, $args): ResponseInterface
    {
        return $response->write($this->view()->fetch('staff.tpl'));
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function telegram($request, $response, $args): ResponseInterface
    {
        $token = $request->getQueryParam('token');
        if ($token == Config::get('telegram_request_token')) {
            TelegramProcess::process();
            $result = '1';
        } else {
            $result = '0';
        }
        return $response->write($result);
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function page404($request, $response, $args): ResponseInterface
    {
        return $response->write($this->view()->fetch('404.tpl'));
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function page405($request, $response, $args): ResponseInterface
    {
        return $response->write($this->view()->fetch('405.tpl'));
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function page500($request, $response, $args): ResponseInterface
    {
        return $response->write($this->view()->fetch('500.tpl'));
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function getOrderList($request, $response, $args): ResponseInterface
    {
        $key = $request->getParam('key');
        if (!$key || $key != Config::get('key')) {
            $res['ret'] = 0;
            $res['msg'] = '??????';
            return $response->write(json_encode($res));
        }
        return $response->write(json_encode(['data' => AliPay::getList()]));
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function setOrder($request, $response, $args): ResponseInterface
    {
        $key = $request->getParam('key');
        $sn = $request->getParam('sn');
        $url = $request->getParam('url');
        if (!$key || $key != Config::get('key')) {
            $res['ret'] = 0;
            $res['msg'] = '??????';
            return $response->write(json_encode($res));
        }
        return $response->write(json_encode(['res' => AliPay::setOrder($sn, $url)]));
    }

    public function getDocCenter($request, $response, $args)
    {
        $user = Auth::getUser();
        if (!$user->isLogin && !Config::get('enable_documents')) {
            $newResponse = $response->withStatus(302)->withHeader('Location', '/');
            return $response->write($newResponse);
        }
        $basePath = Config::get('remote_documents') == 'true' ? Config::get('documents_source') : '/docs/GeekQu';
        return $response->write($this->view()
            ->assign('appName', Config::get('documents_name'))
            ->assign('basePath', $basePath)
            ->display('doc/index.tpl'));
    }

    public function getSubLink($request, $response, $args)
    {
        $user = Auth::getUser();
        if (!$user->isLogin) {
            return $msg = '!> ?????? ???(????????)??? ?????? ?????????????????????[??????????????????](/auth/login \':ignore target=_blank\') ??????????????????????????????';
        } else {
            $subInfo = LinkController::getSubinfo($user, 0);
            switch ($request->getParam('type')) {
                case 'ssr':
                    $msg = [
                        '**???????????????**',
                        '```',
                        $subInfo['ssr'] . '&extend=1',
                        '```'
                    ];
                    break;
                case 'v2ray':
                    $msg = [
                        '**???????????????**',
                        '```',
                        $subInfo['v2ray'] . '&extend=1',
                        '```'
                    ];
                    break;
                case 'ssd':
                    $msg = [
                        '**???????????????**',
                        '```',
                        $subInfo['ssd'],
                        '```'
                    ];
                    break;
                case 'clash':
                    $msg = [
                        '**???????????????**[[??????????????????]](' . $subInfo['clash'] . ')',
                        '```',
                        $subInfo['clash'],
                        '```'
                    ];
                    break;
                case 'surge':
                    $msg = [
                        '**Surge Version 2.x ?????????????????????**[[iOS ????????????????????????]](surge:///install-config?url=' . urlencode($subInfo['surge2']) . ')',
                        '```',
                        $subInfo['surge2'],
                        '```',
                        '**Surge Version 3.x ?????????????????????**[[iOS ????????????????????????]](surge3:///install-config?url=' . urlencode($subInfo['surge3']) . ')',
                        '```',
                        $subInfo['surge3'],
                        '```'
                    ];
                    break;
                case 'kitsunebi':
                    $msg = [
                        '**?????? ss???v2ray ????????????????????????**',
                        '```',
                        $subInfo['kitsunebi'] . '&extend=1',
                        '```'
                    ];
                    break;
                case 'surfboard':
                    $msg = [
                        '**?????????????????????**',
                        '```',
                        $subInfo['surfboard'],
                        '```'
                    ];
                    break;
                case 'quantumult_sub':
                    $msg = [
                        '**ssr ???????????????**[[iOS ????????????????????????]](quantumult://configuration?server=' . Tools::base64_url_encode($subInfo['ssr']) . ')',
                        '```',
                        $subInfo['ssr'],
                        '```',
                        '**V2ray ???????????????**[[iOS ????????????????????????]](quantumult://configuration?server=' . Tools::base64_url_encode($subInfo['quantumult_v2']) . ')',
                        '```',
                        $subInfo['quantumult_v2'],
                        '```'
                    ];
                    break;
                case 'quantumult_conf':
                    $msg = [
                        '**?????? ss???ssr???v2ray ????????????????????????????????????**',
                        '```',
                        $subInfo['quantumult_sub'],
                        '```',
                        '**???????????? Surge???Clash ??????????????????????????????????????????**',
                        '```',
                        $subInfo['quantumult_conf'],
                        '```'
                    ];
                    break;
                case 'shadowrocket':
                    $msg = [
                        '**?????? ss???ssr???v2ray ????????????????????????**[[iOS ????????????????????????]](sub://' . base64_encode($subInfo['shadowrocket']) . ')',
                        '```',
                        $subInfo['shadowrocket'],
                        '```'
                    ];
                    break;
                default:
                    $msg = [
                        '??????????????????...????????????????????????'
                    ];
                    break;
            }
        }
        return $response(implode(PHP_EOL, $msg));
    }
}
