<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

/**
 * class IPBans
 *
 * ip_bans rows can either be an ip, a range (1.2.3.0/24) or a LIKE pattern (192.168.%). 
 * asn bans are only checked if you pass the IPLookup class.
 */
class IPBans
{
    private ?IPLookup $ipLookup;
    private array $data = ['bans' => [], 'ips' => [], 'ranges' => [], 'patterns' => [], 'asns' => []];
    private array $checked = [];

    public function __construct(Database $database, ?IPLookup $ipLookup = null)
    {
        $this->ipLookup = $ipLookup;
        $this->load($database);
    }

    public static function isValid(string $ban): bool
    {
        $ban = trim($ban);

        if (filter_var($ban, FILTER_VALIDATE_IP)) {
            return true;
        }
        if (str_contains($ban, '/')) {
            return self::parseRange($ban) !== null;
        }
        return (str_contains($ban, '%') || str_contains($ban, '_')) && preg_match('/^[0-9a-f.:%_]+$/i', $ban) === 1;
    }

    public function find(string $ip): ?array
    {
        $ip = strtolower(trim($ip));
        if (!array_key_exists($ip, $this->checked)) {
            $this->checked[$ip] = $this->check($ip);
        }
        return $this->checked[$ip];
    }

    public function isBanned(string $ip): bool
    {
        return $this->find($ip) !== null;
    }

    public function rows(): array
    {
        return $this->data['bans'];
    }

    public function summary(): string
    {
        $types = array_count_values(array_column($this->data['bans'], 'type'));
        return sprintf(
            "%d ips, %d ranges, %d patterns, %d invalid, %s asns",
            $types['ip'] ?? 0,
            $types['range'] ?? 0,
            $types['pattern'] ?? 0,
            $types['invalid'] ?? 0,
            $this->ipLookup ? count($this->data['asns']) : 'no'
        );
    }

    private function load(Database $database): void
    {
        foreach ($database->fetchArray($database->query("SELECT ip, reason, timestamp FROM ip_bans")) as $index => $row) {
            $ban = strtolower(trim($row['ip']));
            $type = 'invalid';

            if (str_contains($ban, '%') || str_contains($ban, '_')) {
                $this->data['patterns'][] = ['/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($ban, '/')) . '$/', $index];
                $type = 'pattern';
            } elseif ($range = self::parseRange($ban)) {
                $this->data['ranges'][$range[0]][$range[1]] ??= $index;
                $type = 'range';
            } elseif ($packed = @inet_pton($ban)) {
                $this->data['ips'][inet_ntop($packed)] ??= $index;
                $type = 'ip';
            }

            $this->data['bans'][] = ['ip' => $row['ip'], 'reason' => $row['reason'], 'timestamp' => (int)$row['timestamp'], 'type' => $type];
        }

        if ($this->ipLookup) {
            foreach ($database->fetchArray($database->query("SELECT asn, timestamp FROM asn_bans")) as $row) {
                $this->data['asns'][(int)$row['asn']] = (int)$row['timestamp'];
            }
        }
    }

    private function check(string $ip): ?array
    {
        $packed = @inet_pton($ip);

        if ($packed !== false) {
            $index = $this->data['ips'][inet_ntop($packed)] ?? null;
            if ($index !== null) {
                return $this->data['bans'][$index];
            }

            foreach ($this->data['ranges'] as $key => $networks) {
                [$length, $bits] = explode('/', $key);
                $index = (int)$length === strlen($packed) ? ($networks[bin2hex(self::mask($packed, (int)$bits))] ?? null) : null;
                if ($index !== null) {
                    return $this->data['bans'][$index];
                }
            }
        }

        foreach ($this->data['patterns'] as [$regex, $index]) {
            if (preg_match($regex, $ip)) {
                return $this->data['bans'][$index];
            }
        }

        if ($this->ipLookup && $this->data['asns'] && $packed !== false) {
            try {
                $asn = (int)preg_replace('/^AS/i', '', (string)($this->ipLookup->getInfo($ip)['asn'] ?? ''));
            } catch (\Throwable) {
                return null;
            }
            if (isset($this->data['asns'][$asn])) {
                return ['ip' => $ip, 'reason' => '', 'timestamp' => $this->data['asns'][$asn], 'type' => 'asn'];
            }
        }

        return null;
    }

    private static function parseRange(string $range): ?array
    {
        if (!str_contains($range, '/')) {
            return null;
        }

        [$network, $bits] = explode('/', $range, 2);
        $packed = @inet_pton($network);
        if ($packed === false || !ctype_digit($bits) || (int)$bits > strlen($packed) * 8) {
            return null;
        }

        return [strlen($packed) . '/' . (int)$bits, bin2hex(self::mask($packed, (int)$bits))];
    }

    private static function mask(string $packed, int $bits): string
    {
        $bytes = intdiv($bits, 8);
        $masked = substr($packed, 0, $bytes);
        if ($bits % 8) {
            $masked .= chr(ord($packed[$bytes]) & (0xFF << (8 - $bits % 8)) & 0xFF);
        }
        return $masked;
    }
}