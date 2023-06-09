<?php

namespace openSB;
class Videos
{
    // Functions related to sbNext Video Stuff.
    public static function categoryIDToName($id)
    {
        switch ($id) {
            case 0:
                $name = __('Miscellaneous');
                break;
            case 1:
                $name = __('Entertainment');
                break;
            case 2:
                $name = __('Comedy & Humor');
                break;
            case 3:
                $name = __('Gaming');
                break;
            case 4:
                $name = __('News and Information');
                break;
            case 5:
                $name = __('Lifestyle');
                break;
            case 6:
                $name = __('Science & Technology');
                break;
            case 7:
                $name = __('Archival');
                break;
        }
        return $name;
    }

    public static function type_to_cat($type)
    {
        switch ($type) {
            case 'misc':
                $cat = 0;
                break;
            case 'entertainment':
                $cat = 1;
                break;
            case 'comedy':
                $cat = 2;
                break;
            case 'gaming':
                $cat = 3;
                break;
            case 'news':
                $cat = 4;
                break;
            case 'life':
                $cat = 5;
                break;
            case 'technology':
                $cat = 6;
                break;
            case 'backup':
                $cat = 7;
                break;
        }
        return $cat;
    }

    // This is for the like-to-dislike ratio lightsaber. -Gamerappa, November 2nd 2021
    public static function calculateRatio($number, $percent, $total)
    {
        // If there's no ratio or dislikes, it returns 100.
        if ($total == 0 or $number == 0) {
            return 100;
        } else {
            // It returns the Like-to-dislike ratio.
            return ($percent / $total) * $number * 100;
        }
    }
	
    /**
     * Return a list of videos, Limit and order is required.
     *
     * @param string $orderBy A column in the videos table.
     * @param int $limit The limit.
     * @param string $whereSomething Precise what column.
     * @param string $whereEquals Precise the value of the column.
     * @return array A video list, ordered by what $orderBy specified.
     */
    public static function getVideos($orderBy, $limit, $whereSomething = null, $whereEquals = null): array
    {
        global $userfields, $videofields, $sql;
        if (isset($whereSomething)) {
            $videoList = $sql->fetchArray($sql->query("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE $whereSomething = ? AND flags != 0x2 ORDER BY $orderBy LIMIT $limit", [$whereEquals]));
        } else {
            $videoList = $sql->fetchArray($sql->query("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE flags != 0x2 ORDER BY $orderBy LIMIT $limit"));
        }
        foreach ($videoList as &$video) {
            $video['tags'] = VideoTags::getVideoTags($video['id']);
        }
        return $videoList;
    }

    /**
     * Return the interger ID of a video.
     *
     * @param string $video The randomized video ID.
     * @return int the ID of a video.
     */
    public static function getVideoIntID($video): int
    {
        global $sql;
        return $sql->result("SELECT id FROM videos WHERE video_id = ?", [$video]);
    }

    /**
     * Return a list of videos in an alternative way.
     *
     * @param string $whereSomething Precise what column.
     * @param string $whereEquals Precise the value of the column.
     * @return array A video list, ordered by what $orderBy specified.
     */
    public static function fetchVideos($whereSomething, $whereEquals, $orderBy = null, $limit = null)
    {
        global $userfields, $videofields, $sql;
        if (isset($orderBy, $limit)) {
            $videoList = $sql->fetch("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE $whereSomething = ? AND flags != 0x2 ORDER BY $orderBy LIMIT $limit", [$whereEquals]);
        } elseif (isset($orderBy)) {
            $videoList = $sql->fetch("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE $whereSomething = ? AND flags != 0x2 ORDER BY $orderBy", [$whereEquals]);
        } elseif (isset($limit)) {
            $videoList = $sql->fetch("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE $whereSomething = ? AND flags != 0x2 LIMIT $limit", [$whereEquals]);
        } else {
            $videoList = $sql->fetch("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE $whereSomething = ? AND flags != 0x2", [$whereEquals]);
        }
        return $videoList;
    }

    /**
     * Return the randomized ID of a video.
     *
     * @param string $video The randomized video ID.
     * @return string the ID of a video.
     */
    public static function getVideoRanID($video): string
    {
        global $sql;
        return $sql->result("SELECT video_id FROM videos WHERE id = ?", [$video]);
    }

    public static function getLatestVideo($userID)
    {
        $video = Videos::fetchVideos("author", $userID, "v.id DESC", 1);
        return $video;
    }

    public static function addVideo($new, $title, $description, $id, string $upload_file): void
    {
        global $sql;
        $sql->query("INSERT INTO videos (video_id, title, description, author, time, most_recent_view, videofile, flags) VALUES (?,?,?,?,?,?,?,?)", [$new, $title, $description, $id, time(), time(), $upload_file, 0x2]);
    }

    public static function bumpVideo(int $currentTime, $id): void
    {
        global $sql;
        $sql->query("UPDATE videos SET most_recent_view = ? WHERE video_id = ?", [$currentTime, $id]);
    }

    public static function getVideoFile($videoData)
    {
        global $isQoboTV, $bunnySettings;
        if ($isQoboTV) {
            if ($videoData['post_type'] == 0) {
                // videofile on videos using bunnycdn are the guid, don't ask me why. -grkb 4/8/2023
                $bunny_url = "https://" . $bunnySettings["streamHostname"] . "/" . $videoData["videofile"] . "/playlist.m3u8";
            } elseif ($videoData['post_type'] == 2) {
                // https://qobo-grkb.b-cdn.net/dynamic/art/f_eKEJNj4bm.png
                $bunny_url = "https://" . $bunnySettings["pullZone"] . $videoData["videofile"];
            }
            return $bunny_url;
        } else {
            return $videoData['videofile'];
        }
    }

    public static function getVideoData($userfields, $id)
    {
        global $sql, $isQoboTV, $bunnySettings;

        $videoData = $sql->fetch("SELECT $userfields v.* FROM videos v JOIN users u ON v.author = u.id WHERE v.video_id = ?", [$id]);
        if (!$videoData) {
            return false;
        }
        $videoData['views'] = $sql->fetch("SELECT COUNT(video_id) FROM views WHERE video_id=?", [$videoData['video_id']]) ['COUNT(video_id)'];
        $videoData['file'] = Videos::getVideoFile($videoData);
        return $videoData;
    }
}