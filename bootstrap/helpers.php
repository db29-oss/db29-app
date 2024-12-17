<?php

function formatNumberShort(int $number) {
    if ($number >= 1000 && $number < 1000000) {
        return round($number / 1000, 1) . 'k';
    }

    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    }

    return $number;
}
