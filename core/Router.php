<?php
require_once _WIKIDIR_ . 'config/config.php';

class Router {
    public static function route($url) {
        // Hapus query string dari URL
        $url = explode('?', $url)[0];
        $parts = explode('/', trim($url, '/'));
        $controller = !empty($parts[0]) ? ucfirst($parts[0]) : 'Article';
        $method = !empty($parts[1]) ? $parts[1] : 'index';
        $param = !empty($parts[2]) ? $parts[2] : null;

        $controllerClass = $controller . 'Controller';
        $controllerFile = _WIKIDIR_ . "controllers/$controllerClass.php";

        if (!file_exists($controllerFile)) {
            die("Kontroler $controllerClass tidak ditemukan!");
        }

        require_once $controllerFile;
        $controllerObj = new $controllerClass();

        if (!method_exists($controllerObj, $method)) {
            die("Metode $method tidak ditemukan di $controllerClass!");
        }

        if ($param) {
            $controllerObj->$method($param);
        } else {
            $controllerObj->$method();
        }
    }
}
?>