<?php

// bash char escape
function bce(string $str = '', string $lbs = '', string $hbs = '\\', $quote = "'", $over_ride = []): string {
    $replaced_str = \K92\Phputils\BashCharEscape::escape($str, $lbs, $hbs, $quote, $over_ride);

    return $replaced_str;
}

function formatNumberShort(int $number) {
    if ($number >= 1000 && $number < 1000000) {
        return round($number / 1000, 1) . 'k';
    }

    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    }

    return $number;
}
