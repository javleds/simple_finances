<?php

if (! function_exists('as_money')) {
    function as_money(mixed $value): string
    {
        return sprintf(
            '%s$ %s',
            $value >= 0 ? '' : '-',
            number_format(abs($value), 2)
        );
    }
}
