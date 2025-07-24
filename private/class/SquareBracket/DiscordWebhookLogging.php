<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021 ROllerozxa

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

use BluffingoCore\CoreUtilities;

use \DiscordWebhooks\Client;
use \DiscordWebhooks\Embed;

class DiscordWebhookLogging
{
    private string $url;
    private string $instance_name;
    private string $domain;
    private ?Client $webhook = null;

    public function __construct(SquareBracket $orange, $url)
    {
        $this->url = $url;
        $this->instance_name = $orange->getBrandingSettings()["name"];
        $this->domain = CoreUtilities::getURL(false);
    }

    private function initClient()
    {
        if (!$this->webhook instanceof Client) {
            $this->webhook = new Client($this->url);
        }
    }

    /**
     * Trigger the new upload webhook.
     *
     * @param array $upload Upload array with the necessary data.
     */
    public function newUploadHook($upload)
    {
        $this->initClient();

        $title = $upload['name'] . ' (' . $upload['video_id'] . ')';

        $description = $upload['description'] ?? 'No description';
        if (strlen($description) > 500) {
            $description = substr($description, 0, 497) . '...';
        }

        $author = 'New upload by ' . $upload['author'];

        $uploadUrl = sprintf("%s/view/%s", $this->domain, $upload['video_id']);

        $mbd = new Embed();

        $mbd->title($title)
            ->description($description)
            ->url($uploadUrl)
            ->author($author)
            ->footer($this->instance_name);

        $this->webhook->embed($mbd)->send();
    }

    function newUserHook($user)
    {
        throw new \Exception('Not implemented');
    }
}
