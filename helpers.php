<?php

if (! function_exists('as_money')) {
    function as_money(mixed $value): string
    {
        return sprintf('$ %s', number_format($value, 2));
    }
}
