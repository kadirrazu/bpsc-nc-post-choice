<?php
namespace App\Services\Choice;
use InvalidArgumentException;
class BirthDateNormalizer {
    public function normalize(string $input): string {
        $value = trim($input);
        if (!preg_match('/^(\d{2})(\d{2})(\d{4}|\d{2})$/D', $value, $m)) throw new InvalidArgumentException('Birth date must be DDMMYYYY or DDMMYY.');
        $year = (int) $m[3];
        if (strlen($m[3]) === 2) $year += $year <= (int) config('choice.birth_year_pivot', 30) ? 2000 : 1900;
        if (!checkdate((int) $m[2], (int) $m[1], $year)) throw new InvalidArgumentException('Birth date is invalid.');
        return sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
    }
}
