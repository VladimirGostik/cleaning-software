<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MAP = [
        'a' => 'áäàâãåāăą',
        'c' => 'čćĉċ',
        'd' => 'ďđ',
        'e' => 'éěëèêēėę',
        'i' => 'íìîïī',
        'l' => 'ĺľł',
        'n' => 'ňńñ',
        'o' => 'óôöòõőō',
        'r' => 'ŕř',
        's' => 'šśŝş',
        't' => 'ťţ',
        'u' => 'úůüùûűū',
        'y' => 'ýÿ',
        'z' => 'žźż',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $from = '';
        $to = '';

        foreach (self::MAP as $plain => $accented) {
            foreach ([false, true] as $upper) {
                $letters = $upper ? mb_strtoupper($accented) : $accented;
                $target = $upper ? strtoupper($plain) : $plain;

                foreach (mb_str_split($letters) as $letter) {
                    $from .= $letter;
                    $to .= $target;
                }
            }
        }

        DB::unprepared(
            "CREATE OR REPLACE FUNCTION f_unaccent(text) RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT AS \$\$ SELECT translate(\$1, '{$from}', '{$to}') \$\$",
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP FUNCTION IF EXISTS f_unaccent(text)');
    }
};
