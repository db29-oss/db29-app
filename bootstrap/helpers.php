<?php

// bash char escape
function bce(string $str = '', string $lbs = '', string $hbs = '\\', $quote = "'", $over_ride = []): string {
    $replaced_str = \K92\Phputils\BashCharEscape::escape($str, $lbs, $hbs, $quote, $over_ride);

    return $replaced_str;
}

