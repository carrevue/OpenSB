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
 * class BotInterface
 *
 * Aims to provide a socket interface for OpenSB to send input data to a
 * bot.
 * 
 * @note Output is unlikely to be handled by this class.
 */
class BotInterface {
    private string $host;
    private int $port;
    private $socket = null;
    private int $timeout;

    public function __construct(string $host = "127.0.0.1", int $port = 47101, int $timeout = 5)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    /**
     * Connect to the bot
     */
    public function connect(): bool
    {
        $errNo = 0;
        $errStr = '';

        $this->socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errNo,
            $errStr,
            $this->timeout
        );

        if (!$this->socket) {
            throw new \Exception("Could not connect to bot: $errStr ($errNo)");
        }

        stream_set_blocking($this->socket, true);

        return true;
    }

    /**
     * Send data to the bot
     */
    public function send(string $data): void
    {
        if (!$this->socket) {
            throw new \Exception("Socket not connected");
        }

        fwrite($this->socket, $data);
    }

    /**
     * Close the connection to the bot
     */
    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}