<?php

namespace App\Domain\Commerce\Orders;

use Illuminate\Support\Facades\DB;

/**
 * Human order numbers, sequential per year: IO-2026-000001. The year's counter row is locked while
 * the next number is taken, so two checkouts never get the same number. Call inside a transaction.
 */
class OrderNumbers
{
    public function next(): string
    {
        $year = (int) now(config('platform.display_timezone'))->format('Y');

        DB::table('order_number_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $current = (int) DB::table('order_number_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
        $next = $current + 1;
        DB::table('order_number_sequences')->where('year', $year)->update(['last_number' => $next]);

        return sprintf('IO-%d-%06d', $year, $next);
    }
}
