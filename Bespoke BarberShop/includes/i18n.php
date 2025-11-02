<?php
// Simple i18n helper with cookie/session persistence

if (!defined('BB_LOCALE_COOKIE')) define('BB_LOCALE_COOKIE', 'bb_locale');
if (!defined('BB_DEFAULT_LOCALE')) define('BB_DEFAULT_LOCALE', 'pt_BR');

function bb_allowed_locales(){
    return ['pt_BR','en_US'];
}

function bb_get_locale(){
    if (!empty($_SESSION['bb_locale'])) return $_SESSION['bb_locale'];
    if (!empty($_COOKIE[BB_LOCALE_COOKIE])) {
        $loc = $_COOKIE[BB_LOCALE_COOKIE];
        if (in_array($loc, bb_allowed_locales(), true)) {
            $_SESSION['bb_locale'] = $loc;
            return $loc;
        }
    }
    return BB_DEFAULT_LOCALE;
}

function bb_set_locale($locale){
    if (!in_array($locale, bb_allowed_locales(), true)) return false;
    $_SESSION['bb_locale'] = $locale;
    @setcookie(BB_LOCALE_COOKIE, $locale, time()+3600*24*365, '/');
    return true;
}

// Load translations for current locale
function bb_translations(){
    static $cache = null;
    if ($cache !== null) return $cache;
    $loc = bb_get_locale();
    $file = __DIR__.'/lang/'.($loc === 'en_US' ? 'en_US.php' : 'pt_BR.php');
    $dict = [];
    if (file_exists($file)) {
        $dict = include $file;
        if (!is_array($dict)) $dict = [];
    }
    $cache = $dict;
    return $cache;
}

function t($key, array $vars = []){
    $dict = bb_translations();
    // Resolve dot notation
    $val = $dict;
    foreach (explode('.', $key) as $part) {
        if (is_array($val) && array_key_exists($part, $val)) {
            $val = $val[$part];
        } else { $val = null; break; }
    }
    if ($val === null) { $val = $key; }
    if (is_array($val)) { $val = $key; }
    if (!empty($vars)) {
        foreach($vars as $k=>$v){ $val = str_replace('{'.$k.'}', $v, $val); }
    }
    return $val;
}

function bb_is_en(){ return bb_get_locale() === 'en_US'; }
function bb_is_pt(){ return bb_get_locale() === 'pt_BR'; }

function bb_recaptcha_hl(){ return bb_is_en() ? 'en' : 'pt-BR'; }
