<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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
     * @var CommentLocation The Comment location type.
     */
    private CommentLocation $type;

    /**
     * @var mixed
     */
    private mixed $id;

    /**
     * @var int 
     */
    private int $count = 0; // stupid? maybe idfk

    /**
     * function __construct
     *
     * @param Database $database
     * @param CommentLocation $type
     * @param int $id
     *
     * @return void
     */
    public function __construct(Database $database, CommentLocation $type, $id = null)
    {
        $this->database = $database;
        $this->type = $type;
        $this->id = $id;
    }

    /**
     * function fetchComments
     * 
     * wait what the fuck is this
     *
     * @param mixed $query
     * @param mixed $params
     *
     * @return mixed
     */
    private function fetchComments($query, $params)
    {
        return $this->database->fetchArray($this->database->query($query, $params));
    }

    //

    /**
     * function getReplies
     * 
     * probably stupid and should be part of getComments. -chaziz 8/26/2023
     *
     * @param mixed $id
     *
     * @return mixed
     */
    public function getReplies($id)
    {
        $database_data = null;

        switch ($this->type) {
            case CommentLocation::Upload:
                $database_data = $this->fetchComments("SELECT c.id, c.location_id, c.comment, c.author, c.timestamp FROM upload_comments c WHERE c.reply_to = ? AND c.author NOT IN (SELECT user FROM user_bans) ORDER BY c.timestamp ASC", [$id]);
                break;
            case CommentLocation::Profile:
                $database_data = $this->fetchComments("SELECT c.id, c.location_id, c.comment, c.author, c.timestamp FROM user_profile_comments c WHERE c.reply_to = ? AND c.author NOT IN (SELECT user FROM user_bans) ORDER BY c.timestamp ASC", [$id]);
                break;
            case CommentLocation::Journal:
                $database_data = $this->fetchComments("SELECT c.id, c.location_id, c.comment, c.author, c.timestamp FROM journal_comments c WHERE c.reply_to = ? AND c.author NOT IN (SELECT user FROM user_bans) ORDER BY c.timestamp ASC", [$id]);
                break;
        }

        $data = [];
        foreach ($database_data as $comment) {
            $this->count++;
            $author = new UserData($this->database, $comment["author"]);
            $data[$comment["id"]] = [
                "id" => $comment["id"],
                "posted_id" => $comment["id"],
                "post" => $comment["comment"],
                "posted" => $comment["timestamp"],
                "author" => [
                    "id" => $comment["author"],
                    "info" => $author->getUserArray(),
                ],
                "replies" => $this->getReplies($comment["id"]) // recursive call to get nested replies
            ];
        }
        return $data;
    }

    /**
     * function getCommentCount
     * 
     * @todo rework this completely
     *
     * @return mixed
     */
    public function getCommentCount()
    {
        return $this->count;
    }

    /**
     * function getComments
     * 
     * Fetches all of the comments
     *
     * @param mixed $limit
     *
     * @return mixed
     */
    public function getComments($limit = 0)
    {
        $database_data = null;
        $limit_clause = $limit > 0 ? "LIMIT ?" : "";

        switch ($this->type) {
            case CommentLocation::Upload:
                $query = "SELECT c.id, c.location_id, c.comment, c.author, c.timestamp 
                      FROM upload_comments c 
                      WHERE c.location_id = ? AND c.reply_to = 0
                      AND c.author NOT IN (SELECT user FROM user_bans)
                      ORDER BY c.timestamp DESC " . $limit_clause;
                $params = $limit > 0 ? [$this->id, $limit] : [$this->id];
                $database_data = $this->fetchComments($query, $params);
                break;

            case CommentLocation::Profile:
                $query = "SELECT c.id, c.location_id, c.comment, c.author, c.timestamp 
                      FROM user_profile_comments c 
                      WHERE c.location_id = ? AND c.reply_to = 0
                      AND c.author NOT IN (SELECT user FROM user_bans)
                      ORDER BY c.timestamp DESC " . $limit_clause;
                $params = $limit > 0 ? [$this->id, $limit] : [$this->id];
                $database_data = $this->fetchComments($query, $params);
                break;

            case CommentLocation::Journal:
                $query = "SELECT c.id, c.location_id, c.comment, c.author, c.timestamp 
                      FROM journal_comments c 
                      WHERE c.location_id = ? AND c.reply_to = 0
                      AND c.author NOT IN (SELECT user FROM user_bans)
                      ORDER BY c.timestamp DESC " . $limit_clause;
                $params = $limit > 0 ? [$this->id, $limit] : [$this->id];
                $database_data = $this->fetchComments($query, $params);
                break;
        }


        $data = [];
        foreach ($database_data as $comment) {
            $this->count++;
            $author = new UserData($this->database, $comment["author"]);
            $data[$comment["id"]] = [
                "id" => $comment["id"],
                "posted_id" => $comment["id"],
                "post" => $comment["comment"],
                "posted" => $comment["timestamp"],
                "author" => [
                    "id" => $comment["author"],
                    "info" => $author->getUserArray(),
                ],
                "replies" => $this->getReplies($comment["id"]),
            ];
        }
        return $data;
    }
}
