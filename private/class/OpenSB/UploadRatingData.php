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

namespace OpenSB;

use OpenSB\Database;

/**
 * class UploadRatingData
 * 
 * Helper class for an upload's rating.
 */
class UploadRatingData
{
    /**
     * @var mixed
     */
    private $upload_rating_data;

    /**
     * function __construct
     *
     * @param Database $database
     * @param string $upload_id
     *
     * @return void
     */
    public function __construct(Database $database, string $upload_id)
    {
        // we don't need $this->database nor $this->upload_id.

        $this->upload_rating_data = array_fill_keys(range(1, 5), 0);
        $ratings = $database->fetchArray(
            $database->query("SELECT rating, COUNT(*) as count FROM upload_ratings WHERE upload=? GROUP BY rating", [$upload_id])
        );

        foreach ($ratings as $row) {
            $this->upload_rating_data[(string)$row['rating']] = $row['count'];
        }
    }

    /**
     * function calculateRatingData
     *
     * Calculate the upload's ratings as 5 stars.
     *
     * @return array
     */
    public function calculateRatingData(): array
    {
        $ratings = $this->upload_rating_data;

        $total = array_sum($ratings);

        $weighted = 0;
        foreach ($ratings as $score => $count) {
            $weighted += (int)$score * $count;
        }
        
        return [
            "stars"   => $ratings,
            "total"   => $total,
            "average" => $total ? ($weighted / $total) : 0,
        ];
    }

    /**
     * function calculateRatingDataAsLikeRatio
     *
     * Calculate the upload's ratings as like/dislike ratio.
     *
     * @return array
     */
    public function calculateRatingDataAsLikeRatio(): array
    {
        $likes = $this->upload_rating_data["4"] + $this->upload_rating_data["5"];
        $dislikes = $this->upload_rating_data["1"] + $this->upload_rating_data["2"];
        $total = $likes + $dislikes;

        // calculate finalium likesaber
        $ratio = ($total == 0 || $dislikes == 0)  ? 100
            : Utilities::calculatePercentage($dislikes, $likes, $total);

        return [
            "likes" => $likes,
            "dislikes" => $dislikes,
            "total" => $total,
            "ratio" => $ratio,
           //"current_rating" => $current_rating,
        ];
    }
}
