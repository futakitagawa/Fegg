<?php
/**
 * ディスパッチャ
 *
 * 全てのリクエストに対して、はじめにこのプログラムが実行されて、
 * 次にURLに応じて各プログラムが呼び出される。
 * ドキュメントルートに配置された .htaccess がその制御を行っている。
 *
 * @author    Kazuyuki Saka
 * @copyright 2005-2019 Genies Inc.
 * @version   1.1.0
 * @link      https://github.com/genies-inc/Fegg 
 */

// アプリケーションが設置されている位置
if (preg_match('/(.*)index\.php/', $_SERVER['SCRIPT_NAME'], $matche)) {
    if ($matche[1] == '/') {
        define('FEGG_REWRITEBASE', '');
    } else {
        define('FEGG_REWRITEBASE', rtrim($matche[1], '/'));
    }
} else {
    define('FEGG_REWRITEBASE', '');
}
$tempPath = '';
for ($i = 0; $i < substr_count(FEGG_REWRITEBASE, '/') + 1; $i++) {
    $tempPath .= '/..';
}
$rootpath = realpath(dirname(__FILE__) . $tempPath);

// システム定数定義
define('FEGG_CODE_DIR', $rootpath . '/code');
define('FEGG_HTML_DIR', realpath(dirname(__FILE__)));
define('FEGG_DIR', $rootpath . '/fegg');

// ユーザー定数定義
if (file_exists(FEGG_CODE_DIR . '/config/define.php')) {
    require_once(FEGG_CODE_DIR . '/config/define.php');
}

// リクエストURLの取得（route.phpの定義と揃えるためURIの前後のスラッシュを除去）
$pattern = '/^' . str_replace('/', '\/', FEGG_REWRITEBASE) . '\//';
$uri = preg_replace($pattern, '', $_SERVER['REQUEST_URI']);
$uri = preg_replace('/\?.*/', '', $uri);

// URLセグメンテーション処理
$feggUri = '';
if (file_exists(FEGG_CODE_DIR . '/config/route.php')) {

    // ルートファイルの読み込み
    require_once(FEGG_CODE_DIR . '/config/route.php');

    if (!preg_match("/:any|:num/", $uri) && isset($route[$uri]) && $route[$uri]) {

        $feggUri = $route[$uri];

    } else {

        foreach ($route as $key => $value) {

            $key = str_replace(':num', '([0-9]+)', $key);
            $key = str_replace(':any', '([^\/]+)', $key);

            if (preg_match('#^' . $key . '$#', $uri)) {

                // URIの引数を置換
                if (strpos($value, '$') !== false) {
                    $value = preg_replace('#^' . $key . '$#', $value, $uri);
                }

                $feggUri = $value;
                break;

            }

        }

    }
}

// route.phpにマッチしなかったURIはそのまま格納
$feggUri = $feggUri ? $feggUri : $uri;

// クラス名とメソッド名を決定
$uriSegments = explode('/', $feggUri);
$tempPath = '';
$fileName = '';
$className = '';
$methodName = '';
$parameter = array();

foreach ($uriSegments as $key => $value) {

    if ($tempPath) {
        $tempPath .= '/';
    }

    // 同一階層に同一のフォルダ名とファイル名が存在する場合はファイルを優先する
    if (file_exists(FEGG_CODE_DIR . '/application/' . $tempPath . ucwords($value) . '.php')) {
        $fileName = ucwords($value);
        $methodName = isset($uriSegments[$key + 1]) ? $uriSegments[$key + 1] : '';
        $parameter = array_slice($uriSegments, $key + 2);
        break;
    }

    $tempPath .= $value;

}

// クラス名とメソッド名が決定しない場合初期値を設定
if (!$fileName) {
    $fileName = 'Index';
}
if (!$methodName) {
    $methodName = 'index';
}

// アプリケーションクラス読み込み
require(FEGG_DIR . '/Application.php');

// 最終的に呼び出すクラスファイルの存在チェックとrequireを行う
$classInstance = '';
if (file_exists(FEGG_CODE_DIR . '/application/' . $tempPath . $fileName . '.php')) {

    // 実行対象のアプリケーションのパス（リダイレクトやテンプレート用を想定）
    define('FEGG_APP_BASE', FEGG_REWRITEBASE . '/' . $tempPath . $fileName);
    define('FEGG_APP_CLASS', $fileName);

    try {

        // インスタンス生成
        require(FEGG_CODE_DIR . '/application/' . $tempPath . $fileName . '.php');
        $className = $fileName;
        $classInstance = new $className;

        // 初期化
        if (method_exists($classInstance, '__init')) {
            call_user_func_array(array($classInstance, '__init'), array());
        }

        // 実行
        if (method_exists($classInstance, $methodName)) {
            call_user_func_array(array($classInstance, $methodName), $parameter);
        }

    } catch (Exception $exception) {
        // アプリケーションで例外をCatchされなかった例外の処理
        if (isset($_SERVER['REMOTE_ADDR']) && isset($settings['developer_ip']) && in_array($_SERVER['REMOTE_ADDR'], $settings['developer_ip'])) {
            $trace = $exception->getTrace();
            echo '<pre>';
            foreach ($trace as $key => $value) {
                switch ($value['function']) {
                    case 'errorHandler':
                        echo '<hr>関数内で定義された変数<br>';
                        print_r($trace[0]['args'][4]);
                        break;

                    case 'call_user_func_array':
                        echo '<hr>$this->page変数<br>';
                        print_r($value['args'][0][0]->page);
                        break;
                }
            }
            echo '</pre>';
        }
        exit;
    }

} else {
    header("HTTP/1.0 404 Not Found");
    require(FEGG_DIR . '/settings.php');
    if (isset($_SERVER['REMOTE_ADDR']) && isset($settings['developer_ip']) && in_array($_SERVER['REMOTE_ADDR'], $settings['developer_ip'])) {
        echo "Application Not Found.<br/>";
        echo "URI       : ". $uri . '<br/>';
        echo "Fegg URI  : ". $feggUri . '<br/>';
        echo "Load File : ". FEGG_CODE_DIR . '/application/' . $tempPath . $fileName . '.php<br/>';
    }
    exit;
}


/**
 * 実行中クラス(Application.phpを継承）のインスタンス取得
 */
function FEGG_getInstance() {
    global $classInstance;
    $instance = $classInstance->getInstance();
    return $instance;
}
/* End of file index.php */
