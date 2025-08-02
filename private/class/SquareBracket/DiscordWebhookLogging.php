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

use BluffingoCore\Database;
use BluffingoCore\CoreUtilities;

use SquareBracket\Utilities;

use \DiscordWebhooks\Client;
use \DiscordWebhooks\Embed;

class DiscordWebhookLogging
{
    private Database $database;
    private string $url;
    private string $footer_text;
    private ?string $domain;
    private ?Client $webhook = null;

    public function __construct(SquareBracket $orange, $url)
    {
        $this->database = $orange->getDatabaseClass();

        $this->url = $url;

        $this->footer_text = $orange->getBrandingSettings()["name"]
            . ' / OpenSB ' . (new VersionNumber())->getVersionString();

        $this->domain = CoreUtilities::getURL(false);
    }

    public function initClient()
    {
        if (!$this->webhook instanceof Client) {
            $this->webhook = new Client($this->url);
        }
    }

    /**
     * Trigger the new upload webhook.
     *
     * @param array $data Array with the necessary data.
     */
    public function newUploadHook($data)
    {
        $this->initClient();

        $title = $data['name'] . ' (' . $data['id'] . ')';

        $description = $data['description'] ?? 'No description';
        if (strlen($description) > 500) {
            $description = substr($description, 0, 497) . '...';
        }

        $author = 'New upload by ' . $data['author'];

        $uploadUrl = sprintf("%s/view/%s", $this->domain, $data['id']);

        $mbd = new Embed();

        $mbd->title($title)
            ->description($description)
            ->url($uploadUrl)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::PRIMARY_COLOR);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * Trigger the new journal webhook.
     *
     * @param array $data Array with the necessary data.
     */
    public function newJournalHook($data)
    {
        $this->initClient();

        $title = $data['name'] . ' (' . $data['id'] . ')';

        $description = $data['description'] ?? 'No description';
        if (strlen($description) > 500) {
            $description = substr($description, 0, 497) . '...';
        }

        if ($data["is_news"]) {
            $author = 'New announcement journal by ' . $data['author'];
        } else {
            $author = 'New journal by ' . $data['author'];
        }

        $uploadUrl = sprintf("%s/read/%s", $this->domain, $data['id']);

        $mbd = new Embed();

        $mbd->title($title)
            ->description($description)
            ->url($uploadUrl)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::PRIMARY_COLOR);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * Trigger the new comment webhook.
     *
     * @param array $data Array with the necessary data.
     */
    public function newCommentHook($data, $is_legacy_api = false)
    {
        $this->initClient();

        $description = $data['contents'] ?? 'No contents???';
        if (strlen($description) > 500) {
            $description = substr($description, 0, 497) . '...';
        }

        switch ($data['type']) {
            case 'video': // legacy api
            case 'submission':
                $title = Utilities::uploadStringIDToUploadTitle($this->database, $data['name']);
                $author = 'New upload comment by ' . $data['author'];
                $uploadUrl = sprintf("%s/view/%s", $this->domain, $data['name']);
                break;
            case 'profile':
                $title = Utilities::userIDToUsername($this->database, $data['name']);
                $author = 'New profile comment by ' . $data['author'];
                $uploadUrl = sprintf("%s/user/%s", $this->domain, $data['name']);
                break;
            case 'journal':
                $title = Utilities::journalIDtoJournalTitle($this->database, $data['name']);
                $author = 'New journal comment by ' . $data['author'];
                $uploadUrl = sprintf("%s/read/%s", $this->domain, $data['name']);
                break;
            default:
                exit;
        }

        $mbd = new Embed();

        $mbd->title($title)
            ->description($description)
            ->url($uploadUrl)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::PRIMARY_COLOR);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * Trigger the upload processing worker fail webhook.
     *
     * @param array $data Array with the necessary data.
     */
    public function uploadProcessingWorkerSuccessHook($data)
    {
        $this->initClient();

        $title = $data['id'] . ' successfully processed.';

        $author = 'Processing worker';

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::SUCCESS_COLOR);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * Trigger the upload processing worker fail webhook.
     *
     * @param array $data Array with the necessary data.
     */
    public function uploadProcessingWorkerFailHook($data)
    {
        $this->initClient();

        $title = $data['id'] . ' failed to process.';

        $author = 'Processing worker';

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::DANGER_COLOR);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * Trigger the recount views hook.
     */
    public function recountViewsHook()
    {
        $this->initClient();

        $title = 'Views have been recounted.';

        $author = 'OpenSB (automatic)';

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::SUCCESS_COLOR);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * Trigger the new user webhook.
     *
     * @param array $data Array with the necessary data.
     */
    function newUserHook($data)
    {
        $this->initClient();

        $title = 'New account created';

        $author = $data['username'];

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::PRIMARY_COLOR);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * Trigger the dashboard ban user webhook.
     *
     * @param array $data Array with the necessary data.
     */
    function dashboardBanUserHook($data)
    {
        $this->initClient();

        if ($data['unbanned']) {
            $author = 'User unbanned by ' . $data['author'];
            $color = Colors::WARNING_COLOR;
        } else {
            $author = 'User banned by ' . $data['author'];
            $color = Colors::DANGER_COLOR;
        }

        $title = $data['user']; //Utilities::userIDToUsername($this->database, $data['user']);

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color($color);

        $this->webhook->embed($mbd)->send();
    }
}
