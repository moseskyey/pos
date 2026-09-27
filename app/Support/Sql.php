<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Small driver-aware SQL expression helpers so reports run on MySQL (production)
 * and SQLite (tests) alike.
 */
class Sql
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    public static function date(string $column): string
    {
        return static::driver() === 'sqlite' ? "date($column)" : "DATE($column)";
    }

    public static function hour(string $column): string
    {
        return static::driver() === 'sqlite' ? "CAST(strftime('%H', $column) AS INTEGER)" : "HOUR($column)";
    }

    public static function month(string $column): string
    {
        return static::driver() === 'sqlite' ? "strftime('%Y-%m', $column)" : "DATE_FORMAT($column, '%Y-%m')";
    }

    public static function week(string $column): string
    {
        return static::driver() === 'sqlite' ? "strftime('%Y-W%W', $column)" : "DATE_FORMAT($column, '%x-W%v')";
    }
}
