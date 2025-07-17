<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

namespace SquareBracket;

use Arokettu\Pseudolocale\Pseudolocale;
use Exception;
use IntlDateFormatter;

class Localization
{
    protected mixed $locale;
    protected array $messages = [];
    protected array $messages_fallback = [];
    private bool $isPsuedo = false;

    /**
     * @throws Exception
     */
    public function __construct($locale = 'en-US')
    {
        $this->locale = $locale;
        $this->loadLocalizationData();
    }

    /**
     * @throws Exception
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

    public function formatDate($date, $dateFormat = 'medium', $timeFormat = 'medium', $pattern = null)
    {
        if (!$date instanceof \DateTimeInterface) {
            $date = is_numeric($date) ? new \DateTime('@' . $date) : new \DateTime($date);
        }

        $locale = $this->isPsuedo ? 'en-US' : $this->locale;
        $formatter = new IntlDateFormatter(
            $locale,
            $this->convertPattern($dateFormat),
            $this->convertPattern($timeFormat),
            $date->getTimezone(),
            IntlDateFormatter::GREGORIAN,
            $pattern
        );

        return $formatter->format($date);
    }

    private function convertPattern($pattern)
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

    public function translate($key, ...$args)
    {
        if ($this->isPsuedo) {
            return $this->translatePsuedo($key, ...$args);
        } else {
            if (!isset($this->messages[$key]) && !isset($this->messages_fallback[$key])) {
                if ($args) {
                    return "[$key] (" . implode(', ', $args) . ")";
                } else {
                    return "[$key]";
                }
            }


            if (isset($this->messages[$key]) && $this->messages[$key]) {
                $message = $this->messages[$key];
            } else {
                $message = $this->messages_fallback[$key];
            }

            foreach ($args as $arg) {
                $message = preg_replace('/%s/', $arg, $message, 1);
            }

            return $message;
        }
    }

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
        return Pseudolocale::pseudolocalize(vsprintf($message, $args));
    }
}
