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

namespace Pages;

global $auth, $database, $sb;

use Data\Upload\UploadFlags;
use Data\Upload\UploadQuery;
use Data\User\UserFlags;

$upload_query = new UploadQuery($sb);

function get_N_featured_uploads($upload_query, $amount) {
    return $upload_query->query(
        "uploaded DESC",
        $amount,
        sprintf("v.flags & %d = %d", UploadFlags::FLAG_FEATURED->value, UploadFlags::FLAG_FEATURED->value)
    )->toCleanArray();
}

function get_top_N_users($database, $amount, $minimum_uploads, $limit = null): array {
    $sql = "SELECT u.id, u.name, u.title, lu.last_upload
        FROM users u
        JOIN (
            SELECT author, MAX(timestamp) AS last_upload
            FROM uploads
            WHERE visibility = 0
            GROUP BY author
        ) lu ON lu.author = u.id
        WHERE u.u_index >= ?
        AND (
            (u.flags & ?) != ?
        )
        AND (
            (u.f_index >= (SELECT MIN(f_index) FROM (SELECT f_index FROM users ORDER BY f_index DESC LIMIT ?) t))
            OR (u.flags & ?) = ?
        )
        AND (
            lu.last_upload > ?
        )
        AND (
            u.id NOT IN (SELECT user FROM user_bans)
        )";

    $params = [
        $minimum_uploads,
        UserFlags::FLAG_SHADOW_BAN->value, UserFlags::FLAG_SHADOW_BAN->value,
        $amount,
        UserFlags::FLAG_FEATURED->value, UserFlags::FLAG_FEATURED->value,
        strtotime('-1 month'),
    ];

    if ($limit !== null) {
        $sql .= " ORDER BY RAND() LIMIT ?";
        $params[] = $limit;
    }

    return $database->fetchArray($database->query($sql, $params));
}

function get_N_new_uploads($database, $upload_query, $max, $limit) {
    // select users if they
    // 1. have uploaded at least once
    // 2. are not shadowbanned (this appears to be broken)
    // 3. are in the top 100 of being most followed or are featured
    // 4. have uploaded something within the last month
    // 5. are not banned
    $featured_users = get_top_N_users($database, 100, 1);

    $users = array_map('intval', array_column($featured_users, 'id'));
    $query = implode(', ', $users);

    $uploads_new = $upload_query->query(
        "RAND()",
        $limit * 6,
        sprintf(
            "v.author IN (%s) AND v.timestamp > %d AND views > 1",
            $query,
            strtotime('-7 days')
        )
    )->toCleanArray();

    return organize_uploads($uploads_new, $max, $limit);
}

function organize_uploads($rows, $max, $limit): array {
    $seen = [];
    $array = [];

    // avoid showing more than 4 uploads per author
    foreach ($rows as $row) {
        $author = is_array($row['author']) ? ($row['author']['id'] ?? null) : $row['author'];
        if ($author === null) {
            continue;
        }

        $seen[$author] = ($seen[$author] ?? 0) + 1;
        if ($seen[$author] > $max) {
            continue;
        }

        $array[] = $row;
        if (count($array) >= $limit) {
            break;
        }
    }

    shuffle($array);
    return $array;
}