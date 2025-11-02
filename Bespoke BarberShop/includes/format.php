<?php
// Locale-aware formatting helpers (dates/times/currency)

require_once __DIR__.'/i18n.php';

if (!defined('USD_BRL_RATE')) define('USD_BRL_RATE', 5.20); // override in config.local.php

function bb_locale_code(){ return bb_get_locale(); }

function bb_intl_available(){ return function_exists('intl_get_error_code'); }

function bb_format_date($date){
    $ts = bb_to_timestamp($date);
    $loc = bb_locale_code();
    if (bb_intl_available()){
        $pattern = ($loc==='en_US') ? 'MM/dd/yyyy' : 'dd/MM/yyyy';
        $fmt = new IntlDateFormatter($loc, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), NULL, $pattern);
        return $fmt->format($ts);
    }
    return ($loc==='en_US') ? date('m/d/Y', $ts) : date('d/m/Y', $ts);
}

function bb_format_time($time){
    $ts = bb_to_timestamp($time);
    $loc = bb_locale_code();
    if (bb_intl_available()){
        $pattern = ($loc==='en_US') ? 'h:mm a' : 'HH:mm';
        $fmt = new IntlDateFormatter($loc, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), NULL, $pattern);
        return $fmt->format($ts);
    }
    return ($loc==='en_US') ? date('g:i A', $ts) : date('H:i', $ts);
}

function bb_format_datetime($dt){
    $ts = bb_to_timestamp($dt);
    $loc = bb_locale_code();
    if (bb_intl_available()){
        $pattern = ($loc==='en_US') ? 'MM/dd/yyyy h:mm a' : 'dd/MM/yyyy HH:mm';
        $fmt = new IntlDateFormatter($loc, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), NULL, $pattern);
        return $fmt->format($ts);
    }
    return ($loc==='en_US') ? date('m/d/Y g:i A', $ts) : date('d/m/Y H:i', $ts);
}

function bb_currency_for_locale(){ return (bb_locale_code()==='en_US') ? 'USD' : 'BRL'; }

function bb_amount_for_locale($amountBrl){
    // Convert BRL to USD when locale is EN
    if (bb_locale_code()==='en_US'){
        $rate = floatval(USD_BRL_RATE) ?: 5.20;
        if ($rate <= 0) $rate = 5.20;
        return $amountBrl / $rate;
    }
    return $amountBrl; // BRL
}

function bb_format_currency_local($amountBrl){
    $currency = bb_currency_for_locale();
    $amount   = bb_amount_for_locale($amountBrl);
    $loc      = bb_locale_code();

    if (bb_intl_available()){
        $fmt = new NumberFormatter($loc, NumberFormatter::CURRENCY);
        $out = $fmt->formatCurrency($amount, $currency);
        if ($out !== false) return $out;
    }
    // Fallback manual formatting
    if ($currency==='USD'){
        return '$' . number_format($amount, 2, '.', ',');
    } else {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }
}

function bb_to_timestamp($value){
    if ($value instanceof DateTimeInterface) return $value->getTimestamp();
    if (is_numeric($value)) return (int)$value;
    // Try to parse common formats
    $ts = strtotime((string)$value);
    if ($ts !== false) return $ts;
    return time();
}
