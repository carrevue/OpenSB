<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

namespace Data\Recommendation;

use Core\Database;
use Data\Upload\UploadFlags;
use Data\Upload\UploadQuery;
use Data\Upload\UploadResult;
use Data\Upload\UploadVisibilityEnum;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * class RecommendationQuery
 *
 * Fetches recommendations based on the generated upload_recommendations table.
 */
class RecommendationQuery
{
    private const CANDIDATES = 60;
    private const MAX_PER_AUTHOR = 2;
    private const RANDOMNESS = 0.35;

    private const MAX_HISTORY = 40;
    private const HISTORY_HALF_LIFE_DAYS = 14;
    private const LAST_VISIT_WINDOW = 6 * 3600;
    private const LAST_VISIT_BOOST = 3.0;
    private const FOLLOWING_BOOST = 1.3;
    private const NEW_UPLOAD_BOOST = 0.5;
    private const NEW_UPLOAD_DAYS = 7;

    private Database $database;
    private ?UploadQuery $uploadQuery;
    private Randomizer $random;

    /**
     * $uploadQuery and $seed are optional.
     */
    public function __construct(Database $database, ?UploadQuery $uploadQuery = null, ?int $seed = null)
    {
        $this->database = $database;
        $this->uploadQuery = $uploadQuery;
        $this->random = new Randomizer($seed === null ? null : new Mt19937($seed));
    }

    public function related(array $upload, int $limit = 20): array
    {
        $id = (int)$upload['id'];
        $candidates = $this->getSaved($id);

        if (!$candidates) {
            $candidates = $this->relatedByTags($id);
        }

        return $this->finish($candidates, $limit);
    }

    public function getSaved(int $uploadId, int $limit = self::CANDIDATES): array
    {
        $rows = $this->database->fetchArray($this->database->query(
            "SELECT related, score FROM upload_recommendations WHERE upload = ? ORDER BY score DESC LIMIT " . $limit,
            [$uploadId]
        ));

        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['related']] = (float)$row['score'];
        }
        return $out;
    }

    public function relatedByTags(int $uploadId, int $limit = self::CANDIDATES): array
    {
        $rows = $this->database->fetchArray($this->database->query(
            "SELECT other.upload_id AS related, SUM(1 / LOG(2 + tag_uses.uses)) AS score
             FROM upload_tag_index mine
             JOIN upload_tag_index other ON other.tag_id = mine.tag_id AND other.upload_id != mine.upload_id
             JOIN (SELECT tag_id, COUNT(*) AS uses FROM upload_tag_index GROUP BY tag_id) tag_uses ON tag_uses.tag_id = mine.tag_id
             WHERE mine.upload_id = ?
             GROUP BY other.upload_id
             ORDER BY score DESC
             LIMIT " . $limit,
            [$uploadId]
        ));

        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['related']] = (float)$row['score'];
        }
        return $out;
    }

    public function forUser(?int $userId, string $ip, int $limit = 20): array
    {
        $scores = $this->forUserScores($userId, $ip);
        arsort($scores);

        return $this->finish(array_slice($scores, 0, $limit * 3, true), $limit);
    }

    public function forUserScores(?int $userId, string $ip): array
    {
        $now = time();
        $history = [];
        $seen = [];
        $info = [];
        $following = [];

        $views = $this->database->fetchArray($this->database->query(
            "SELECT v.id, uv.timestamp
             FROM upload_views uv JOIN uploads v ON v.upload_id = uv.upload_id
             WHERE uv.user = ?
             ORDER BY uv.timestamp DESC
             LIMIT 500",
            [$ip]
        ));

        $lastView = $views ? (int)$views[0]['timestamp'] : 0;
        foreach ($views as $i => $row) {
            $id = (int)$row['id'];
            $seen[$id] = true;

            if ($i < self::MAX_HISTORY) {
                $time = (int)$row['timestamp'];
                $weight = 0.5 ** ((max(0, $now - $time) / 86400) / self::HISTORY_HALF_LIFE_DAYS);
                if ($lastView - $time <= self::LAST_VISIT_WINDOW) {
                    $weight *= self::LAST_VISIT_BOOST;
                }
                $history[$id] = max($history[$id] ?? 0, $weight);
            }
        }

        if ($userId !== null) {
            $ratings = $this->database->fetchArray($this->database->query(
                "SELECT upload, rating FROM upload_ratings WHERE user = ?",
                [$userId]
            ));
            foreach ($ratings as $row) {
                $id = (int)$row['upload'];
                $rating = (int)$row['rating'];
                $seen[$id] = true;

                if ($rating >= 4) {
                    $history[$id] = max($history[$id] ?? 0, $rating === 5 ? 1.5 : 1.2);
                } elseif ($rating <= 2) {
                    unset($history[$id]);
                }
            }

            $comments = $this->database->fetchArray($this->database->query(
                "SELECT v.id FROM upload_comments c JOIN uploads v ON v.upload_id = c.location_id
                 WHERE c.author = ? ORDER BY c.timestamp DESC LIMIT 50",
                [$userId]
            ));
            foreach ($comments as $row) {
                $id = (int)$row['id'];
                $seen[$id] = true;
                $history[$id] = max($history[$id] ?? 0, 1.2);
            }

            $own = $this->database->fetchArray($this->database->query("SELECT id FROM uploads WHERE author = ?", [$userId]));
            foreach ($own as $row) {
                $seen[(int)$row['id']] = true;
                unset($history[(int)$row['id']]);
            }

            // the schema comments on user_follows are fucked. id is who's being followed
            $follows = $this->database->fetchArray($this->database->query("SELECT id FROM user_follows WHERE user = ?", [$userId]));
            foreach ($follows as $row) {
                $following[(int)$row['id']] = true;
            }
        }

        if (!$history && !$following) {
            return [];
        }

        arsort($history);
        $history = array_slice($history, 0, self::MAX_HISTORY, true);

        $scores = [];
        if ($history) {
            $placeholders = implode(',', array_fill(0, count($history), '?'));
            $rows = $this->database->fetchArray($this->database->query(
                "SELECT r.upload, r.related, r.score, v.author, COALESCE(NULLIF(v.original_timestamp, 0), v.timestamp) AS uploaded
                 FROM upload_recommendations r JOIN uploads v ON v.id = r.related
                 WHERE r.upload IN ($placeholders)",
                array_keys($history)
            ));

            foreach ($rows as $row) {
                $id = (int)$row['related'];
                if (!isset($seen[$id])) {
                    $scores[$id] = ($scores[$id] ?? 0) + $history[(int)$row['upload']] * (float)$row['score'];
                    $info[$id] = [(int)$row['author'], (int)$row['uploaded']];
                }
            }
        }

        if ($following) {
            $base = $scores ? max($scores) * 0.5 : 1.0;
            $placeholders = implode(',', array_fill(0, count($following), '?'));
            $rows = $this->database->fetchArray($this->database->query(
                "SELECT id, author, COALESCE(NULLIF(original_timestamp, 0), timestamp) AS uploaded
                 FROM uploads WHERE author IN ($placeholders) AND timestamp > ? AND visibility = ?
                 ORDER BY timestamp DESC LIMIT 30",
                [...array_keys($following), $now - 14 * 86400, UploadVisibilityEnum::Public->value]
            ));

            foreach ($rows as $row) {
                $id = (int)$row['id'];
                if (!isset($seen[$id]) && !isset($scores[$id])) {
                    $scores[$id] = $base;
                    $info[$id] = [(int)$row['author'], (int)$row['uploaded']];
                }
            }
        }

        foreach ($scores as $id => $score) {
            [$author, $uploaded] = $info[$id];

            if (isset($following[$author])) {
                $score *= self::FOLLOWING_BOOST;
            }

            $ageDays = max(0, $now - $uploaded) / 86400;
            $scores[$id] = $score * (1 + self::NEW_UPLOAD_BOOST * exp(-$ageDays / self::NEW_UPLOAD_DAYS));
        }

        return $scores;
    }

    public function pick(array $scores, array $authors, int $limit, float $randomness = self::RANDOMNESS): array
    {
        $shuffled = [];
        foreach ($scores as $id => $score) {
            $roll = $this->random->getInt(0, 1000000) / 1000000;
            $shuffled[$id] = $score * (1 + $randomness * ($roll - 0.5));
        }
        arsort($shuffled);

        $picked = [];
        $leftover = [];
        $perAuthor = [];
        foreach (array_keys($shuffled) as $id) {
            $author = $authors[$id];
            if (($perAuthor[$author] ?? 0) >= self::MAX_PER_AUTHOR) {
                $leftover[] = $id;
                continue;
            }

            $perAuthor[$author] = ($perAuthor[$author] ?? 0) + 1;
            $picked[] = $id;
            if (count($picked) >= $limit) {
                return $picked;
            }
        }

        return array_slice(array_merge($picked, $leftover), 0, $limit);
    }

    private function finish(array $scores, int $limit): array
    {
        $rows = $this->getVisible(array_keys($scores));
        $scores = array_intersect_key($scores, $rows);

        $authors = [];
        foreach ($rows as $id => $row) {
            $authors[$id] = (int)$row['author'];
        }

        $ordered = [];
        foreach ($this->pick($scores, $authors, $limit) as $id) {
            $ordered[] = $rows[$id];
        }

        return (new UploadResult($this->database, $ordered))->toCleanArray();
    }

    private function getVisible(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $where = sprintf("v.id IN (%s) AND (v.flags & %d) = 0", implode(',', array_map('intval', $ids)), UploadFlags::FLAG_UNPROCESSED->value);

        if ($this->uploadQuery) {
            $rows = $this->uploadQuery->query("v.id", count($ids), $where)->raw();
        } else {
            $rows = $this->database->fetchArray($this->database->query("SELECT * FROM uploads v WHERE $where"));
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['id']] = $row;
        }
        return $out;
    }
}
