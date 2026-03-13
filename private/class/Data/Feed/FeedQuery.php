<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

namespace Data\Feed;

use Core\SquareBracket;
use Core\Database;
use Core\Authentication;

use Data\Upload\UploadQuery;
use Data\Post\PostQuery;

/**
 * class FeedQuery
 * 
 * Gets the feed data. This is a hybrid of UploadQuery and PostQuery.
 */
class FeedQuery
{
    /**
     * @var SquareBracket
     */
    private SquareBracket $sb;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     *
     * @return void
     */
    public function __construct(SquareBracket $sb)
    {
        $this->sb = $sb;
    }

    /**
     * function query
     *
     * @param string $order
     * @param int $limit
     * @param string $whereCondition
     * @param array $params
     * @param bool $adminPanel
     *
     * @return FeedResult
     */
    public function query($order, $limit, $whereCondition = null, $params = [], $adminPanel = false)
    {
        preg_match('/LIMIT\s*(\d+)(?:\s*OFFSET\s*(\d+))?/i', str_contains($limit, "LIMIT") ? $limit : "LIMIT $limit", $matches);
        $actualLimit = (int)($matches[1] ?? $limit);
        $offset = (int)($matches[2] ?? 0);
        $fetchLimit = $actualLimit + $offset;

        $postQuery = new PostQuery($this->sb);
        $uploadQuery = new UploadQuery($this->sb);

        $posts    = array_map(fn($r) => ['item_type' => 'post'] + $r, $postQuery->query($order, (string)$fetchLimit, $whereCondition, $params, $adminPanel)->raw());
        $uploads  = array_map(fn($r) => ['item_type' => 'upload'] + $r, $uploadQuery->query($order, (string)$fetchLimit, $whereCondition, $params, $adminPanel)->raw());

        $combined = array_merge($posts, $uploads);
        usort($combined, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
        $combined = array_slice($combined, $offset, $actualLimit);

        return new FeedResult($this->sb->getDatabaseClass(), $combined);
    }
}