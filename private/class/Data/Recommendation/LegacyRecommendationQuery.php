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

namespace Data\Recommendation;

use Core\Database;
use Core\SquareBracket;
use Data\Upload\UploadFlags;
use Data\Upload\UploadResult;
use Data\Upload\UploadVisibilityEnum;
use Data\User\UserFlags;

/**
 * class LegacyRecommendationQuery
 *
 * The recommendation scoring view.php used before upload_recommendations. Only used when
 * RecommendationQuery fails or comes up empty, since it scores every upload on the site.
 */
class LegacyRecommendationQuery
{
    // avoid recommending masturbatory uploads and dramaslop.
    // this should *probably* be moved to settings
    private const TITLE_PENALTY = [
        // websites
        'squarebracket',
        'opensb',
        'fulptube',
        'subrocks',
        'poktube',
        'vidlii',
        'bitview',
        'vlare',
        'betacast',
        'eracast',
        'kamtape',
        'goodtube',
        'rehike',
    ];

    private Database $database;
    private string $whereRatings;
    private array $whereTagBlacklist;

    public function __construct(SquareBracket $sb)
    {
        $this->database = $sb->getDatabaseClass();
        $this->whereRatings = $sb->getAuthenticationClass()->databaseWhereRatingsHelper();
        $this->whereTagBlacklist = $sb->getAuthenticationClass()->databaseWhereTagBlacklistHelper();
    }

    public function related(array $upload, int $limit = 20): array
    {
        $recommended = $this->score($upload, $limit);

        return $recommended ? (new UploadResult($this->database, $recommended))->toCleanArray() : [];
    }

    private function score(array $upload, int $limit): array
    {
        // this isn't ported to UploadQuery for now, as it will require me to rework all of UploadQuery.
        $candidateQuery = "
            SELECT v.*, u.flags AS author_flags, u.last_seen
            FROM uploads v
            JOIN users u ON u.id = v.author
            WHERE v.upload_id != ?
            AND v.flags != " . UploadFlags::FLAG_UNPROCESSED->value . "
            AND v.visibility = " . UploadVisibilityEnum::Public->value . "
            AND v.upload_id NOT IN (SELECT upload FROM upload_takedowns)
            AND v.author NOT IN (SELECT user FROM user_bans)
        ";

        $candidateParams = [$upload["upload_id"]];

        if (!empty($this->whereRatings)) {
            $candidateQuery .= " AND $this->whereRatings";
        }

        if (!empty($this->whereTagBlacklist['sql'])) {
            $candidateQuery .= " AND " . $this->whereTagBlacklist['sql'];
            // tag blacklist ?'s come after the upload_id ? in the query string,
            // so params must be appended in the same order -chaziz 07/16/2026
            $candidateParams = array_merge($candidateParams, $this->whereTagBlacklist['params']);
        }

        $candidates = $this->database->fetchArray($this->database->query($candidateQuery, $candidateParams));
        if (!$candidates) {
            return [];
        }

        $sourceTags = array_column(
            $this->database->fetchArray($this->database->query(
                "SELECT tag_id FROM upload_tag_index WHERE upload_id = ?",
                [$upload["id"]]
            )),
            'tag_id'
        );

        $candidateIds = array_column($candidates, 'id');
        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $allTags = [];
        foreach ($this->database->fetchArray($this->database->query(
            "SELECT upload_id, tag_id FROM upload_tag_index WHERE upload_id IN ($placeholders)",
            $candidateIds
        )) as $row) {
            $allTags[$row["upload_id"]][] = $row["tag_id"];
        }

        $sourceTagCount = count($sourceTags);
        // count uploads per author across all candidates
        $authorUploadCounts = array_count_values(array_column($candidates, 'author'));

        // now score this shit
        foreach ($candidates as &$row) {
            $candidateTags = $allTags[$row["id"]] ?? [];
            $intersection = count(array_intersect($sourceTags, $candidateTags));
            $union = $sourceTagCount + count($candidateTags) - $intersection;
            $jaccard = $union > 0 ? $intersection / $union : 0.0;

            $titleLower = strtolower($row["title"]);
            $penalty = 0.0;

            foreach (self::TITLE_PENALTY as $word) {
                if (str_contains($titleLower, $word)) {
                    $penalty += 1;
                }
            }

            if ($row["author"] !== $upload["author"]) {
                // if the author hasn't logged in over a year, penalize.
                if ($row["last_seen"] < strtotime('-1 year')) {
                    $penalty = 10;
                }

                // if the author has one upload, penalize.
                if ($authorUploadCounts[$row["author"]] == 1) {
                    $penalty = 50;
                }

                // if the author is shadow banned, just penalize that shit completely.
                if ($row["author_flags"] & UserFlags::FLAG_SHADOW_BAN->value) {
                    $penalty = 100;
                }
            }

            $row["relevance_score"] =
                (
                    ($jaccard * 0.75) +
                    ($this->stringSimilarity($upload["title"], $row["title"]) * 0.1) +
                    ($this->stringSimilarity($upload["description"] ?? '', $row["description"] ?? '') * 0.05)
                )
                - $penalty;
        }
        unset($row);

        usort($candidates, fn($a, $b) => $b["relevance_score"] <=> $a["relevance_score"]);

        $relevant = array_values(array_filter($candidates, fn($row) => $row["relevance_score"] > 0.005));

        shuffle($relevant);
        return array_slice($relevant, 0, $limit);
    }

    private function stringSimilarity(?string $a, ?string $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $clean = fn(string $s) => mb_strtolower(preg_replace('/[\p{P}\p{S}]/u', '', $s));
        $tokenize = fn(string $s) => array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($s))));

        $wordsA = array_values($tokenize($clean($a)));
        $wordsB = array_values($tokenize($clean($b)));

        if (count($wordsA) === 0 || count($wordsB) === 0) {
            return 0.0;
        }

        $intersection = count(array_intersect($wordsA, $wordsB));
        $union = count(array_unique(array_merge($wordsA, $wordsB)));

        return $union === 0 ? 0.0 : $intersection / $union;
    }
}
