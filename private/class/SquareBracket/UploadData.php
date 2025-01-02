<?php

namespace SquareBracket;

/**
 * uploads.
 */
class UploadData
{
    private \SquareBracket\Database $database;
    private $takedown;
    private $data;
    private $tags;
    private $deleted_data;

    // FLAGS

    // 00000001: featured upload flag that was used in mid-2021.
    public const FLAG_FEATURED = 1;

    // 00000010: Unprocessed VIDEO upload.
    public const FLAG_UNPROCESSED = 2;

    // 00000100: "Block guests from viewing this upload"
    public const FLAG_BLOCK_GUESTS = 4;

    // 00001000: "Block users from commenting in this upload"
    public const FLAG_BLOCK_COMMENTS = 8;

    // 00010000: "upload has custom thumbnail"
    public const FLAG_CUSTOM_THUMBNAIL = 16;

    public function __construct(\SquareBracket\Database $database, $id)
    {
        $this->database = $database;

        $this->deleted_data = $this->database->fetch("SELECT COUNT(*) FROM upload_deleted v WHERE id = ?", [$id])["COUNT(*)"];

        // if we get the internal id instead of the string id, we correct $id after fetching the upload otherwise
        // stuff won't work.
        if (is_int($id)) {
            $this->data = $this->database->fetch("SELECT v.* FROM uploads v WHERE v.id = ?", [$id]);
            if ($this->data != []) {
                $id = $this->data["video_id"];
            }
        } else {
            $this->data = $this->database->fetch("SELECT v.* FROM uploads v WHERE v.video_id = ?", [$id]);
        }
        if ($this->data != []) {
            $this->takedown = $this->database->fetchArray($this->database->query("SELECT * FROM upload_takedowns t WHERE t.submission = ?", [$id]));
            $this->tags = $this->database->fetchArray($this->database->query("SELECT * FROM `upload_tag_index` ti JOIN upload_tag_meta t ON (t.tag_id = ti.tag_id) WHERE ti.video_id = ?", [$this->data["id"]]));
        }
    }

    public function getTakedown()
    {
        return $this->takedown;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function isDeleted()
    {
        return $this->deleted_data;
    }

    public function bitmaskToArray()
    {
        if ($this->data != []) {
            return [
                "featured" => (bool)($this->data["flags"] & $this::FLAG_FEATURED),
                "unprocessed" => (bool)($this->data["flags"] & $this::FLAG_UNPROCESSED),
                "block_guests" => (bool)($this->data["flags"] & $this::FLAG_BLOCK_GUESTS),
                "block_comments" => (bool)($this->data["flags"] & $this::FLAG_BLOCK_COMMENTS),
                "custom_thumbnail" => (bool)($this->data["flags"] & $this::FLAG_CUSTOM_THUMBNAIL),
            ];
        }
    }
}