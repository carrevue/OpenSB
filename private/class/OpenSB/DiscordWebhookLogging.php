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

namespace OpenSB;

use OpenSB\Database;
use OpenSB\Utilities;

use \DiscordWebhooks\Client;
use \DiscordWebhooks\Embed;

/**
 * class DiscordWebhookLogging
 * 
 * The Discord Webhook Logging class.
 */
class DiscordWebhookLogging
{
    /**
     * @var Database The database class.
     */
    private Database $database;

    /**
     * @var string
     */
    private string $url;

    /**
     * @var string
     */
    private string $footer_text;

    /**
     * @var string
     */
    private ?string $domain;

    /**
     * @var Client
     */
    private ?Client $webhook = null;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     * @param mixed $url
     *
     * @return void
     */
    public function __construct(SquareBracket $sb, $url)
    {
        $this->database = $sb->getDatabaseClass();

        $this->url = $url;

        $this->footer_text = $sb->getBrandingSettings()["name"]
            . ' / OpenSB ' . (new VersionNumber())->getVersionString();

        $this->domain = Utilities::getURL(false);
    }

    /**
     * function initClient
     *
     * @return void
     */
    public function initClient()
    {
        if (!$this->webhook instanceof Client) {
            $this->webhook = new Client($this->url);
        }
    }

    /**
     * function newUploadHook
     *
     * Trigger the new upload webhook.
     *
     * @param mixed $data
     *
     * @return void
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
            ->color(Colors::PRIMARY);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * function newJournalHook
     *
     * Trigger the new journal webhook.
     *
     * @param mixed $data
     *
     * @return void
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
            ->color(Colors::PRIMARY);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * function newCommentHook
     *
     * Trigger the new comment webhook.
     *
     * @param mixed $data
     *
     * @return void
     */
    public function newCommentHook($data)
    {
        $this->initClient();

        $description = $data['contents'] ?? 'No contents???';
        if (strlen($description) > 500) {
            $description = substr($description, 0, 497) . '...';
        }

        switch ($data['type']) {
            case 'video': // legacy api
            case 'upload':
                $title = Utilities::uploadStringIDToUploadTitle($this->database, $data['location_id']);
                $author = 'New upload comment by ' . $data['author'];
                $uploadUrl = sprintf("%s/view/%s", $this->domain, $data['location_id']);
                break;
            case 'profile':
                $title = Utilities::userIDToUsername($this->database, $data['location_id']);
                $author = 'New profile comment by ' . $data['author'];
                $uploadUrl = sprintf("%s/user/%s", $this->domain, $title);
                break;
            case 'journal':
                $title = Utilities::journalIDtoJournalTitle($this->database, $data['location_id']);
                $author = 'New journal comment by ' . $data['author'];
                $uploadUrl = sprintf("%s/read/%s", $this->domain, $data['location_id']);
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
            ->color(Colors::PRIMARY);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * function uploadProcessorSuccessHook
     *
     * Trigger the upload processor fail webhook.
     *
     * @param mixed $data
     *
     * @return void
     */
    public function uploadProcessorSuccessHook($data)
    {
        $this->initClient();

        $title = $data['id'] . ' successfully processed.';

        $author = 'Upload processor';

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::SUCCESS);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * function uploadProcessorFailHook
     *
     * Trigger the upload processor fail webhook.
     *
     * @param mixed $data
     *
     * @return void
     */
    public function uploadProcessorFailHook($data)
    {
        $this->initClient();

        $title = $data['id'] . ' failed to process.';

        $author = 'Upload processor';

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::DANGER);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * function recountViewsHook
     *
     * Trigger the recount views hook.
     *
     * @return void
     */
    public function recountViewsHook()
    {
        $this->initClient();

        $title = 'Views have been recounted.';

        $username = exec('whoami');
        $hostname = gethostname();

        if ($username !== false && $hostname !== false) {
            $author = $username . '@' . $hostname;
        } else {
            $author = 'OpenSB';
        }

        $mbd = new Embed();

        $mbd->title($title)
            ->author($author)
            ->footer($this->footer_text)
            ->color(Colors::SUCCESS);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * function newUserHook
     *
     * Trigger the new user webhook.
     *
     * @param mixed $data
     *
     * @return void
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
            ->color(Colors::PRIMARY);

        $this->webhook->embed($mbd)->send();
    }

    /**
     * function dashboardUserHook
     *
     * Trigger the dashboard user webhook.
     *
     * @param mixed $data
     *
     * @return void
     */
    function dashboardUserHook($data)
    {
        $this->initClient();

        switch ($data['action']) {
            case "banned":
                $author = 'User banned by ' . $data['author'];
                $color = Colors::DANGER;
                break;
            case "unbanned":
                $author = 'User unbanned by ' . $data['author'];
                $color = Colors::WARNING;
                break;
            case "verified":
                $author = 'User verified by ' . $data['author'];
                $color = Colors::SUCCESS;
                break;
            case "unverified":
                $author = 'User unverified by ' . $data['author'];
                $color = Colors::DANGER;
                break;
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
