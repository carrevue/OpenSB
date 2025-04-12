<?php

namespace SquareBracket;

class UploadRatingData
{
    private $database;
    private $upload_id;
    private $upload_rating_data;

    public function __construct($database, $upload_id) {
        $this->database = $database;
        $this->upload_id = $upload_id;

        $this->upload_rating_data = [
            "1" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=1", [$upload_id]),
            "2" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=2", [$upload_id]),
            "3" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=3", [$upload_id]),
            "4" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=4", [$upload_id]),
            "5" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=5", [$upload_id]),
        ];
    }

    /**
     * Calculate the upload's ratings.
     */
    public function calculateRatingData(): array
    {
        $total_ratings = ($this->upload_rating_data["1"] +
            $this->upload_rating_data["2"] +
            $this->upload_rating_data["3"] +
            $this->upload_rating_data["4"] +
            $this->upload_rating_data["5"]);

        if ($total_ratings == 0) {
            $average_ratings = 0;
        } else {
            $average_ratings = ($this->upload_rating_data["1"] +
                    $this->upload_rating_data["2"] * 2 +
                    $this->upload_rating_data["3"] * 3 +
                    $this->upload_rating_data["4"] * 4 +
                    $this->upload_rating_data["5"] * 5) / $total_ratings;
        }

        return [
            "stars" => $this->upload_rating_data,
            "total" => $total_ratings,
            "average" => $average_ratings,
        ];
    }
}