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

use Core\Authentication;
use Core\Database;
use Core\SquareBracket;
use Data\User\UserRoleEnum;

abstract class BaseActions
{
    protected SquareBracket $sb;

    protected Database $database;

    protected Authentication $auth;

    public function __construct(SquareBracket $sb)
    {
        $this->sb = $sb;
        $this->database = $sb->getDatabaseClass();
        $this->auth = $sb->getAuthenticationClass();
    }

    protected function requireLogin(): int|ActionError
    {
        if (!$this->auth->isLoggedIn()) {
            return ActionError::LoginRequired;
        }

        if ($this->auth->isBanned()) {
            return ActionError::Banned;
        }

        return $this->auth->getUserID();
    }

    protected function requireVerified(): int|ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        if ($this->auth->getUserFlags(true)["unverified"]) {
            return ActionError::Unverified;
        }

        return $user;
    }

    protected function requireModerator(): int|ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        if (!$this->auth->userHasRole(UserRoleEnum::Moderator) || !$this->auth->hasUserAuthenticatedAsStaff()) {
            return ActionError::NoPermission;
        }

        return $user;
    }
}
