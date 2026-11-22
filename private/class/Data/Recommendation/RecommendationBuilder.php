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
use Core\IPBans;
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
    private const CANDIDATES_PER_UPLOAD = 250;

    private const MAX_UPLOADS_PER_IP = 400;
    private const VIEW_WINDOW = 8;
    private const VIEW_MAX_GAP = 6 * 3600;
    private const VIEW_FALLOFF = 2 * 3600;
    private const NEW_UPLOAD_WINDOW = 7 * 86400;
    private const NEW_UPLOAD_PAIR = 0.3;

    private const MAX_GROUP = 150;
    private const MIN_EVIDENCE = 1.0;
    private const ACTIVITY_HALF = 12.0;

    private const WEIGHT_VIEWS = 0.55;
    private const WEIGHT_INTERACTIONS = 0.20;
    private const WEIGHT_PLAYLISTS = 0.25;
    private const WEIGHT_TAGS = 0.60;
    private const WEIGHT_TITLE = 0.25;
    private const WEIGHT_DESCRIPTION = 0.15;
    private const WEIGHT_AUTHOR = 0.15;
    private const WEIGHT_SOCIAL = 0.10;

    private const FOLLOW_LINK = 0.4;
    private const COMMENT_LINK = 0.6;
    private const COMMENT_HALF = 3.0;

    private const SMALL_AUTHOR = 60;
    private const MAX_PER_AUTHOR = 10;
    private const COMMON_TAG_RATIO = 0.20;
    private const COMMON_WORD_COUNT = 120;
    private const DESCRIPTION_MAX_WORDS = 80;
    private const GENERIC_MIN_USES = 10;
    private const MIN_SCORE = 0.02;
    private const TYPE_MISMATCH = 0.8;

    private const RATING_DEFAULT = 3.5;
    private const RATING_DEFAULT_COUNT = 5;
    private const POPULARITY = 0.3;
    private const DORMANT_AUTHOR = 0.75;
    private const SINGLE_UPLOAD_AUTHOR = 0.6;
    private const TITLE_WORD_PENALTY = 0.35;

    private const MIN_ROWS_RATIO = 0.5;

    private const BUBBLE_EDGES = 15;
    private const BUBBLE_ACTIVITY = 0.3;
    private const BUBBLE_SOCIAL = 0.3;
    private const TOPIC_CONTENT = 0.3;
    private const BUBBLE_RESOLUTION = 1.0;
    private const TERM_MIN_TOGETHER = 2;
    private const TERM_MIN_AUTHORS = 2;
    private const TERM_EVIDENCE = 20.0;
    private const REFINE_ROUNDS = 8;
    private const REFINE_EVIDENCE = 5.0;
    private const MIN_BUBBLE_SIZE = 5;
    private const TERMS_PER_UPLOAD = 15;
    private const TERM_TAG = 1.0;
    private const TERM_TITLE = 0.7;
    private const TERM_DESCRIPTION = 0.4;
    private const FILL_ROUNDS = 5;
    private const MIN_BUBBLE_AUTHORS = 2;
    private const TOPIC_RESOLUTION = 0.5;
    private const NEIGHBOR_BUBBLES = 3;
    private const NEIGHBOR_BUBBLE_MIN_SHARE = 0.1;
    private const SAME_BUBBLE = 1.0;
    private const SAME_TOPIC = 0.8;
    private const NEIGHBOR_BUBBLE = 0.6;
    private const OTHER_BUBBLE = 0.3;

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
        'so', 'hi', 'was', 'were', 'do', 'does', 'did', 'not', 'no', 'yes', 'you', 'your', 'we', 'our', 'he', 'she',
        'they', 'his', 'her', 'its', 'what', 'when', 'where', 'who', 'why', 'how', 'all', 'but', 'if', 'as', 'up',
        'down', 'out', 'about', 'just', 'get', 'got', 'can', 'will', 'have', 'has', 'had', 'am', 'been', 'very',
        'too', 'some', 'any', 'into', 'than', 'then', 'there', 'here',
    ];

    private const NO_SPACE_SCRIPTS = '\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}ー';

    private const TABLES = [
        'upload_recommendations' => "CREATE TABLE IF NOT EXISTS `%s` (
            `upload` bigint(20) unsigned NOT NULL,
            `related` bigint(20) unsigned NOT NULL,
            `score` float NOT NULL,
            `activity` float NOT NULL DEFAULT 0,
            `content` float NOT NULL DEFAULT 0,
            PRIMARY KEY (`upload`, `related`),
            KEY `idx_upload_score` (`upload`, `score`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'upload_bubbles' => "CREATE TABLE IF NOT EXISTS `%s` (
            `upload` bigint(20) unsigned NOT NULL,
            `bubble` int(11) NOT NULL,
            PRIMARY KEY (`upload`),
            KEY `idx_bubble` (`bubble`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'recommendation_bubbles' => "CREATE TABLE IF NOT EXISTS `%s` (
            `bubble` int(11) NOT NULL,
            `size` int(11) NOT NULL,
            `words` text NOT NULL COMMENT 'JSON list, tags start with #',
            `authors` text NOT NULL COMMENT 'JSON object, user id => uploads in the bubble',
            `neighbors` text NOT NULL COMMENT 'JSON list of bubble ids',
            `topic` int(11) DEFAULT NULL COMMENT 'NULL when every upload in the topic is by one author',
            `counts` tinyint(1) NOT NULL COMMENT '0 when every upload in the bubble is by one author',
            `untagged` int(11) NOT NULL COMMENT 'uploads in the bubble without usable tags',
            `links` text NOT NULL COMMENT 'JSON object, share of what holds the bubble together: activity/content/social',
            `built` bigint(20) unsigned NOT NULL,
            PRIMARY KEY (`bubble`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];

    private Database $database;
    private ?IPLookup $ipLookup;
    private int $viewsSince;
    private array $ignoredWords;
    private $logger;

    private array $uploads = [];
    private array $idByUploadId = [];

    private array $pairs = ['views' => [], 'user_views' => [], 'interactions' => [], 'playlists' => []];
    private array $totals = ['views' => [], 'user_views' => [], 'interactions' => [], 'playlists' => []];

    private array $tagWeights = [];
    private array $uploadTags = [];
    private array $titleWeights = [];
    private array $descriptionWeights = [];
    private array $quality = [];
    private array $social = [];

    private array $ignoredUsers = [];
    private array $userIps = [];
    private ?IPBans $ipBans = null;

    private array $bubbles = [];
    private array $topics = [];
    private array $countedBubbles = [];
    private array $bubbleLinks = [];
    private array $neighborBubbles = [];

    private array $recommendations = [];
    private array $stats = [];

    public function __construct(Database $database, ?IPLookup $ipLookup = null, ?callable $logger = null, int $viewsSince = 0, array $ignoredWords = [])
    {
        $this->database = $database;
        $this->ipLookup = $ipLookup;
        $this->logger = $logger;
        $this->viewsSince = $viewsSince;
        $this->ignoredWords = array_flip(array_map(fn($word) => $this->normalize($word), $ignoredWords));
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

    public function getBubbles(): array
    {
        return $this->bubbles;
    }

    // what a bubble is about: tags (#) and title words that show up way more inside it than on the rest of the site
    public function describeBubbles(int $limit = 0): array
    {
        $members = [];
        foreach ($this->bubbles as $id => $bubble) {
            $members[$bubble][] = $id;
        }
        uasort($members, fn($a, $b) => count($b) <=> count($a));
        if ($limit > 0) {
            $members = array_slice($members, 0, $limit, true);
        }

        $labels = [];
        $globalUses = [];
        foreach ($this->uploads as $id => $_) {
            $list = [];
            foreach ($this->tagWeights[$id] ?? [] as $tag => $_) {
                $list["#$tag"] = true;
            }
            foreach ($this->titleWeights[$id] ?? [] as $word => $_) {
                $list[$word] = true;
            }
            $labels[$id] = $list;
            foreach ($list as $label => $_) {
                $globalUses[$label] = ($globalUses[$label] ?? 0) + 1;
            }
        }
        $total = max(1, count($this->uploads));

        $out = [];
        foreach ($members as $bubble => $ids) {
            $size = count($ids);

            $uses = [];
            $authors = [];
            $untagged = 0;
            foreach ($ids as $id) {
                if (!isset($this->tagWeights[$id])) {
                    $untagged++;
                }
                foreach ($labels[$id] as $label => $_) {
                    $uses[$label] = ($uses[$label] ?? 0) + 1;
                }
                $author = $this->uploads[$id]['author'];
                $authors[$author] = ($authors[$author] ?? 0) + 1;
            }

            $distinct = [];
            foreach ($uses as $label => $count) {
                if ($count >= 2) {
                    $distinct[$label] = $count * log(($count / $size) / ($globalUses[$label] / $total));
                }
            }
            arsort($distinct);
            arsort($authors);

            $out[$bubble] = [
                'size' => $size,
                'words' => array_map('strval', array_keys(array_slice(array_filter($distinct, fn($score) => $score > 0), 0, 8, true))),
                'authors' => array_slice($authors, 0, 3, true),
                'neighbors' => array_keys($this->neighborBubbles[$bubble] ?? []),
                'topic' => $this->topics[$bubble],
                'counts' => isset($this->countedBubbles[$bubble]),
                'untagged' => $untagged,
                'links' => $this->bubbleLinks[$bubble] ?? ['activity' => 0, 'content' => 0, 'social' => 0],
            ];
        }

        return $out;
    }

    private function run(bool $save, bool $force): array
    {
        $start = microtime(true);

        $this->loadBans();
        $this->loadUploads();
        $this->loadViews();
        $this->loadInteractions();
        $this->loadSocial();
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
        $this->ipBans = new IPBans($this->database, $this->ipLookup);

        $ipBannedUsers = 0;
        foreach ($this->database->fetchArray($this->database->query("SELECT id, ip FROM users WHERE ip IS NOT NULL")) as $row) {
            $this->userIps[(int)$row['id']] = strtolower($row['ip']);
            if (!isset($this->ignoredUsers[(int)$row['id']]) && $this->ipBans->isBanned($row['ip'])) {
                $this->ignoredUsers[(int)$row['id']] = true;
                $ipBannedUsers++;
            }
        }

        $this->log(sprintf(
            "ignoring %d users (%d ip banned). bans: %s",
            count($this->ignoredUsers),
            $ipBannedUsers,
            $this->ipBans->summary()
        ));
    }

    private function isIpBanned(string $ip): bool
    {
        return $this->ipBans->isBanned($ip);
    }

    private function loadUploads(): void
    {
        // mature/rating/blacklist/guest filtering happens in RecommendationQuery, so dont bother here
        $rows = $this->database->fetchArray($this->database->query(
            "SELECT v.id, v.upload_id, v.title, v.description, v.author, v.type, v.views, v.timestamp, u.last_seen
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
            $row['timestamp'] = (int)$row['timestamp'];

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
            "SELECT upload_id, user, timestamp, type FROM upload_views WHERE timestamp >= ? ORDER BY user, timestamp",
            [$this->viewsSince]
        );

        $currentIp = null;
        $history = [];
        $rows = 0;
        $used = 0;
        $tooMany = 0;
        $banned = 0;
        $own = 0;
        $loggedIn = 0;

        $finishIp = function () use (&$history, &$currentIp, &$used, &$tooMany, &$banned) {
            $count = count($history);
            if ($count >= 2) {
                if ($count > self::MAX_UPLOADS_PER_IP) {
                    $tooMany++;
                } elseif ($this->isIpBanned((string)$currentIp)) {
                    $banned++;
                } else {
                    $used++;
                    $this->addViewHistory('views', $history);
                    $this->addViewHistory('user_views', array_values(array_filter($history, fn($view) => $view[2])));
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
            if ($id === null) {
                continue;
            }

            // users.ip only has their latest ip
            if (($this->userIps[$this->uploads[$id]['author']] ?? null) === strtolower((string)$currentIp)) {
                $own++;
                continue;
            }

            $history[] = [$id, (int)$row['timestamp'], $row['type'] === 'user'];
            if ($row['type'] === 'user') {
                $loggedIn++;
            }
        }
        $finishIp();

        $this->stats['views'] = $rows;
        $this->stats['ips_used'] = $used;
        $this->stats['ips_too_many_views'] = $tooMany;
        $this->stats['ips_banned'] = $banned;
        $this->stats['own_views'] = $own;
        $this->stats['logged_in_views'] = $loggedIn;
        $this->log("views: $rows from $used ips ($tooMany with too many views, $banned banned, $own views of their own uploads, $loggedIn logged in)");
    }

    private function addViewHistory(string $source, array $history): void
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
        $weight = 1 / sqrt($count);

        foreach ($unique as [$id]) {
            $this->totals[$source][$id] = ($this->totals[$source][$id] ?? 0) + $weight;
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
                $pair = $weight * exp(-$gap / self::VIEW_FALLOFF);
                if ($firstTime - $this->uploads[$first]['timestamp'] < self::NEW_UPLOAD_WINDOW
                    && $secondTime - $this->uploads[$second]['timestamp'] < self::NEW_UPLOAD_WINDOW) {
                    $pair *= self::NEW_UPLOAD_PAIR;
                }
                $this->addPair($source, $first, $second, $pair);
            }
        }
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

    // how close two authors are from follows/comments
    private function loadSocial(): void
    {
        $authors = array_flip(array_column($this->uploads, 'author'));
        $links = [];

        foreach ($this->database->fetchArray($this->database->query("SELECT user, id FROM user_follows")) as $row) {
            $fan = (int)$row['user'];
            $creator = (int)$row['id'];
            if ($fan !== $creator && isset($authors[$fan], $authors[$creator]) && !isset($this->ignoredUsers[$fan])) {
                $links[$fan][$creator] = ($links[$fan][$creator] ?? 0) + self::FOLLOW_LINK;
            }
        }

        foreach ($this->database->fetchArray($this->database->query(
            "SELECT c.author AS fan, v.author AS creator, COUNT(*) AS count
             FROM upload_comments c
             JOIN uploads v ON v.upload_id = c.location_id
             WHERE c.author != v.author
             GROUP BY c.author, v.author"
        )) as $row) {
            $fan = (int)$row['fan'];
            $creator = (int)$row['creator'];
            if (isset($authors[$fan], $authors[$creator]) && !isset($this->ignoredUsers[$fan])) {
                $count = (int)$row['count'];
                $links[$fan][$creator] = ($links[$fan][$creator] ?? 0) + self::COMMENT_LINK * $count / ($count + self::COMMENT_HALF);
            }
        }

        foreach ($links as $fan => $creators) {
            foreach ($creators as $creator => $strength) {
                $both = min(1.0, $strength + ($links[$creator][$fan] ?? 0));
                $this->social[$fan][$creator] = $both;
                $this->social[$creator][$fan] = $both;
            }
        }

        $pairs = intdiv(array_sum(array_map('count', $this->social)), 2);
        $this->stats['social_links'] = $pairs;
        $this->log("social: $pairs author pairs");
    }

    private function loadPlaylists(): void
    {
        // TODO: deal with autogenerated playlists
        $byPlaylist = [];
        foreach ($this->database->fetchArray($this->database->query(
            "SELECT pi.playlist, pi.upload, p.author
             FROM playlist_items pi
             JOIN playlists p ON p.id = pi.playlist
             ORDER BY pi.playlist, pi.position"
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

    private function loadText(): void
    {
        $count = max(1, count($this->uploads));
        $stopwords = array_flip(self::STOPWORDS) + $this->ignoredWords;

        $tags = [];
        foreach ($this->database->fetchArray($this->database->query(
            "SELECT ti.upload_id, t.name FROM upload_tag_index ti JOIN upload_tag_meta t ON t.tag_id = ti.tag_id"
        )) as $row) {
            $id = (int)$row['upload_id'];
            $tag = $this->normalize($row['name']);
            if (isset($this->uploads[$id]) && $tag !== '' && !isset($stopwords[$tag]) && !$this->isDate($tag)) {
                $tags[$id][$tag] = true;
            }
        }
        $this->uploadTags = $tags;
        $this->tagWeights = $this->weighWords($tags, (int)floor($count * self::COMMON_TAG_RATIO), $count);

        $words = [];
        foreach ($this->uploads as $id => $row) {
            foreach ($this->splitWords($row['title']) as $word) {
                if (!isset($stopwords[$word]) && !$this->isDate($word) && $this->longEnough($word, 2)) {
                    $words[$id][$word] = true;
                }
            }
        }
        $this->titleWeights = $this->weighWords($words, self::COMMON_WORD_COUNT, $count);
        $this->stats['dropped_title_words'] = $this->mostUsed($words, self::COMMON_WORD_COUNT);

        $words = [];
        foreach ($this->uploads as $id => $row) {
            $description = (string)preg_replace('~https?://\S+|www\.\S+~i', ' ', (string)$row['description']);
            foreach (array_slice($this->splitWords($description), 0, self::DESCRIPTION_MAX_WORDS) as $word) {
                if (!isset($stopwords[$word]) && !$this->isDate($word) && $this->longEnough($word, 3)) {
                    $words[$id][$word] = true;
                }
            }
        }
        $this->descriptionWeights = $this->weighWords($words, self::COMMON_WORD_COUNT, $count);

        $this->stats['generic_tags'] = array_slice($this->downweightGeneric($this->tagWeights, 't'), 0, 10, true);
        $this->stats['generic_title_words'] = array_slice($this->downweightGeneric($this->titleWeights, 'w'), 0, 10, true);

        $this->log(sprintf(
            "text: %d uploads with tags, %d with title words, %d with description words",
            count($this->tagWeights),
            count($this->titleWeights),
            count($this->descriptionWeights)
        ));
    }

    // words used too much to count at all, so the stats show what got thrown out
    private function mostUsed(array $words, int $maxUses): array
    {
        $uses = [];
        foreach ($words as $list) {
            foreach ($list as $word => $_) {
                $uses[$word] = ($uses[$word] ?? 0) + 1;
            }
        }

        $uses = array_filter($uses, fn($count) => $count > $maxUses);
        arsort($uses);
        return array_slice($uses, 0, 10, true);
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

    private function normalize(string $text): string
    {
        // titles from old imports can have broken utf-8, which makes the /u regexes return null
        $text = mb_strtolower((string)\Normalizer::normalize(mb_scrub($text, 'UTF-8'), \Normalizer::FORM_KC));

        // strip accents from latin letters only, doing it to ド would turn it into ト
        $text = (string)preg_replace('/(\p{Latin})\p{Mn}+/u', '$1', (string)\Normalizer::normalize($text, \Normalizer::FORM_D));
        $text = (string)\Normalizer::normalize($text, \Normalizer::FORM_C);

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    // years and dates like 20241105 link uploads that have nothing else in common
    private function isDate(string $word): bool
    {
        return preg_match('/^\d{6,}$/', $word) === 1
            || (preg_match('/^\d{4}$/', $word) === 1 && (int)$word >= 1800 && (int)$word <= 2099);
    }

    private function longEnough(string $word, int $min): bool
    {
        return mb_strlen($word) >= $min || preg_match('/[' . self::NO_SPACE_SCRIPTS . ']/u', $word) === 1;
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

    // downweight uploads with generic words
    private function downweightGeneric(array &$weights, string $prefix): array
    {
        $combined = [];
        foreach ($this->uploads as $id => $_) {
            $vector = [];
            foreach (['t' => $this->tagWeights, 'w' => $this->titleWeights, 'd' => $this->descriptionWeights] as $type => $all) {
                foreach ($all[$id] ?? [] as $word => $weight) {
                    $vector["$type:$word"] = $weight;
                }
            }
            $combined[$id] = $vector;
        }

        $uploadsByWord = [];
        foreach ($weights as $id => $words) {
            foreach ($words as $word => $_) {
                $uploadsByWord[$word][] = $id;
            }
        }

        // how much the uploads using a word have in common besides that word
        $focus = [];
        foreach ($uploadsByWord as $word => $ids) {
            if (count($ids) < self::GENERIC_MIN_USES) {
                continue;
            }

            $sum = [];
            foreach ($ids as $id) {
                $vector = $combined[$id];
                unset($vector["$prefix:$word"]);
                $length = sqrt(array_sum(array_map(fn($x) => $x * $x, $vector)));
                if ($length > 0) {
                    foreach ($vector as $key => $value) {
                        $sum[$key] = ($sum[$key] ?? 0) + $value / $length;
                    }
                }
            }
            $focus[$word] = sqrt(array_sum(array_map(fn($x) => $x * $x, $sum))) / count($ids);
        }

        if (!$focus) {
            return [];
        }

        $sorted = array_values($focus);
        sort($sorted);
        $typical = $sorted[intdiv(count($sorted), 2)];

        $factors = [];
        foreach ($focus as $word => $value) {
            if ($value < $typical) {
                $factors[$word] = $value / $typical;
            }
        }

        foreach ($weights as $id => $words) {
            foreach ($words as $word => $weight) {
                $weights[$id][$word] = $weight * ($factors[$word] ?? 1);
            }
        }

        asort($factors);
        $report = [];
        foreach ($factors as $word => $factor) {
            $report["$prefix:$word"] = round($factor, 2);
        }
        return $report;
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

    private function scoreUploads(): array
    {
        $engagement = [];
        $this->addActivity($engagement, 'interactions', self::WEIGHT_INTERACTIONS);
        $this->addActivity($engagement, 'playlists', self::WEIGHT_PLAYLISTS);

        $bubbleActivity = $engagement;
        $this->addActivity($bubbleActivity, 'user_views', self::WEIGHT_VIEWS);
        $this->pairs['user_views'] = [];

        $activity = [];
        $sources = [
            'views' => self::WEIGHT_VIEWS,
            'interactions' => self::WEIGHT_INTERACTIONS,
            'playlists' => self::WEIGHT_PLAYLISTS,
        ];

        foreach ($sources as $source => $sourceWeight) {
            $this->addActivity($activity, $source, $sourceWeight);
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

        // [similarity, score, activity, content, social]
        $candidates = [];
        foreach ($this->uploads as $id => $upload) {
            $content = [];
            $this->addWordMatches($content, $this->tagWeights[$id] ?? [], $tagIndex, self::WEIGHT_TAGS);
            $this->addWordMatches($content, $this->titleWeights[$id] ?? [], $titleIndex, self::WEIGHT_TITLE);
            $this->addWordMatches($content, $this->descriptionWeights[$id] ?? [], $descriptionIndex, self::WEIGHT_DESCRIPTION);
            $words = $content;
            unset($words[$id]);

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
            $bubbleActivityRow = $bubbleActivity[$id] ?? [];
            $engagementRow = $engagement[$id] ?? [];

            $social = [];
            $bigFriends = [];
            foreach ($this->social[$upload['author']] ?? [] as $friend => $strength) {
                $friendUploads = $byAuthor[$friend] ?? [];
                if (!$friendUploads) {
                    continue;
                }

                $weight = self::WEIGHT_SOCIAL * $strength * max(0, 1 - log(count($friendUploads)) / $logTotal);
                if (count($friendUploads) <= self::SMALL_AUTHOR) {
                    foreach ($friendUploads as $other) {
                        $social[$other] = $weight;
                    }
                } else {
                    $bigFriends[$friend] = $weight;
                }
            }
            if ($bigFriends) {
                foreach ($activityRow + $content as $other => $_) {
                    $weight = $bigFriends[$this->uploads[$other]['author']] ?? 0;
                    if ($weight > 0) {
                        $social[$other] = $weight;
                    }
                }
            }

            $scored = [];
            foreach ($activityRow + $content + $social as $other => $_) {
                $activityScore = $activityRow[$other] ?? 0.0;
                $contentScore = $content[$other] ?? 0.0;
                $socialScore = $social[$other] ?? 0.0;
                $trustBoth = sqrt($trust[$id] * $trust[$other]);

                // content keeps at least 30% so new uploads can show up next to popular ones
                $similarity = $trustBoth * $activityScore + max(0.3, 1 - $trustBoth) * $contentScore + $socialScore;
                if ($similarity < self::MIN_SCORE) {
                    continue;
                }

                if ($upload['type'] !== $this->uploads[$other]['type']) {
                    $similarity *= self::TYPE_MISMATCH;
                }

                $scored[$other] = [$similarity, $similarity * $this->quality[$other], $activityScore, $contentScore, $socialScore, $bubbleActivityRow[$other] ?? 0.0, $engagementRow[$other] ?? 0.0, $words[$other] ?? 0.0];
            }

            uasort($scored, fn($a, $b) => $b[1] <=> $a[1]);
            $candidates[$id] = array_slice($scored, 0, self::CANDIDATES_PER_UPLOAD, true);
            unset($activity[$id], $bubbleActivity[$id], $engagement[$id]);
        }

        $this->findBubbles($candidates);

        $out = [];
        foreach ($candidates as $id => $scored) {
            foreach (array_keys($scored) as $other) {
                $scored[$other][1] *= $this->bubbleFactor($id, $other);
            }
            uasort($scored, fn($a, $b) => $b[1] <=> $a[1]);

            $kept = [];
            $perAuthor = [];
            foreach ($scored as $other => [, $score, $activityScore, $contentScore, $socialScore]) {
                $author = $this->uploads[$other]['author'];
                if (($perAuthor[$author] ?? 0) >= self::MAX_PER_AUTHOR) {
                    continue;
                }
                $perAuthor[$author] = ($perAuthor[$author] ?? 0) + 1;

                $kept[$other] = ['score' => $score, 'activity' => $activityScore, 'content' => $contentScore + $socialScore];
                if (count($kept) >= self::RECOMMENDATIONS_PER_UPLOAD) {
                    break;
                }
            }

            $out[$id] = $kept;
        }

        $this->stats['uploads_with_recommendations'] = count(array_filter($out));
        return $out;
    }

    private function addActivity(array &$activity, string $source, float $sourceWeight): void
    {
        foreach ($this->pairs[$source] as $first => $others) {
            foreach ($others as $second => $together) {
                $similarity = $together / sqrt($this->totals[$source][$first] * $this->totals[$source][$second]);
                $similarity *= $together / ($together + self::MIN_EVIDENCE);

                $value = $sourceWeight * min(1.0, $similarity);
                $activity[$first][$second] = ($activity[$first][$second] ?? 0) + $value;
                $activity[$second][$first] = ($activity[$second][$first] ?? 0) + $value;
            }
        }
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

    private function addWordMatches(array &$content, array $words, array $index, float $weight): void
    {
        foreach ($words as $word => $mine) {
            foreach ($index[$word] as $other => $theirs) {
                $content[$other] = ($content[$other] ?? 0) + $weight * $mine * $theirs;
            }
        }
    }

    private function bubbleFactor(int $id, int $other): float
    {
        $mine = $this->bubbles[$id] ?? null;
        $theirs = $this->bubbles[$other] ?? null;
        if ($mine === null || $theirs === null) {
            return self::NEIGHBOR_BUBBLE;
        }
        $mineCounts = isset($this->countedBubbles[$mine]);
        $theirsCount = isset($this->countedBubbles[$theirs]);

        if ($mine === $theirs && $mineCounts) {
            return self::SAME_BUBBLE;
        }
        if ($this->topics[$mine] !== null && $this->topics[$mine] === $this->topics[$theirs]) {
            return self::SAME_TOPIC;
        }
        if (!$mineCounts || !$theirsCount) {
            return self::NEIGHBOR_BUBBLE;
        }
        return isset($this->neighborBubbles[$mine][$theirs]) ? self::NEIGHBOR_BUBBLE : self::OTHER_BUBBLE;
    }

    private function findBubbles(array $candidates): void
    {
        // links mostly come from views/ratings/playlists, words and friendships alone cause too many cross-topic bubbles
        $edges = [];
        $topicEdges = [];
        foreach ($candidates as $id => $scored) {
            $links = [];
            $topicLinks = [];
            foreach ($scored as $other => [, , , $contentScore, $socialScore, $activityScore, $engagementScore, $wordScore]) {
                $links[$other] = [
                    'activity' => self::BUBBLE_ACTIVITY * $engagementScore,
                    'content' => $wordScore,
                    'social' => self::BUBBLE_SOCIAL * $socialScore,
                ];
                $topicLinks[$other] = $activityScore + self::TOPIC_CONTENT * $contentScore + self::BUBBLE_SOCIAL * $socialScore;
            }
            uasort($links, fn($a, $b) => array_sum($b) <=> array_sum($a));
            foreach (array_slice($links, 0, self::BUBBLE_EDGES, true) as $other => $parts) {
                $edges[] = [$id, $other, $parts];
            }
            arsort($topicLinks);
            foreach (array_slice($topicLinks, 0, self::BUBBLE_EDGES, true) as $other => $weight) {
                $topicEdges[] = [$id, $other, $weight];
            }
        }

        $assigned = $this->termBubbles();
        $sizes = array_count_values($assigned);
        $assigned = array_filter($assigned, fn($group) => $sizes[$group] >= self::MIN_BUBBLE_SIZE);
        $fromText = count($assigned);

        // no usable words, so go by same uploader and ratings/comments/playlists
        for ($round = 0; $round < self::FILL_ROUNDS; $round++) {
            $added = 0;
            foreach ($candidates as $id => $scored) {
                if (isset($assigned[$id])) {
                    continue;
                }
                $votes = [];
                foreach ($scored as $other => [, , , $contentScore, , , $engagementScore]) {
                    if (isset($assigned[$other])) {
                        $votes[$assigned[$other]] = ($votes[$assigned[$other]] ?? 0) + $contentScore + $engagementScore;
                    }
                }
                if ($votes) {
                    arsort($votes);
                    $assigned[$id] = key($votes);
                    $added++;
                }
            }
            if ($added === 0) {
                break;
            }
        }

        // one person's uploads aren't a bubble, merge them into the one they're most linked to
        for ($round = 0; $round < self::FILL_ROUNDS; $round++) {
            $authors = [];
            $members = [];
            foreach ($assigned as $id => $bubble) {
                $authors[$bubble][$this->uploads[$id]['author']] = true;
                $members[$bubble][] = $id;
            }

            $merged = 0;
            foreach ($members as $bubble => $ids) {
                if (count($authors[$bubble]) >= self::MIN_BUBBLE_AUTHORS) {
                    continue;
                }
                $votes = [];
                foreach ($ids as $id) {
                    foreach ($candidates[$id] ?? [] as $other => [, , , $contentScore, $socialScore, , $engagementScore]) {
                        $target = $assigned[$other] ?? null;
                        if ($target !== null && $target !== $bubble) {
                            $votes[$target] = ($votes[$target] ?? 0) + $contentScore + $socialScore + $engagementScore;
                        }
                    }
                }
                if ($votes) {
                    arsort($votes);
                    $target = key($votes);
                    foreach ($ids as $id) {
                        $assigned[$id] = $target;
                    }
                    $merged++;
                }
            }
            if ($merged === 0) {
                break;
            }
        }

        $renumber = [];
        foreach ($assigned as $id => $bubble) {
            $renumber[$bubble] ??= count($renumber);
            $this->bubbles[$id] = $renumber[$bubble];
        }

        $bubbleGraph = array_fill_keys(array_values($renumber), []);
        foreach ($topicEdges as [$a, $b, $weight]) {
            if (!isset($this->bubbles[$a], $this->bubbles[$b])) {
                continue;
            }
            $x = $this->bubbles[$a];
            $y = $this->bubbles[$b];
            $bubbleGraph[$x][$y] = ($bubbleGraph[$x][$y] ?? 0) + $weight;
            $bubbleGraph[$y][$x] = ($bubbleGraph[$y][$x] ?? 0) + $weight;
        }

        // the same thing one level up: bubbles that are linked more than chance end up in one topic
        $rawTopics = $this->louvain($bubbleGraph, self::TOPIC_RESOLUTION);

        $bubbleAuthors = [];
        $topicAuthors = [];
        foreach ($this->bubbles as $id => $bubble) {
            $bubbleAuthors[$bubble][$this->uploads[$id]['author']] = true;
            $topicAuthors[$rawTopics[$bubble]][$this->uploads[$id]['author']] = true;
        }

        $topicIds = [];
        foreach ($rawTopics as $bubble => $topic) {
            if (count($bubbleAuthors[$bubble]) >= self::MIN_BUBBLE_AUTHORS) {
                $this->countedBubbles[$bubble] = true;
            }
            if (count($topicAuthors[$topic]) >= self::MIN_BUBBLE_AUTHORS) {
                $topicIds[$topic] ??= count($topicIds);
                $this->topics[$bubble] = $topicIds[$topic];
            } else {
                $this->topics[$bubble] = null;
            }
        }

        foreach ($edges as [$a, $b, $parts]) {
            if (!isset($this->bubbles[$a], $this->bubbles[$b]) || $this->bubbles[$a] !== $this->bubbles[$b]) {
                continue;
            }
            foreach ($parts as $type => $value) {
                $this->bubbleLinks[$this->bubbles[$a]][$type] = ($this->bubbleLinks[$this->bubbles[$a]][$type] ?? 0) + $value;
            }
        }
        foreach ($this->bubbleLinks as $bubble => $parts) {
            $total = array_sum($parts) ?: 1;
            $this->bubbleLinks[$bubble] = array_map(fn($value) => round($value / $total, 2), $parts);
        }

        // link each bubble to the bubbles its uploads get recommended next to most
        $between = [];
        foreach ($candidates as $id => $scored) {
            if (!isset($this->bubbles[$id])) {
                continue;
            }
            foreach ($scored as $other => [$similarity]) {
                if (isset($this->bubbles[$other]) && $this->bubbles[$other] !== $this->bubbles[$id]) {
                    $between[$this->bubbles[$id]][$this->bubbles[$other]] = ($between[$this->bubbles[$id]][$this->bubbles[$other]] ?? 0) + $similarity;
                }
            }
        }
        foreach ($between as $bubble => $others) {
            arsort($others);
            $total = array_sum($others);
            $first = true;
            foreach (array_slice($others, 0, self::NEIGHBOR_BUBBLES, true) as $other => $weight) {
                if ($first || $weight / $total >= self::NEIGHBOR_BUBBLE_MIN_SHARE) {
                    $this->neighborBubbles[$bubble][$other] = true;
                    $this->neighborBubbles[$other][$bubble] = true;
                }
                $first = false;
            }
        }

        $sizes = array_count_values(array_filter($this->bubbles, fn($bubble) => isset($this->countedBubbles[$bubble])));
        arsort($sizes);
        $this->stats['bubbles'] = count($sizes);
        $this->stats['topics'] = count($topicIds);
        $this->stats['one_author_bubbles'] = count($renumber) - count($sizes);
        $this->stats['biggest_bubbles'] = array_slice(array_values($sizes), 0, 5);
        $this->stats['uploads_bubbled_from_text'] = $fromText;
        $this->stats['uploads_without_bubble'] = count($this->uploads) - array_sum($sizes);
        $this->stats['bubbles_without_neighbors'] = count($renumber) - count($this->neighborBubbles);
        $this->log(sprintf(
            "bubbles: %d in %d topics (+%d one-author ones), biggest %s. %d uploads placed by their words, %d without a bubble, %d bubbles with no neighbors",
            count($sizes),
            count($topicIds),
            $this->stats['one_author_bubbles'],
            implode('/', array_slice($sizes, 0, 5)),
            $fromText,
            $this->stats['uploads_without_bubble'],
            $this->stats['bubbles_without_neighbors']
        ));
    }

    // groups words that get used together a lot, then puts each upload in the group its words point to
    private function termBubbles(): array
    {
        $total = max(1, count($this->uploads));

        $terms = [];
        foreach ($this->uploads as $id => $row) {
            $list = [];
            foreach ($this->descriptionWeights[$id] ?? [] as $word => $_) {
                $list[$word] = self::TERM_DESCRIPTION;
            }
            foreach ($this->titleWeights[$id] ?? [] as $word => $_) {
                $list[$word] = self::TERM_TITLE;
            }
            foreach ($this->uploadTags[$id] ?? [] as $tag => $_) {
                $list[$tag] = self::TERM_TAG;
            }
            if ($list) {
                $terms[$id] = $list;
            }
        }

        $uses = [];
        $authors = [];
        foreach ($terms as $id => $list) {
            foreach ($list as $term => $_) {
                $uses[$term] = ($uses[$term] ?? 0) + 1;
                $authors[$term][$this->uploads[$id]['author']] = true;
            }
        }
        // skip keymashes, numbers and words only one person uses
        $uses = array_filter(
            $uses,
            fn($count, $term) => $count >= self::TERM_MIN_TOGETHER && count($authors[$term]) >= self::TERM_MIN_AUTHORS && !ctype_digit((string)$term),
            ARRAY_FILTER_USE_BOTH
        );

        $together = [];
        foreach ($terms as $id => $list) {
            $list = array_intersect_key($list, $uses);
            $ranked = [];
            foreach ($list as $term => $weight) {
                $ranked[$term] = $weight * log($total / $uses[$term]);
            }
            arsort($ranked);
            $terms[$id] = array_intersect_key($list, array_slice($ranked, 0, self::TERMS_PER_UPLOAD, true));

            $keys = array_keys($terms[$id]);
            sort($keys);
            $count = count($keys);
            for ($a = 0; $a < $count; $a++) {
                for ($b = $a + 1; $b < $count; $b++) {
                    $together[$keys[$a]][$keys[$b]] = ($together[$keys[$a]][$keys[$b]] ?? 0) + 1;
                }
            }
        }

        // "funny" shows up next to anything, "roblox" mostly next to roblox stuff.
        // compare the words around each word to the site as a whole
        $partners = [];
        foreach ($together as $a => $others) {
            foreach ($others as $b => $count) {
                $partners[$a][$b] = $count;
                $partners[$b][$a] = $count;
            }
        }
        $allUses = array_sum($uses);
        $specific = [];
        foreach ($uses as $term => $_) {
            $sum = array_sum($partners[$term] ?? []);
            $divergence = 0.0;
            foreach ($partners[$term] ?? [] as $other => $count) {
                $share = $count / $sum;
                $divergence += $share * log($share / ($uses[$other] / $allUses));
            }
            $specific[$term] = $divergence * $sum / ($sum + self::TERM_EVIDENCE);
        }
        $sorted = array_values(array_filter($specific));
        sort($sorted);
        $typical = $sorted ? $sorted[intdiv(count($sorted), 2)] : 1.0;
        foreach ($specific as $term => $value) {
            $specific[$term] = min(1.0, $value / $typical);
        }

        $graph = array_fill_keys(array_keys($uses), []);
        foreach ($together as $a => $others) {
            foreach ($others as $b => $count) {
                if ($count >= self::TERM_MIN_TOGETHER && $total * $count > $uses[$a] * $uses[$b]) {
                    $graph[$a][$b] = $count / sqrt($uses[$a] * $uses[$b]) * $specific[$a] * $specific[$b];
                    $graph[$b][$a] = $graph[$a][$b];
                }
            }
        }
        unset($together, $partners);
        $groups = $this->louvain($graph, self::BUBBLE_RESOLUTION);

        $out = [];
        foreach ($terms as $id => $list) {
            $votes = [];
            foreach ($list as $term => $weight) {
                $votes[$groups[$term]] = ($votes[$groups[$term]] ?? 0) + $weight * $specific[$term] * log(1 + $uses[$term]);
            }
            arsort($votes);
            if ($votes && reset($votes) > 0) {
                $out[$id] = key($votes);
            }
        }

        // move each upload to the group where most uploads sharing its words ended up
        for ($round = 0; $round < self::REFINE_ROUNDS; $round++) {
            $where = [];
            $groupSizes = array_count_values($out);
            $placedTotal = max(1, count($out));
            foreach ($out as $id => $group) {
                foreach ($terms[$id] as $term => $_) {
                    $where[$term][$group] = ($where[$term][$group] ?? 0) + 1;
                }
            }

            $moved = 0;
            foreach ($terms as $id => $list) {
                $mine = $out[$id] ?? null;
                $votes = [];
                foreach ($list as $term => $weight) {
                    $placed = $where[$term] ?? [];
                    if ($mine !== null) {
                        $placed[$mine]--;
                    }
                    $count = array_sum($placed);
                    if ($count < self::MIN_BUBBLE_SIZE) {
                        continue;
                    }
                    $trust = $count / ($count + self::REFINE_EVIDENCE);
                    // weighed against group size, otherwise big groups win by default
                    foreach ($placed as $group => $n) {
                        if ($n > 0) {
                            $lift = ($n / $count) / ($groupSizes[$group] / $placedTotal);
                            $votes[$group] = ($votes[$group] ?? 0) + $weight * $trust * ($n / $count) * log(1 + $lift);
                        }
                    }
                }
                if (!$votes) {
                    continue;
                }
                arsort($votes);
                $best = key($votes);
                if ($best !== $mine) {
                    $out[$id] = $best;
                    $moved++;
                }
            }
            $groupSizes = array_count_values($out);
            $out = array_filter($out, fn($group) => $groupSizes[$group] >= self::MIN_BUBBLE_SIZE);
            if ($moved === 0) {
                break;
            }
        }

        $common = array_filter($uses, fn($count) => $count >= 20);
        $generic = array_intersect_key($specific, $common);
        asort($generic);
        $this->stats['generic_words'] = array_map(fn($value) => round($value, 2), array_slice($generic, 0, 15, true));
        $this->stats['word_groups'] = count(array_unique($groups));
        return $out;
    }

    private function louvain(array $graph, float $resolution): array
    {
        $membership = array_combine(array_keys($graph), array_keys($graph));

        while (true) {
            $community = $this->louvainPass($graph, $resolution);
            if (count(array_unique($community)) === count($graph)) {
                return $membership;
            }

            foreach ($membership as $id => $node) {
                $membership[$id] = $community[$node];
            }

            $merged = [];
            foreach ($graph as $a => $neighbors) {
                $merged[$community[$a]] ??= [];
                foreach ($neighbors as $b => $weight) {
                    $merged[$community[$a]][$community[$b]] = ($merged[$community[$a]][$community[$b]] ?? 0) + $weight;
                }
            }
            $graph = $merged;
        }
    }

    private function louvainPass(array $graph, float $resolution): array
    {
        $degree = [];
        foreach ($graph as $node => $neighbors) {
            $degree[$node] = array_sum($neighbors);
        }
        $total = array_sum($degree);

        $community = array_combine(array_keys($graph), array_keys($graph));
        $communityDegree = $degree;

        // nothing linked yet, e.g. a fresh install
        if ($total == 0) {
            return $community;
        }

        for ($pass = 0; $pass < 20; $pass++) {
            $moves = 0;
            foreach ($graph as $node => $neighbors) {
                $current = $community[$node];
                $links = [];
                foreach ($neighbors as $other => $weight) {
                    if ($other !== $node) {
                        $links[$community[$other]] = ($links[$community[$other]] ?? 0) + $weight;
                    }
                }

                $communityDegree[$current] -= $degree[$node];
                $best = $current;
                $bestGain = ($links[$current] ?? 0) - $resolution * $communityDegree[$current] * $degree[$node] / $total;
                foreach ($links as $candidate => $weight) {
                    $gain = $weight - $resolution * $communityDegree[$candidate] * $degree[$node] / $total;
                    if ($gain > $bestGain) {
                        $best = $candidate;
                        $bestGain = $gain;
                    }
                }
                $communityDegree[$best] += $degree[$node];

                if ($best !== $current) {
                    $community[$node] = $best;
                    $moves++;
                }
            }

            if ($moves === 0) {
                break;
            }
        }

        return $community;
    }

    private function save(bool $force): void
    {
        // built in separate tables and swapped in afterwards
        foreach (self::TABLES as $table => $sql) {
            $this->database->query("DROP TABLE IF EXISTS {$table}_next");
            $this->database->query(sprintf($sql, "{$table}_next"));
            $this->database->query(sprintf($sql, $table));
        }

        $written = $this->insertRows('upload_recommendations_next', ['upload', 'related', 'score', 'activity', 'content'], (function () {
            foreach ($this->recommendations as $upload => $related) {
                foreach ($related as $other => $scores) {
                    yield [$upload, $other, $scores['score'], $scores['activity'], $scores['content']];
                }
            }
        })());

        $this->insertRows('upload_bubbles_next', ['upload', 'bubble'], (function () {
            foreach ($this->bubbles as $upload => $bubble) {
                yield [$upload, $bubble];
            }
        })());

        $built = time();
        $this->insertRows('recommendation_bubbles_next', ['bubble', 'size', 'words', 'authors', 'neighbors', 'topic', 'counts', 'untagged', 'links', 'built'], (function () use ($built) {
            foreach ($this->describeBubbles() as $bubble => $info) {
                yield [
                    $bubble,
                    $info['size'],
                    json_encode($info['words'], JSON_UNESCAPED_UNICODE),
                    json_encode($info['authors'], JSON_FORCE_OBJECT),
                    json_encode($info['neighbors']),
                    $info['topic'],
                    (int)$info['counts'],
                    $info['untagged'],
                    json_encode($info['links'], JSON_FORCE_OBJECT),
                    $built,
                ];
            }
        })());

        $this->stats['rows_written'] = $written;

        // don't throw out good data because of a bad build
        $current = (int)$this->database->result("SELECT COUNT(*) FROM upload_recommendations");
        if (!$force && $current > 0 && $written < $current * self::MIN_ROWS_RATIO) {
            $this->dropTables('_next');
            throw new RuntimeException("new build has $written rows, current one has $current. not saving, use --force if that's expected");
        }

        // left behind if a previous run died mid swap
        $this->dropTables('_old');

        // RENAME waits for running queries so we don't wait forever
        $this->database->query("SET SESSION lock_wait_timeout = 15");
        $renames = [];
        foreach (array_keys(self::TABLES) as $table) {
            $renames[] = "$table TO {$table}_old, {$table}_next TO $table";
        }
        try {
            $this->database->query("RENAME TABLE " . implode(', ', $renames));
        } catch (\PDOException $e) {
            $this->dropTables('_next');
            throw new RuntimeException("couldn't swap tables: " . $e->getMessage());
        }
        $this->dropTables('_old');

        $this->log("saved $written rows (was $current)");
    }

    private function insertRows(string $table, array $columns, iterable $rows): int
    {
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ";

        $values = [];
        $params = [];
        $written = 0;
        foreach ($rows as $row) {
            $values[] = $placeholders;
            array_push($params, ...$row);
            $written++;

            if (count($values) >= 1000) {
                $this->database->query($sql . implode(',', $values), $params);
                $values = [];
                $params = [];
            }
        }
        if ($values) {
            $this->database->query($sql . implode(',', $values), $params);
        }

        return $written;
    }

    private function dropTables(string $suffix): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            $this->database->query("DROP TABLE IF EXISTS {$table}{$suffix}");
        }
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }
}