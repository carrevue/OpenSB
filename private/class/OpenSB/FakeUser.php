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


namespace OpenSB;

class FakeUser
{
    /**
     * @var array Fake user array, which pretends to be user data from the database
     */
    private static array $fakeUsers = [
        "-1000" => [
            "id" => -1000,
            "name" => "System",
            "email" => "system@opensb",
            "joined" => 0,
            "last_seen" => 0,
            "birthdate" => "2021-04-24",
            "featured_upload" => 0,
            "title" => "System",
            "about" => "This is the system user.",
            "userlink_color" => "#ff0000",
            "flags" => 0,
            "powerlevel" => 4, // owner
        ]
    ];

    public static function getFakeUserFromID(int $id): ?array
    {
        return self::$fakeUsers[$id] ?? null;
    }

    public static function getFakeUserFromName(string $name): ?array
    {
        foreach (self::$fakeUsers as $user) { // kind of ass?
            if (strcasecmp($user['name'], $name) === 0) {
                return $user;
            }
        }

        return null;
    }
}