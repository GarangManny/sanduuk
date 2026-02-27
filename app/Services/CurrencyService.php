<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    /**
     * Get the exchange rate from one currency to another.
     * 
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function getRate($from, $to)
    {
        if ($from === $to) {
            return 1.0;
        }

        try {
            // Using a free API for exchange rates. 
            // Note: In production, you would want a more reliable one or multiple fallbacks.
            $response = Http::get("https://api.exchangerate-api.com/v4/latest/{$from}");

            if ($response->successful()) {
                $rates = $response->json('rates');
                if (isset($rates[$to])) {
                    return (float) $rates[$to];
                }
            }
        } catch (\Exception $e) {
            Log::error("Currency conversion error: " . $e->getMessage());
        }

        // Fallback hardcoded rates if API fails
        return self::getFallbackRate($from, $to);
    }

    /**
     * Fallback rates for common pairs.
     */
    private static function getFallbackRate($from, $to)
    {
        $rates = [
            'USD' => [
                'KES' => 130.00,
                'SSP' => 130.00,
            ],
            'KES' => [
                'USD' => 1 / 130.00,
                'SSP' => 1.00,
            ],
            'SSP' => [
                'USD' => 1 / 130.00,
                'KES' => 1.00,
            ],
        ];

        return $rates[$from][$to] ?? 1.0;
    }
}
