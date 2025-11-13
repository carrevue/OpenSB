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

use MaxMind\Db\Reader;

/**
 * class IPLookup
 * 
 * This class is a simplified interface for MaxMind's database reader class.
 */
class IPLookup
{
    /**
     * @var Reader The MaxMind Database Reader class.
     */
    private Reader $reader;

    /**
     * function __construct
     *
     * @param string $config
     *
     * @return void
     */
    public function __construct(string $config)
    {
        if ($config) {
            $this->reader = new Reader(BLUFF_PRIVATE_PATH . '/config/' . $config);
        }
    }

    /**
     * function getInfo
     * 
     * Gets IP information, and falls back to generic placeholder data for
     * localhost or invalid IPs.
     *
     * @param mixed $ip
     *
     * @return mixed
     */
    public function getInfo($ip)
    {
        if (
            $ip == "localhost"
            || $ip == "999.999.999.999"
            || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE)
        ) {
            // made up bullshit so theres no bugs
            return [
                "as_domain" => "localhost",
                "as_name" => "Unknown",
                "asn" => "AS00000",
                "continent" => "Unknown",
                "continent_code" => "XX",
                "country" => "Unknown",
                "country_code" => "XX",
            ];
        }
        return $this->reader->get($ip);
    }

    /**
     * function getCountry
     * 
     * Gets the IP's country.
     *
     * @param mixed $ip
     *
     * @return mixed
     */
    public function getCountry($ip)
    {
        return $this->getInfo($ip)["country_code"];
    }
}
