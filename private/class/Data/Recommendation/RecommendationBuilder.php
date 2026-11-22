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
use Core\IPLookup;
use Data\Upload\UploadFlags;
use Data\Upload\UploadVisibilityEnum;
use Data\User\UserFlags;
use RuntimeException;

/**
 * class RecommendationBuilder
 *
 * Fills upload_recommendations. Run through private/scripts/build_recommendations.php.
 */
class RecommendationBuilder
{
    private const RECOMMENDATIONS_PER_UPLOAD = 50;

    private const MAX_UPLOADS_PER_IP = 400;
    private const VIEW_WINDOW = 8;
    private const VIEW_MAX_GAP = 6 * 3600;
    private const VIEW_FALLOFF = 2 * 3600;

    private const MAX_GROUP = 150;
    private const MAX_PLAYLIST = 100;
    private const MIN_EVIDENCE = 1.0;
    private const ACTIVITY_HALF = 12.0;

    private const WEIGHT_VIEWS = 0.55;
    private const WEIGHT_INTERACTIONS = 0.20;
    private const WEIGHT_PLAYLISTS = 0.25;
    private const WEIGHT_TAGS = 0.60;
    private const WEIGHT_TITLE = 0.25;
    private const WEIGHT_DESCRIPTION = 0.15;
    private const WEIGHT_AUTHOR = 0.15;

    private const SMALL_AUTHOR = 60;
    private const MAX_PER_AUTHOR = 10;
    private const COMMON_TAG_RATIO = 0.20;
    private const COMMON_WORD_COUNT = 120;
    private const DESCRIPTION_MAX_WORDS = 80;
    private const MIN_SCORE = 0.02;
    private const TYPE_MISMATCH = 0.8;

    private const RATING_DEFAULT = 3.5;
    private const RATING_DEFAULT_COUNT = 5;
    private const POPULARITY = 0.3;
    private const DORMANT_AUTHOR = 0.75;
    private const SINGLE_UPLOAD_AUTHOR = 0.6;
    private const TITLE_WORD_PENALTY = 0.35;

    private const MIN_ROWS_RATIO = 0.5;

    private const PENALIZED_TITLE_WORDS = [
        'squarebracket', 'opensb', 'fulptube', 'subrocks', 'poktube', 'vidlii', 'bitview', 'vlare',
        'betacast', 'eracast', 'kamtape', 'goodtube', 'rehike',
    ];

    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'it', 'my', 'i', 'me', 'with',
        'this', 'that', 'at', 'by', 'from', 'be', 'are', 'was', 'part', 'video', 'new', 'vs', 'ft', 'feat',
        'official', 'hd',
        'el', 'la', 'los', 'las', 'un', 'una', 'y', 'o', 'de', 'del', 'al', 'en', 'que', 'por', 'con', 'para',
        'mi', 'es', 'se', 'lo', 'su', 'parte',
        'le', 'les', 'des', 'du', 'une', 'et', 'est', 'pour', 'dans', 'sur', 'au', 'aux', 'ce', 'mon', 'ma',
        'avec', 'qui', 'pas', 'partie',
    ];

    private const NO_SPACE_SCRIPTS = '\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}ー';

    private const TABLE_SQL = "CREATE TABLE IF NOT EXISTS `%s` (
        `upload` bigint(20) unsigned NOT NULL,
        `related` bigint(20) unsigned NOT NULL,
        `score` float NOT NULL,
        `activity` float NOT NULL DEFAULT 0,
        `content` float NOT NULL DEFAULT 0,
        PRIMARY KEY (`upload`, `related`),
        KEY `idx_upload_score` (`upload`, `score`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    private Database $database;
    private ?IPLookup $ipLookup;
    private int $viewsSince;
    private $logger;

    private array $uploads = [];
    private array $idByUploadId = [];

    private array $pairs = ['views' => [], 'interactions' => [], 'playlists' => []];
    private array $totals = ['views' => [], 'interactions' => [], 'playlists' => []];

    private array $tagWeights = [];
    private array $titleWeights = [];
    private array $descriptionWeights = [];
    private array $quality = [];

    private array $ignoredUsers = [];
    private array $bannedIps = [];
    private array $bannedRanges = [];
    private array $masks = [];
    private array $bannedPrefixes = [];
    private array $prefixLengths = [];
    private array $bannedPatterns = [];
    private array $bannedAsns = [];

    private array $recommendations = [];
    private array $stats = [];

    public function __construct(Database $database, ?IPLookup $ipLookup = null, ?callable $logger = null, int $viewsSince = 0)
    {
        $this->database = $database;
        $this->ipLookup = $ipLookup;
        $this->logger = $logger;
        $this->viewsSince = $viewsSince;
    }

    public function build(bool $save = true, bool $force = false): array
    {
        if (!$this->database->result("SELECT GET_LOCK('sb_build_recommendations_lock', 0)")) {
            throw new RuntimeException('build_recommendations is already running');
        }

        try {
            return $this->run($save, $force);
        } finally {
            $this->database->query("SELECT RELEASE_LOCK('sb_build_recommendations_lock')");
        }
    }

    public function getRecommendations(): array
    {
        return $this->recommendations;
    }

    private function run(bool $save, bool $force): array
    {
        $start = microtime(true);

        $this->loadBans();
        $this->loadUploads();
        $this->loadViews();
        $this->loadInteractions();
        $this->loadPlaylists();
        $this->loadText();
        $this->loadQuality();

        $this->recommendations = $this->scoreUploads();

        if ($save) {
            $this->save($force);
        }

        $this->stats['seconds'] = round(microtime(true) - $start, 2);
        $this->stats['peak_memory_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);

        return $this->stats;
    }

    private function loadBans(): void
    {
        foreach ($this->database->fetchArray($this->database->query(
            "SELECT user AS id FROM user_bans UNION SELECT id FROM users WHERE (flags & ?) != 0",
            [UserFlags::FLAG_SHADOW_BAN->value]
        )) as $row) {
            $this->ignoredUsers[(int)$row['id']] = true;
        }

        // deal with user/ip/asn bans.
        $patterns = [];
        foreach ($this->database->fetchArray($this->database->query("SELECT ip FROM ip_bans")) as $row) {
            $ip = strtolower(trim($row['ip']));

            if (str_contains($ip, '%') || str_contains($ip, '_')) {
                $prefix = substr($ip, 0, -1);
                if (str_ends_with($ip, '%') && !str_contains($prefix, '%') && !str_contains($prefix, '_')) {
                    $this->bannedPrefixes[$prefix] = true;
                } else {
                    $patterns[] = str_replace(['%', '_'], ['.*', '.'], preg_quote($ip, '/'));
                }
            } elseif (str_contains($ip, '/')) {
                [$network, $bits] = explode('/', $ip, 2);
                $long = ip2long($network);
                $bits = (int)$bits;
                if ($long !== false && $bits >= 0 && $bits <= 32) {
                    $this->masks[$bits] = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;
                    $this->bannedRanges[$bits][$long & $this->masks[$bits]] = true;
                }
            } else {
                $this->bannedIps[$ip] = true;
            }
        }

        $this->prefixLengths = array_values(array_unique(array_map('strlen', array_keys($this->bannedPrefixes))));
        foreach (array_chunk($patterns, 200) as $chunk) {
            $this->bannedPatterns[] = '/^(?:' . implode('|', $chunk) . ')$/';
        }

        if ($this->ipLookup) {
            foreach ($this->database->fetchArray($this->database->query("SELECT asn FROM asn_bans")) as $row) {
                $this->bannedAsns[(int)$row['asn']] = true;
            }
        }

        $ipBannedUsers = 0;
        foreach ($this->database->fetchArray($this->database->query("SELECT id, ip FROM users WHERE ip IS NOT NULL")) as $row) {
            if (!isset($this->ignoredUsers[(int)$row['id']]) && $this->isIpBanned($row['ip'])) {
                $this->ignoredUsers[(int)$row['id']] = true;
                $ipBannedUsers++;
            }
        }

        $this->log(sprintf(
            "ignoring %d users (%d ip banned). ip bans: %d exact, %d ranges, %d prefixes, %d patterns. asn bans: %s",
            count($this->ignoredUsers),
            $ipBannedUsers,
            count($this->bannedIps),
            array_sum(array_map('count', $this->bannedRanges)),
            count($this->bannedPrefixes),
            count($patterns),
            $this->ipLookup ? count($this->bannedAsns) : 'off'
        ));
    }

    private function isIpBanned(string $ip): bool
    {
        $ip = strtolower($ip);

        if (isset($this->bannedIps[$ip])) {
            return true;
        }

        $long = ip2long($ip);
        if ($long !== false) {
            foreach ($this->masks as $bits => $mask) {
                if (isset($this->bannedRanges[$bits][$long & $mask])) {
                    return true;
                }
            }
        }

        foreach ($this->prefixLengths as $length) {
            if (isset($this->bannedPrefixes[substr($ip, 0, $length)])) {
                return true;
            }
        }

        foreach ($this->bannedPatterns as $regex) {
            if (preg_match($regex, $ip)) {
                return true;
            }
        }

        if ($this->bannedAsns) {
            try {
                $asn = $this->ipLookup?->getInfo($ip)['asn'] ?? '';
            } catch (\Throwable) {
                return false;
            }
            return isset($this->bannedAsns[(int)preg_replace('/^AS/i', '', $asn)]);
        }

        return false;
    }

    private function loadUploads(): void
    {
        // mature/rating/blacklist/guest filtering happens in RecommendationQuery, so dont bother here
        $rows = $this->database->fetchArray($this->database->query(
            "SELECT v.id, v.upload_id, v.title, v.description, v.author, v.type, v.views, u.last_seen
             FROM uploads v
             JOIN users u ON u.id = v.author
             WHERE v.visibility = ?
               AND (v.flags & ?) = 0
               AND (u.flags & ?) = 0
               AND v.upload_id NOT IN (SELECT upload FROM upload_takedowns)
               AND v.author NOT IN (SELECT user FROM user_bans)",
            [
                UploadVisibilityEnum::Public->value,
                UploadFlags::FLAG_UNPROCESSED->value,
                UserFlags::FLAG_SHADOW_BAN->value,
            ]
        ));

        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $row['id'] = $id;
            $row['author'] = (int)$row['author'];
            $row['type'] = (int)$row['type'];

            $this->uploads[$id] = $row;
            $this->idByUploadId[$row['upload_id']] = $id;
        }

        $this->stats['uploads'] = count($this->uploads);
        $this->log("uploads: " . count($this->uploads));
    }

    private function loadViews(): void
    {
        // upload_views.user is the ip, even when logged in
        $statement = $this->database->query(
            "SELECT upload_id, user, timestamp FROM upload_views WHERE timestamp >= ? ORDER BY user, timestamp",
            [$this->viewsSince]
        );

        $currentIp = null;
        $history = [];
        $rows = 0;
        $used = 0;
        $tooMany = 0;
        $banned = 0;

        $finishIp = function () use (&$history, &$currentIp, &$used, &$tooMany, &$banned) {
            $count = count($history);
            if ($count >= 2) {
                if ($count > self::MAX_UPLOADS_PER_IP) {
                    $tooMany++;
                } elseif ($this->isIpBanned((string)$currentIp)) {
                    $banned++;
                } else {
                    $used++;
                    $this->addViewHistory($history);
                }
            }
            $history = [];
        };

        while ($row = $statement->fetch()) {
            $rows++;
            if ($row['user'] !== $currentIp) {
                $finishIp();
                $currentIp = $row['user'];
            }

            $id = $this->idByUploadId[$row['upload_id']] ?? null;
            if ($id !== null) {
                $history[] = [$id, (int)$row['timestamp']];
            }
        }
        $finishIp();

        $this->stats['views'] = $rows;
        $this->stats['ips_used'] = $used;
        $this->stats['ips_too_many_views'] = $tooMany;
        $this->stats['ips_banned'] = $banned;
        $this->log("views: $rows from $used ips ($tooMany with too many views, $banned banned)");
    }

    private function loadInteractions(): void
    {
        $byUser = [];

        foreach ($this->database->fetchArray($this->database->query("SELECT user, upload FROM upload_ratings WHERE rating >= 4")) as $row) {
            $id = (int)$row['upload'];
            if (isset($this->uploads[$id]) && !isset($this->ignoredUsers[(int)$row['user']])) {
                $byUser[(int)$row['user']][$id] = true;
            }
        }

        foreach ($this->database->fetchArray($this->database->query("SELECT author, location_id FROM upload_comments")) as $row) {
            $id = $this->idByUploadId[$row['location_id']] ?? null;
            if ($id !== null && !isset($this->ignoredUsers[(int)$row['author']])) {
                $byUser[(int)$row['author']][$id] = true;
            }
        }

        $used = 0;
        foreach ($byUser as $user => $uploads) {
            $uploads = array_values(array_filter(
                array_keys($uploads),
                fn($id) => $this->uploads[$id]['author'] !== $user
            ));

            if (count($uploads) >= 2) {
                $used++;
                $this->addGroup('interactions', $uploads);
            }
        }

        $this->stats['users_with_interactions'] = $used;
        $this->log("interactions: $used users");
    }

    private function loadPlaylists(): void
    {
        // big playlists are autogenerated ones
        $byPlaylist = [];
        foreach ($this->database->fetchArray($this->database->query(
            "SELECT pi.playlist, pi.upload, p.author
             FROM playlist_items pi
             JOIN playlists p ON p.id = pi.playlist
             WHERE p.upload_count <= ?
             ORDER BY pi.playlist, pi.position",
            [self::MAX_PLAYLIST]
        )) as $row) {
            $id = (int)$row['upload'];
            if (isset($this->uploads[$id]) && !isset($this->ignoredUsers[(int)$row['author']])) {
                $byPlaylist[(int)$row['playlist']][$id] = true;
            }
        }

        $used = 0;
        foreach ($byPlaylist as $uploads) {
            if (count($uploads) >= 2) {
                $used++;
                $this->addGroup('playlists', array_keys($uploads));
            }
        }

        $this->stats['playlists'] = $used;
        $this->log("playlists: $used");
    }

    private function loadText(): void
    {
        $count = max(1, count($this->uploads));
        $stopwords = array_flip(self::STOPWORDS);

        $tags = [];
        foreach ($this->database->fetchArray($this->database->query(
            "SELECT ti.upload_id, t.name FROM upload_tag_index ti JOIN upload_tag_meta t ON t.tag_id = ti.tag_id"
        )) as $row) {
            $id = (int)$row['upload_id'];
            $tag = $this->normalize($row['name']);
            if (isset($this->uploads[$id]) && $tag !== '') {
                $tags[$id][$tag] = true;
            }
        }
        $this->tagWeights = $this->weighWords($tags, (int)floor($count * self::COMMON_TAG_RATIO), $count);

        $words = [];
        foreach ($this->uploads as $id => $row) {
            foreach ($this->splitWords($row['title']) as $word) {
                if (!isset($stopwords[$word]) && $this->longEnough($word, 2)) {
                    $words[$id][$word] = true;
                }
            }
        }
        $this->titleWeights = $this->weighWords($words, self::COMMON_WORD_COUNT, $count);

        $words = [];
        foreach ($this->uploads as $id => $row) {
            $description = (string)preg_replace('~https?://\S+|www\.\S+~i', ' ', (string)$row['description']);
            foreach (array_slice($this->splitWords($description), 0, self::DESCRIPTION_MAX_WORDS) as $word) {
                if (!isset($stopwords[$word]) && $this->longEnough($word, 3)) {
                    $words[$id][$word] = true;
                }
            }
        }
        $this->descriptionWeights = $this->weighWords($words, self::COMMON_WORD_COUNT, $count);

        $this->log(sprintf(
            "text: %d uploads with tags, %d with title words, %d with description words",
            count($this->tagWeights),
            count($this->titleWeights),
            count($this->descriptionWeights)
        ));
    }

    private function loadQuality(): void
    {
        $ratings = [];
        foreach ($this->database->fetchArray($this->database->query(
            "SELECT upload, COUNT(*) AS count, SUM(rating) AS total FROM upload_ratings GROUP BY upload"
        )) as $row) {
            $ratings[(int)$row['upload']] = [(int)$row['count'], (float)$row['total']];
        }

        $uploadsPerAuthor = array_count_values(array_column($this->uploads, 'author'));
        $yearAgo = time() - 365 * 86400;

        foreach ($this->uploads as $id => $row) {
            [$ratingCount, $ratingTotal] = $ratings[$id] ?? [0, 0.0];

            // few ratings = close to 3.5 stars
            $stars = ($ratingTotal + self::RATING_DEFAULT * self::RATING_DEFAULT_COUNT) / ($ratingCount + self::RATING_DEFAULT_COUNT);
            $quality = 0.85 + 0.30 * (($stars - 1) / 4);
            $quality *= 1 + self::POPULARITY * log10(1 + (int)$row['views']);

            if ((int)$row['last_seen'] < $yearAgo) {
                $quality *= self::DORMANT_AUTHOR;
            }

            if (($uploadsPerAuthor[$row['author']] ?? 0) <= 1) {
                $quality *= self::SINGLE_UPLOAD_AUTHOR;
            }

            $title = $this->normalize($row['title']);
            foreach (self::PENALIZED_TITLE_WORDS as $word) {
                if (str_contains($title, $word)) {
                    $quality *= self::TITLE_WORD_PENALTY;
                    break;
                }
            }

            $this->quality[$id] = $quality;
        }
    }

    private function addViewHistory(array $history): void
    {
        $seen = [];
        $unique = [];
        foreach ($history as $view) {
            if (!isset($seen[$view[0]])) {
                $seen[$view[0]] = true;
                $unique[] = $view;
            }
        }

        $count = count($unique);
        if ($count < 2) {
            return;
        }

        // people who watch everything say less about any two uploads
        $weight = 1 / log(2 + $count, 2);

        foreach ($unique as [$id]) {
            $this->totals['views'][$id] = ($this->totals['views'][$id] ?? 0) + $weight;
        }

        for ($a = 0; $a < $count; $a++) {
            [$first, $firstTime] = $unique[$a];
            $end = min($count, $a + 1 + self::VIEW_WINDOW);

            for ($b = $a + 1; $b < $end; $b++) {
                [$second, $secondTime] = $unique[$b];
                $gap = $secondTime - $firstTime;
                if ($gap > self::VIEW_MAX_GAP) {
                    break;
                }
                $this->addPair('views', $first, $second, $weight * exp(-$gap / self::VIEW_FALLOFF));
            }
        }
    }

    private function addGroup(string $source, array $uploads): void
    {
        if (count($uploads) > self::MAX_GROUP) {
            shuffle($uploads);
            $uploads = array_slice($uploads, 0, self::MAX_GROUP);
        }

        $count = count($uploads);
        $weight = 1 / log(2 + $count, 2);

        foreach ($uploads as $id) {
            $this->totals[$source][$id] = ($this->totals[$source][$id] ?? 0) + $weight;
        }

        for ($a = 0; $a < $count; $a++) {
            for ($b = $a + 1; $b < $count; $b++) {
                $this->addPair($source, $uploads[$a], $uploads[$b], $weight);
            }
        }
    }

    private function addPair(string $source, int $first, int $second, float $weight): void
    {
        if ($first === $second) {
            return;
        }
        if ($first > $second) {
            [$first, $second] = [$second, $first];
        }
        $this->pairs[$source][$first][$second] = ($this->pairs[$source][$first][$second] ?? 0) + $weight;
    }

    private function scoreUploads(): array
    {
        $activity = [];
        $sources = [
            'views' => self::WEIGHT_VIEWS,
            'interactions' => self::WEIGHT_INTERACTIONS,
            'playlists' => self::WEIGHT_PLAYLISTS,
        ];

        foreach ($sources as $source => $sourceWeight) {
            foreach ($this->pairs[$source] as $first => $others) {
                foreach ($others as $second => $together) {
                    $similarity = $together / sqrt($this->totals[$source][$first] * $this->totals[$source][$second]);
                    $similarity *= $together / ($together + self::MIN_EVIDENCE);

                    $value = $sourceWeight * min(1.0, $similarity);
                    $activity[$first][$second] = ($activity[$first][$second] ?? 0) + $value;
                    $activity[$second][$first] = ($activity[$second][$first] ?? 0) + $value;
                }
            }
            $this->pairs[$source] = [];
        }

        $trust = [];
        foreach ($this->uploads as $id => $_) {
            $amount = ($this->totals['views'][$id] ?? 0)
                + 2 * ($this->totals['interactions'][$id] ?? 0)
                + 2 * ($this->totals['playlists'][$id] ?? 0);
            $trust[$id] = $amount / ($amount + self::ACTIVITY_HALF);
        }

        $tagIndex = $this->indexWords($this->tagWeights);
        $titleIndex = $this->indexWords($this->titleWeights);
        $descriptionIndex = $this->indexWords($this->descriptionWeights);

        $byAuthor = [];
        foreach ($this->uploads as $id => $row) {
            $byAuthor[$row['author']][] = $id;
        }
        $logTotal = log(max(2, count($this->uploads)));

        $out = [];
        foreach ($this->uploads as $id => $upload) {
            $content = [];
            $this->addWordMatches($content, $this->tagWeights[$id] ?? [], $tagIndex, self::WEIGHT_TAGS);
            $this->addWordMatches($content, $this->titleWeights[$id] ?? [], $titleIndex, self::WEIGHT_TITLE);
            $this->addWordMatches($content, $this->descriptionWeights[$id] ?? [], $descriptionIndex, self::WEIGHT_DESCRIPTION);

            $authorUploads = $byAuthor[$upload['author']];
            $authorWeight = self::WEIGHT_AUTHOR * max(0, 1 - log(count($authorUploads)) / $logTotal);
            if (count($authorUploads) <= self::SMALL_AUTHOR) {
                foreach ($authorUploads as $other) {
                    $content[$other] = ($content[$other] ?? 0) + $authorWeight;
                }
            } else {
                foreach ($content as $other => $value) {
                    if ($this->uploads[$other]['author'] === $upload['author']) {
                        $content[$other] = $value + $authorWeight;
                    }
                }
            }
            unset($content[$id]);

            $activityRow = $activity[$id] ?? [];

            $scored = [];
            foreach ($activityRow + $content as $other => $_) {
                $activityScore = $activityRow[$other] ?? 0.0;
                $contentScore = $content[$other] ?? 0.0;
                $trustBoth = sqrt($trust[$id] * $trust[$other]);

                // content keeps at least 30% so new uploads can show up next to popular ones
                $score = $trustBoth * $activityScore + max(0.3, 1 - $trustBoth) * $contentScore;
                if ($score < self::MIN_SCORE) {
                    continue;
                }

                if ($upload['type'] !== $this->uploads[$other]['type']) {
                    $score *= self::TYPE_MISMATCH;
                }

                $scored[$other] = [
                    'score' => $score * $this->quality[$other],
                    'activity' => $activityScore,
                    'content' => $contentScore,
                ];
            }

            uasort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

            $kept = [];
            $perAuthor = [];
            foreach ($scored as $other => $row) {
                $author = $this->uploads[$other]['author'];
                if (($perAuthor[$author] ?? 0) >= self::MAX_PER_AUTHOR) {
                    continue;
                }
                $perAuthor[$author] = ($perAuthor[$author] ?? 0) + 1;

                $kept[$other] = $row;
                if (count($kept) >= self::RECOMMENDATIONS_PER_UPLOAD) {
                    break;
                }
            }

            $out[$id] = $kept;
            unset($activity[$id]);
        }

        $this->stats['uploads_with_recommendations'] = count(array_filter($out));
        return $out;
    }

    private function addWordMatches(array &$content, array $words, array $index, float $weight): void
    {
        foreach ($words as $word => $mine) {
            foreach ($index[$word] as $other => $theirs) {
                $content[$other] = ($content[$other] ?? 0) + $weight * $mine * $theirs;
            }
        }
    }

    private function save(bool $force): void
    {
        // built in a separate table and swapped in afterwards
        $this->database->query("DROP TABLE IF EXISTS upload_recommendations_next");
        $this->database->query(sprintf(self::TABLE_SQL, 'upload_recommendations_next'));
        $this->database->query(sprintf(self::TABLE_SQL, 'upload_recommendations'));

        $values = [];
        $params = [];
        $written = 0;

        $flush = function () use (&$values, &$params) {
            if ($values) {
                $this->database->query(
                    "INSERT INTO upload_recommendations_next (upload, related, score, activity, content) VALUES " . implode(',', $values),
                    $params
                );
            }
            $values = [];
            $params = [];
        };

        foreach ($this->recommendations as $upload => $related) {
            foreach ($related as $other => $scores) {
                $values[] = '(?,?,?,?,?)';
                array_push($params, $upload, $other, $scores['score'], $scores['activity'], $scores['content']);
                $written++;

                if (count($values) >= 1000) {
                    $flush();
                }
            }
        }
        $flush();

        $this->stats['rows_written'] = $written;

        // don't throw out good data because of a bad build
        $current = (int)$this->database->result("SELECT COUNT(*) FROM upload_recommendations");
        if (!$force && $current > 0 && $written < $current * self::MIN_ROWS_RATIO) {
            $this->database->query("DROP TABLE upload_recommendations_next");
            throw new RuntimeException("new build has $written rows, current one has $current. not saving, use --force if that's expected");
        }

        // left behind if a previous run died mid swap
        $this->database->query("DROP TABLE IF EXISTS upload_recommendations_old");

        // RENAME waits for running queries so we don't wait forever
        $this->database->query("SET SESSION lock_wait_timeout = 15");
        try {
            $this->database->query(
                "RENAME TABLE upload_recommendations TO upload_recommendations_old,
                              upload_recommendations_next TO upload_recommendations"
            );
        } catch (\PDOException $e) {
            $this->database->query("DROP TABLE IF EXISTS upload_recommendations_next");
            throw new RuntimeException("couldn't swap tables: " . $e->getMessage());
        }
        $this->database->query("DROP TABLE upload_recommendations_old");

        $this->log("saved $written rows (was $current)");
    }

    private function weighWords(array $words, int $maxUses, int $uploadCount): array
    {
        $uses = [];
        foreach ($words as $list) {
            foreach ($list as $word => $_) {
                $uses[$word] = ($uses[$word] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($words as $id => $list) {
            $weights = [];
            foreach ($list as $word => $_) {
                if ($uses[$word] >= 2 && $uses[$word] <= $maxUses) {
                    $weights[$word] = log($uploadCount / $uses[$word]);
                }
            }

            $length = sqrt(array_sum(array_map(fn($weight) => $weight * $weight, $weights)));
            if ($length > 0) {
                foreach ($weights as $word => $weight) {
                    $weights[$word] = $weight / $length;
                }
                $out[$id] = $weights;
            }
        }

        return $out;
    }

    private function indexWords(array $weights): array
    {
        $index = [];
        foreach ($weights as $id => $words) {
            foreach ($words as $word => $weight) {
                $index[$word][$id] = $weight;
            }
        }
        return $index;
    }

    private function normalize(string $text): string
    {
        // titles from old imports can have broken utf-8, which makes the /u regexes return null
        $text = mb_strtolower((string)\Normalizer::normalize(mb_scrub($text, 'UTF-8'), \Normalizer::FORM_KC));

        // strip accents from latin letters only, doing it to ド would turn it into ト
        $text = (string)preg_replace('/(\p{Latin})\p{Mn}+/u', '$1', (string)\Normalizer::normalize($text, \Normalizer::FORM_D));
        $text = (string)\Normalizer::normalize($text, \Normalizer::FORM_C);

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    private function splitWords(string $text): array
    {
        $text = (string)preg_replace('/[\p{P}\p{S}]+/u', ' ', $this->normalize($text));
        $scripts = self::NO_SPACE_SCRIPTS;
        preg_match_all("/[$scripts]+|[^\\s$scripts]+/u", $text, $matches);

        // no spaces to split on, so use overlapping pairs of characters instead
        $words = [];
        foreach ($matches[0] as $part) {
            if (!preg_match("/^[$scripts]+$/u", $part)) {
                $words[] = $part;
                continue;
            }

            $chars = mb_str_split($part);
            if (count($chars) === 1) {
                $words[] = $part;
            }
            for ($i = 0; $i < count($chars) - 1; $i++) {
                $words[] = $chars[$i] . $chars[$i + 1];
            }
        }

        return $words;
    }

    private function longEnough(string $word, int $min): bool
    {
        return mb_strlen($word) >= $min || preg_match('/[' . self::NO_SPACE_SCRIPTS . ']/u', $word) === 1;
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }
}
