<?php
// config/currency_helper.php

class CurrencyHelper {
    private static $currencyMap = [
        'India' => [
            'symbol' => '₹',
            'code' => 'INR',
            'position' => 'before',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'locale' => 'en-IN'
        ],
        'UAE' => [
            'symbol' => 'AED ',
            'code' => 'AED',
            'position' => 'before',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'locale' => 'ar-AE'
        ],
        'UK' => [
            'symbol' => '£',
            'code' => 'GBP',
            'position' => 'before',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'locale' => 'en-GB'
        ],
        'USA' => [
            'symbol' => '$',
            'code' => 'USD',
            'position' => 'before',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'locale' => 'en-US'
        ]
    ];

    /**
     * Get currency configuration for a country
     */
    public static function getCurrencyConfig($country) {
        $country = ucfirst(trim($country));
        return self::$currencyMap[$country] ?? self::$currencyMap['India'];
    }

    /**
     * Format amount with currency symbol
     */
    public static function format($amount, $country, $showSymbol = true) {
        $config = self::getCurrencyConfig($country);
        $formatted = number_format(
            $amount,
            2,
            $config['decimal_separator'],
            $config['thousands_separator']
        );
        
        if (!$showSymbol) {
            return $formatted;
        }
        
        if ($config['position'] === 'before') {
            return $config['symbol'] . $formatted;
        } else {
            return $formatted . $config['symbol'];
        }
    }

    /**
     * Get only the currency symbol
     */
    public static function getSymbol($country) {
        $config = self::getCurrencyConfig($country);
        return $config['symbol'];
    }

    /**
     * Get currency code
     */
    public static function getCode($country) {
        $config = self::getCurrencyConfig($country);
        return $config['code'];
    }

    /**
     * Format amount without symbol (for JavaScript)
     */
    public static function formatNumber($amount, $country) {
        $config = self::getCurrencyConfig($country);
        return number_format(
            $amount,
            2,
            $config['decimal_separator'],
            $config['thousands_separator']
        );
    }

    /**
     * Get all currency info as array
     */
    public static function getAllInfo($country) {
        return self::getCurrencyConfig($country);
    }

    /**
     * Validate if country has currency configuration
     */
    public static function isValidCountry($country) {
        $country = ucfirst(trim($country));
        return isset(self::$currencyMap[$country]);
    }

    /**
     * Get list of supported countries
     */
    public static function getSupportedCountries() {
        return array_keys(self::$currencyMap);
    }

    /**
     * Format for display in dropdowns
     */
    public static function getCountryDisplay($country) {
        $config = self::getCurrencyConfig($country);
        return $country . ' (' . $config['symbol'] . ' ' . $config['code'] . ')';
    }
}
?>