<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2026 Chaziz

  OpenSB is free software: you can redistribute it and/or modify it under the 
  terms of the GNU Affero General Public License as published by the Free 
  Software Foundation, either version 3 of the License, or (at your option) any
  later version. 

  OpenSB is distributed in the hope that it will be useful, but WITHOUT ANY 
  WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
  FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more 
  details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace Core;

use Arokettu\Pseudolocale\Pseudolocale;
use DateTime;
use DateTimeInterface;
use Exception;
use IntlDateFormatter;
use MessageFormatter;
use NumberFormatter;

/**
 * class Localization
 * 
 * This class handles translation.
 */
class Localization
{
    /**
     * @var array The user's options, inherited from the core SquareBracket class.
     */
    private array $options;

    /**
     * @var string The current locale.
     */
    protected string $locale;

    /**
     * @var array Strings from the currently selected locale.
     */
    protected array $messages = [];

    /**
     * @var array Fallback strings from the American English locale.
     */
    protected array $messages_fallback = [];

    /**
     * @var bool If psuedolocalization is enabled
     */
    private bool $isPsuedo = false;

    /**
     * function __construct
     *
     * @param array $options
     *
     * @return void
     */
    public function __construct(array $options)
    {
        $this->options = $options;
        $this->locale = $options["locale"] ?? "en-US";
        $this->loadLocalizationData();
    }

    /**
     * function loadLocalizationData
     *
     * @return void
     */
    protected function loadLocalizationData(): void
    {
        $file = SB_PRIVATE_PATH . "/locales/{$this->locale}.json";
        $file_fallback = SB_PRIVATE_PATH . "/locales/en-US.json"; // fallback to english

        if ($this->locale == "psuedo") {
            $this->isPsuedo = true;
            $file = $file_fallback;
        }

        if (file_exists($file)) {
            $json = file_get_contents($file);
            $this->messages = json_decode($json, true);
        } else {
            trigger_error("Localization $this->locale ($file) missing", E_USER_WARNING);
        }

        if ($this->locale != "en-US") {
            if (file_exists($file_fallback)) {
                $json = file_get_contents($file_fallback);
                $this->messages_fallback = json_decode($json, true);
            } else {
                throw new Exception("The default en-US locale is missing.");
            }
        }
    }

    public function getLocaleData() {
        return $this->messages;
    }

    /**
     * function formatDate
     * 
     * Formats dates.
     *
     * @param mixed $date
     * @param mixed $dateFormat
     * @param mixed $timeFormat
     * @param mixed $pattern
     *
     * @return mixed
     */
    public function formatDate($date, $dateFormat = 'medium', $timeFormat = 'medium', $pattern = null)
    {
        if (!$date instanceof DateTimeInterface) {
            if ($date === null) {
                return "unknown";
            } elseif (is_numeric($date)) {
                $date = new DateTime('@' . $date);
            } else {
                $date = new DateTime($date);
            }
        }

        $locale = $this->isPsuedo ? 'en-US' : $this->locale;
        $formatter = new IntlDateFormatter(
            $locale,
            $this->convertDateFormatterPattern($dateFormat),
            $this->convertDateFormatterPattern($timeFormat),
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
            $pattern
        );

        return $formatter->format($date);
    }

    /**
     * function formatNumber
     * 
     * Formats numbers.
     *
     * @param mixed $number
     *
     * @return mixed
     */
    public function formatNumber($number)
    {
        if ($number == null) $number = 0;
        
        $formatter = new NumberFormatter($this->isPsuedo ? 'en-US' : $this->locale, NumberFormatter::DECIMAL);
        return $formatter->format($number);
    }

    /**
     * function formatRelativeTime
     *
     * Relative time function.
     *
     * @param mixed $time
     *
     * @return mixed
     */
    public function formatRelativeTime($time)
    {
        if ($time === 0) {
            return $this->translate('relative_unknown');
        }

        $diff = time() - $time;

        if ($diff < 1) {
            return $this->translate('relative_just_now');
        }

        $units = [
            31536000 => ['relative_year', 'relative_years'],
            2592000  => ['relative_month', 'relative_months'],
            604800   => ['relative_week', 'relative_weeks'],
            86400    => ['relative_day', 'relative_days'],
            3600     => ['relative_hour', 'relative_hours'],
            60       => ['relative_minute', 'relative_minutes'],
            1        => ['relative_second', 'relative_seconds']
        ];

        foreach ($units as $seconds => $keys) {
            if ($diff >= $seconds) {
                $value = floor($diff / $seconds);
                $key = $keys[$value == 1 ? 0 : 1];
                return sprintf(
                    $this->translate('relative_ago_format'),
                    $value,
                    $this->translate($key)
                );
            }
        }

        return $this->translate('relative_just_now');
    }

    /*
    public function truncateNumber($number)
    {
        $precision = 2;

        if ($number < 1000) {
            $formatter = new NumberFormatter($this->isPsuedo ? 'en-US' : $this->locale, NumberFormatter::DECIMAL);
            return $formatter->format($number);
        } else {
            $suffixes = ['', 'k', 'm', 'b', 't'];
            $suffixIndex = 0;

            while ($number >= 1000 && $suffixIndex < count($suffixes) - 1) {
                $number /= 1000;
                $suffixIndex++;
            }

            $formatter = new NumberFormatter($this->isPsuedo ? 'en-US' : $this->locale, NumberFormatter::DECIMAL);

            $should_show_decimals = $number >= 100 && $number < 1000;

            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $should_show_decimals ? 0 : $precision);

            return $formatter->format($number) . $suffixes[$suffixIndex];
        }
    }
    */

    /**
     * function translate
     * 
     * Handles all translations.
     *
     * @param mixed $key
     * @param mixed $args
     *
     * @return mixed
     */
    public function translate($key, ...$args)
    {
        //$hitchhiker_string_debug = isset($this->options["localization_use_hitchhiker_strings"]) && $this->options["localization_use_hitchhiker_strings"] == "true";
    
        // if we're on finalium hitchhiker, check if there's a hitchhiker-specific version of a string
        if ($this->options["skin"] == "finalium" && $this->options["theme"] == "hitchhiker" /*|| $hitchhiker_string_debug*/) {
            if (array_key_exists($key . "_hitchhiker", $this->messages)) {
                $key .= "_hitchhiker";
            }
        }

        if ($this->isPsuedo) {
            return $this->translatePsuedo($key, ...$args);
        }

        $message = $this->messages[$key] ?? $this->messages_fallback[$key] ?? $key;

        if (str_contains($message, '{')) {
            if ($args && array_keys($args) === range(0, count($args) - 1)) {
                $args = ['count' => $args[0]];
            }
            return $this->translateICU($message, $args);
        }

        foreach ($args as $arg) {
            $message = preg_replace('/%s/', $arg, $message, 1);
        }

        return $message;
    }

    /**
     * function getLanguageCode
     * 
     * Returns the current locale's language code.
     *
     * @return string|mixed
     */
    public function getLanguageCode()
    {
        if ($this->locale == null) {
            return 'en';
        }

        $parts = explode('-', $this->locale);
        return strtolower($parts[0]);
    }

    /**
     * function convertDateFormatterPattern
     * 
     * Formats dates.
     *
     * @param mixed $pattern
     *
     * @return mixed
     */
    private function convertDateFormatterPattern($pattern)
    {
        if (is_int($pattern)) {
            return $pattern;
        }

        $formats = [
            'none' => IntlDateFormatter::NONE,
            'short' => IntlDateFormatter::SHORT,
            'medium' => IntlDateFormatter::MEDIUM,
            'long' => IntlDateFormatter::LONG,
            'full' => IntlDateFormatter::FULL,
            //'relative' => IntlDateFormatter::RELATIVE_SHORT, IntlDateFormatter's relative time is fucking stupid
        ];

        return $formats[strtolower($pattern)] ?? IntlDateFormatter::MEDIUM;
    }

    /**
     * function translatePsuedo
     * 
     * Handle psuedo-localization.
     *
     * @param mixed $key
     * @param mixed $args
     *
     * @return string|mixed
     */
    private function translatePsuedo($key, ...$args)
    {
        if (!isset($this->messages[$key])) {
            if ($args) {
                return "[$key] (" . implode(', ', $args) . ")";
            } else {
                return "[$key]";
            }
        }

        $message = $this->messages[$key];

        // TODO: make this not use a dependency. -chaziz 1/16/2025
        return Pseudolocale::pseudolocalize($args ? vsprintf($message, $args) : $message);
    }

    /**
     * function translateICU
     * 
     * Handles ICU translations for units.
     *
     * @param string $message
     * @param array $args
     *
     * @return mixed
     */
    private function translateICU(string $message, array $args)
    {
        $locale = $this->isPsuedo ? 'en-US' : $this->locale;
        $formatter = new MessageFormatter($locale, $message);
        $result = $formatter->format($args);
        return $result !== false ? $result : $message;
    }
}
