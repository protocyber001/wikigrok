<?php
define(_WIKIDIR_, 'D:/_titip_pa_kaji/UwAmp/www/wiki_grok/');
// var_dump(_WIKIDIR_);
require_once _WIKIDIR_ . 'config/config.php';
require_once _WIKIDIR_ . 'core/Router.php';

Router::route($_SERVER['REQUEST_URI']);
?>