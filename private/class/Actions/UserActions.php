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

namespace Actions;

use Core\Utilities;
use Data\Notification\NotificationEnum;

class UserActions extends BaseActions
{
    public function toggleFollow(int $member): array|ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        if ($member == $user) {
            return ActionError::CannotFollowSelf;
        }

        if (!$this->database->result("SELECT COUNT(*) FROM users WHERE id = ?", [$member])) {
            return ActionError::InvalidUser;
        }

        $number = $this->database->result("SELECT f_index FROM users WHERE id = ?", [$member]);

        if ($this->database->result("SELECT COUNT(user) FROM user_follows WHERE user=? AND id=?", [$user, $member]) != 0) {
            $this->database->query("DELETE FROM user_follows WHERE user=? AND id=?", [$user, $member]);
            $result = false;
            $number = $number - 1;
        } else {
            $this->database->query("INSERT INTO user_follows (id, user) VALUES (?,?)", [$member, $user]);
            $result = true;
            $number = $number + 1;

            Utilities::notifyUser($this->database, $member, 0, 0, NotificationEnum::Follow);
        }

        $this->database->query("UPDATE users SET f_index = ? WHERE id = ?", [$number, $member]);

        return [
            "followed" => $result,
            "number" => $number,
        ];
    }
}
