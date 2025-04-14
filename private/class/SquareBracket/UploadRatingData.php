<?php

namespace SquareBracket;

class UploadRatingData
{
    private $upload_rating_data;

    public function __construct($database, $upload_id) {
        // we don't need $this->database nor $this->upload_id.

        $this->upload_rating_data = array_fill_keys(range(1, 5), 0);
        $ratings = $database->query("SELECT rating, COUNT(*) as count FROM upload_ratings WHERE video=? GROUP BY rating", [$upload_id]);

        foreach ($ratings as $row) {
            $this->upload_rating_data[(string)$row['rating']] = $row['count'];
        }
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