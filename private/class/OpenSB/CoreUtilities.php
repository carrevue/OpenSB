<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

namespace OpenSB;

/**
 * Static core utilities.
 */
class CoreUtilities
{
    public static function getURL(bool $includeURI = false): ?string
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return null;
        }

        $protocol = self::isThisHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        if ($includeURI && isset($_SERVER['REQUEST_URI'])) {
            return $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
        }

        return $protocol . '://' . $host;
    }

    public static function redirect(string $url, int $statusCode = 302): never
    {
        header("Location: $url", true, $statusCode);
        exit;
    }

    public static function isThisHttps()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return true;
        }

        // somewhat inefficient?
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $cf_visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
            if (isset($cf_visitor->scheme) && $cf_visitor->scheme === 'https') {
                return true;
            }
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        return false;
    }
}
