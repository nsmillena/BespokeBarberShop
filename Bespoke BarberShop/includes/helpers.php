<?php
// Common helpers for formatting and UI rendering
if (!function_exists('bb_format_minutes')) {
    function bb_format_minutes($m) {
        $m = (int)$m;
        if ($m < 60) return $m . 'min';
        $h = intdiv($m, 60);
        $rem = $m % 60;
        return $h . 'h ' . $rem . 'min';
    }
}
