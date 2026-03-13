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

namespace OpenSB;

use Core\Database;

/**
 * class CommentData
 *
 * Handles the comments on uploads, profiles and journals.
 */
class CommentData
{
    /**
     * @var Database The Database class
     */
    private Database $database;

    /**
     * @var CommentLocation The Comment location type
     */
    private CommentLocation $type;

    /**
     * @var int|string|null The location ID
     */
    private int|string|null $locationId;

    /**
     * function __construct
     *
     * @param Database $database
     * @param CommentLocation $type
     * @param int|string|null $id
     *
     * @return void
     */
    public function __construct(Database $database, CommentLocation $type, $locationId = null)
    {
        $this->database = $database;
        $this->type = $type;
        $this->locationId = $locationId;
    }

    /**
     * function getTable
     * 
     * Get which table we'll be fetching from depending on the location type
     * 
     * @return string
     */
    private function getTable(): string
    {
        return match ($this->type) {
            CommentLocation::Upload  => 'upload_comments',
            CommentLocation::Profile => 'user_profile_comments',
            CommentLocation::Journal => 'journal_comments',
        };
    }

    /**
     * getLocationAuthor
     * 
     * Get the author of the location itself.
     */
    private function getLocationAuthor(): int
    {
        return match ($this->type) {

            CommentLocation::Upload => (
                $this->database->fetch(
                    "SELECT author FROM uploads WHERE upload_id = ?", // weird ass shit
                    [$this->locationId]
                )['author'] ?? 0
            ),

            CommentLocation::Profile => $this->locationId,

            CommentLocation::Journal => (
                $this->database->fetch(
                    "SELECT author FROM journals WHERE id = ?",
                    [$this->locationId]
                )['author'] ?? 0
            ),
        };
    }

    /**
     * function getComments
     * 
     * Fetches all of the comments
     *
     * @param int $limit
     *
     * @return array
     */
    public function getComments(int $limit = 0): array
    {
        $table = $this->getTable();

        $locationAuthor = $this->getLocationAuthor();

        $limitClause = $limit > 0 ? "LIMIT ?" : "";

        $query = "
            SELECT c.id, c.location_id, c.reply_to, c.comment, c.author, c.timestamp
            FROM {$table} c
            WHERE c.location_id = ?
            AND c.author NOT IN (SELECT user FROM user_bans)
            ORDER BY c.timestamp DESC
            {$limitClause}
        ";

        $params = $limit > 0
            ? [$this->locationId, $limit]
            : [$this->locationId];

        $comments_from_db = $this->database->fetchArray(
            $this->database->query($query, $params)
        ) ?? [];

        $comments = [];
        $lookup = [];

        // organize the data
        foreach ($comments_from_db as $comment) {
            $author = new UserData($this->database, $comment['author']);

            $lookup[$comment['id']] = [
                'id' => $comment['id'],
                'posted_id' => $comment['id'],
                'post' => $comment['comment'],
                'posted' => $comment['timestamp'],
                'author' => [
                    'id' => $comment['author'],
                    'is_owner' => $locationAuthor == $comment['author'],
                    'info' => $author->getUserArray(),
                ],
                'replies' => [],
                'reply_to' => $comment['reply_to'],
            ];
        }

        // then handle the replies
        foreach ($lookup as $id => &$comment) {
            if ($comment['reply_to'] == 0) {
                $comments[$id] = &$comment;
            } else {
                $parentId = $comment['reply_to'];
                if (isset($lookup[$parentId])) {
                    $lookup[$parentId]['replies'][$id] = &$comment;
                }
            }
        }

        // but we don't need this at all in the frontend
        foreach ($lookup as &$comment) {
            unset($comment['reply_to']);
        }

        return $comments;
    }

    /**
     * function getCommentCount
     * 
     * Get total comment count.
     * 
     * @return int
     */
    public function getCommentCount(): int
    {
        $table = $this->getTable();

        $result = $this->database->fetch(
            "SELECT COUNT(*) AS count
                FROM {$table}
                WHERE location_id = ?
                AND author NOT IN (SELECT user FROM user_bans)",
            [$this->locationId]
        );

        return (int) ($result['count'] ?? 0);
    }
}
