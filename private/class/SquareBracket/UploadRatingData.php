<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

namespace SquareBracket;

class UploadRatingData
{
    private $upload_rating_data;

    public function __construct($database, $upload_id)
    {
        // we don't need $this->database nor $this->upload_id.

        $this->upload_rating_data = array_fill_keys(range(1, 5), 0);
        $ratings = $database->fetchArray(
            $database->query("SELECT rating, COUNT(*) as count FROM upload_ratings WHERE video=? GROUP BY rating", [$upload_id])
        );

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
