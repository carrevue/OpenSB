<?php

namespace SquareBracket;

class UploadQuery
{
    private $database;
    private $whereRatings;
    private $whereTagBlacklist;

    public function __construct($database) {
        $this->database = $database;
        $this->whereRatings = Utilities::whereRatings();
        $this->whereTagBlacklist = Utilities::whereTagBlacklist();
    }

    public function query($order, $limit, $whereCondition = null, $params = [], $adminPanel = false) {
        $query = "SELECT v.* FROM uploads v";
        $whereClauses = [];

        if (!$adminPanel) {
            // if upload isnt taken down
            $whereClauses[] = "v.video_id NOT IN (SELECT submission FROM upload_takedowns)";
            // if upload does not belong to someone who has been banned
            $whereClauses[] = "v.author NOT IN (SELECT userid FROM user_bans)";
        }

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        if (!$adminPanel) {
            if (!empty($this->whereRatings)) {
                $whereClauses[] = $this->whereRatings;
            }

            if (!empty($this->whereTagBlacklist)) {
                $whereClauses[] = $this->whereTagBlacklist;
            }
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $query .= " ORDER BY $order LIMIT $limit";

        return $this->database->fetchArray($this->database->query($query, $params));
    }

    // used in the browse page
    public function count($whereCondition = null, $params = []) {
        $query = "
        SELECT COUNT(*)
        FROM uploads v
        WHERE v.video_id NOT IN (SELECT submission FROM upload_takedowns)
        AND v.author NOT IN (SELECT userid FROM user_bans)
        ";

        if (!empty($whereCondition)) {
            $query .= "AND $whereCondition ";
        }

        if (!empty($this->whereRatings)) {
            $query .= "AND $this->whereRatings ";
        }

        if (!empty($this->whereTagBlacklist)) {
            $query .= "AND $this->whereTagBlacklist ";
        }

        return $this->database->result($query, $params);
    }
}