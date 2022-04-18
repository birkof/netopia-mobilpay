<?php

namespace Mobilpay;

/**
 * Class MobilpayGlobal
 * @copyright   Copyright (c) NETOPIA
 * @author      Claudiu Tudose / maintainer  Daniel Stancu
 * @version     2.1
 *
 */
class MobilpayGlobal
{
    const PAYMENT_ALLOWED = 0x01;
    const PAYMENT_NOT_ALLOWED = 0x02;
    const PAYMENT_NOT_AVAILABLE = 0x03;

    public static function buildCRC($params)
    {
        $crc_pairs = [];
        foreach ($params as $key => $value) {
            if (strcasecmp($key, 'crc') == 0) {
                continue;
            }
            $crc_pairs[] = "{$key}={$value}";
        }

        return md5(implode('&', $crc_pairs));
    }

    public static function buildQueryString($params)
    {
        $crc_pairs = [];
        foreach ($params as $key => $value) {
            $crc_pairs[] = "{$key}={$value}";
        }

        return implode('&', $crc_pairs);
    }

    public static function attachQueryString($url, $query_string)
    {
        $return_url = $url;
        if (strlen($query_string) == 0) {
            return $return_url;
        }

        $pos = strpos($url, '?');
        if ($pos === false) {
            $return_url = "{$url}?{$query_string}";
        } else {
            if ($pos == strlen($url) - 1) {
                $return_url = "{$url}{$query_string}";
            } else {
                if (substr($url, strlen($url) - 1) == '&') {
                    $return_url = "{$url}{$query_string}";
                } else {
                    $return_url = "{$url}&{$query_string}";
                }
            }
        }

        return $return_url;
    }

    public static function formatMessage($message_format, $message_parameters = null)
    {
        $message = $message_format;
        if (is_array($message_parameters)) {
            $index = 1;
            foreach ($message_parameters as $value) {
                $search = "%{$index}%";
                $replace = $value;
                $message = str_replace($search, $replace, $message);
                $index++;
            }
        }

        return $message;
    }


    public static function isValidMsisdn($param_msisdn)
    {
        $pattern = '/^(4)?07[0-9]{8,8}$/';

        return preg_match($pattern, $param_msisdn) != 0;
    }

    public static function unreferrencedVariable($variable)
    {
        $variable = $variable;
    }

    public static function generatePassword($num_interval, $alpha_interval, $length = DEFAULT_CODE_LENGTH)
    {
        $num_start = null;
        $num_end = null;
        $num_exclude = null;
        $max_value = null;
        if (is_array($num_interval)) {
            $num_start = ord($num_interval['first']);
            $num_end = ord($num_interval['last']);
            $num_exclude = [];
            if (isset($num_interval['exclude']) && is_array($num_interval['exclude'])) {
                $num_exclude = $num_interval['exclude'];
            }
            $max_value = $num_end;
        }

        $alpha_start = null;
        $alpha_end = null;
        $alpha_exclude = null;
        if (is_array($alpha_interval)) {
            $alpha_start = ord($alpha_interval['first']);
            $alpha_end = ord($alpha_interval['last']);
            $alpha_exclude = [];
            if (is_array($alpha_interval['exclude'])) {
                $alpha_exclude = $alpha_interval['exclude'];
            }
            $max_value = $alpha_end;
        }
        if (is_null($max_value)) {
            return null;
        }

        srand((double)microtime() * 1000000);
        $password = "";
        while ($length) {
            $value = rand() % $max_value;
            if (!is_null($alpha_start) && !is_null($alpha_end) && is_array($alpha_exclude)) {
                if ($value >= $alpha_start && $value <= $alpha_end && !in_array($value, $alpha_exclude)) {
                    $password .= chr($value);
                    $length--;
                    continue;
                }
            }
            if (!is_null($num_start) && !is_null($num_end) && is_array($num_exclude)) {
                if ($value >= $num_start && $value <= $num_end && !in_array($value, $num_exclude)) {
                    $password .= chr($value);
                    $length--;
                    continue;
                }
            }
        }

        return $password;
    }

    /**
     * generate a seller signature
     * this signature will be used by seller to identify himself when makes a payment request
     * signature format will be XXXX-XXXX-XXXX-XXXX-XXXX where X could be any uppercase letter (except O and I) or any
     * digit (except 0)
     *
     * @return string
     */
    public static function generateSellerSignature()
    {
        $num_start = ord("1");
        $num_end = ord("9");
        $al_start = ord("A");
        $al_end = ord("Z");
        $al_exclude = [ord("O"), ord("I")];
        $num_exclude = [ord("0")];
        srand((double)microtime() * 1000000);
        $signature_parts = [];
        for ($index = 0; $index < 5; $index++) {
            $signature_part = "";
            $length = 4;
            while ($length) {
                $value = rand() % $al_end;
                if ($value >= $al_start && $value <= $al_end && !in_array($value, $al_exclude)) {
                    $signature_part .= chr($value);
                    $length--;
                } elseif ($value >= $num_start && $value <= $num_end && !in_array($value, $num_exclude)) {
                    $signature_part .= chr($value);
                    $length--;
                }
            }
            $signature_parts[] = $signature_part;
        }

        return implode('-', $signature_parts);
    }
}
