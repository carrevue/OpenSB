<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

namespace Actions;

use Core\Utilities;
use Data\Comment\CommentLocation;
use Data\Notification\NotificationEnum;
use Data\Upload\UploadFlags;
use Data\User\UserData;

class CommentActions extends BaseActions
{
    public function post(CommentLocation $location, string|int $id, string $text, int $replyTo = 0): array|ActionError
    {
        $user = $this->requireVerified();

        if ($user instanceof ActionError) {
            return $user;
        }

        $text = trim($text);

        if ($text === "") {
            return ActionError::CommentEmpty;
        }

        if (strlen($text) > 1000) {
            return ActionError::CommentTooLong;
        }

        if (!$this->sb->isDebug()) {
            $timeLimit = time() - 15;
            if (
                $this->database->result("SELECT COUNT(*) FROM upload_comments WHERE timestamp > ? AND author = ?", [$timeLimit, $user]) ||
                $this->database->result("SELECT COUNT(*) FROM user_profile_comments WHERE timestamp > ? AND author = ?", [$timeLimit, $user]) ||
                $this->database->result("SELECT COUNT(*) FROM journal_comments WHERE timestamp > ? AND author = ?", [$timeLimit, $user])
            ) {
                return ActionError::CommentRatelimit;
            }
        }

        if ($location === CommentLocation::Upload) {
            $upload_flags = UploadFlags::toArray($this->database->result("SELECT flags from uploads where upload_id = ?", [$id]));

            if ($upload_flags["block_comments"]) {
                return ActionError::CommentsDisabled;
            }
        }

        $table = match ($location) {
            CommentLocation::Upload => 'upload_comments',
            CommentLocation::Profile => 'user_profile_comments',
            CommentLocation::Journal => 'journal_comments',
        };

        $this->database->query(
            "INSERT INTO {$table} (location_id, reply_to, comment, author, timestamp) VALUES (?,?,?,?,?)",
            [$id, $replyTo, $text, $user, time()]
        );

        // we need the insertid right before we call anything else to avoid a "trying to access array offset on false" warning.
        $insertID = $this->database->insertID();

        // not gonna put this code in the first switch case YET due to a weird ass hack i have to do with upload comments

        // for replies, this should most likely be recursive and notify everyone in a 
        // comment reply thread, but that's going to be for opensb 2.1. -chaziz 9/18/2025
        switch ($location) {
            case CommentLocation::Upload:
                // comments use the upload's string id and not the numeric id as the location of an upload, so we have to do
                // this weird shit.
                $numericID = Utilities::uploadStringIDToUploadNumericID($this->database, $id);

                if ($replyTo) {
                    // get author of the comment we're replying to
                    $recipient = $this->database->result("SELECT author FROM upload_comments WHERE id = ?", [$replyTo]);
                } else {
                    // get author of the upload
                    $recipient = $this->database->result("SELECT author FROM uploads WHERE upload_id = ?", [$id]);
                }

                if ($recipient != $user) {
                    Utilities::notifyUser($this->database, $recipient, $numericID, $insertID, NotificationEnum::CommentUpload);
                }
                break;
            case CommentLocation::Profile:
                if ($replyTo) {
                    // get author of the comment we're replying to
                    $recipient = $this->database->result("SELECT author FROM user_profile_comments WHERE id = ?", [$replyTo]);
                } else {
                    // get author of the upload
                    $recipient = $id;
                }

                if ($recipient != $user) {
                    Utilities::notifyUser($this->database, $recipient, $id, $insertID, NotificationEnum::CommentProfile);
                }
                break;
            case CommentLocation::Journal:
                if ($replyTo) {
                    // get author of the comment we're replying to
                    $recipient = $this->database->result("SELECT author FROM journal_comments WHERE id = ?", [$replyTo]);
                } else {
                    // get author of the upload
                    $recipient = $this->database->result("SELECT author FROM journals WHERE id = ?", [$id]);
                }

                if ($recipient != $user) {
                    Utilities::notifyUser($this->database, $recipient, $id, $insertID, NotificationEnum::CommentJournal);
                }
                break;
        }

        if ($this->sb->isWebhookLoggerEnabled()) {
            $data = [
                'id' => $insertID,
                'location_id' => $id,
                'contents' => $text,
                'author' => $this->auth->getUserData()["name"],
                'type' => strtolower($location->name),
            ];

            $this->sb->getWebhookLoggerClass()->newCommentHook($data);
        }

        $this->auth->bumpLastActive();

        $author = new UserData($this->database, $user);

        return [
            "id" => $insertID,
            "posted_id" => $id,
            "post" => $text,
            "posted" => time(),
            "author" => [
                "id" => $user,
                "info" => $author->getUserArray(),
            ],
            "replies" => []
        ];
    }
}
