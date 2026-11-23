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

class PlaylistActions extends BaseActions
{
    public function owns(array $playlist): bool
    {
        return $this->auth->isLoggedIn() && (int)$playlist["author"] === $this->auth->getUserID();
    }

    public function create(string $title, ?string $description = null): string|ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        $title = trim($title);

        if ($title === "") {
            return ActionError::PlaylistMissingTitle;
        }

        $id = Utilities::generateRandomString(11, true);

        $this->database->query(
            "INSERT INTO playlists (playlist_id, title, description, author, timestamp, updated_timestamp) VALUES (?, ?, ?, ?, ?, ?)",
            [$id, $title, $description ?: null, $user, time(), time()]
        );

        return $id;
    }

    public function delete(string $playlist): ?ActionError
    {
        $playlist = $this->load($playlist);

        if ($playlist instanceof ActionError) {
            return $playlist;
        }

        $this->database->query("DELETE FROM playlist_items WHERE playlist = ?", [$playlist["id"]]);
        $this->database->query("DELETE FROM playlists WHERE id = ?", [$playlist["id"]]);

        return null;
    }

    public function add(string $playlist, string $upload): ?ActionError
    {
        [$playlist, $upload, $uploads] = $this->resolve($playlist, $upload);

        if ($playlist instanceof ActionError) {
            return $playlist;
        }

        if (in_array($upload, $uploads)) {
            return ActionError::PlaylistAlreadyAdded;
        }

        $this->database->query(
            "INSERT INTO playlist_items (playlist, upload, position, timestamp) VALUES (?, ?, ?, ?)",
            [$playlist["id"], $upload, count($uploads), time()]
        );

        $uploads[] = $upload;
        $this->save($playlist["id"], $uploads);

        return null;
    }

    public function remove(string $playlist, string $upload): ?ActionError
    {
        [$playlist, $upload, $uploads] = $this->resolve($playlist, $upload);

        if ($playlist instanceof ActionError) {
            return $playlist;
        }

        if (!in_array($upload, $uploads)) {
            return ActionError::PlaylistNotAdded;
        }

        $this->database->query("DELETE FROM playlist_items WHERE playlist = ? AND upload = ?", [$playlist["id"], $upload]);

        $this->save($playlist["id"], array_diff($uploads, [$upload]));

        return null;
    }

    public function move(string $playlist, string $upload, int $position): ?ActionError
    {
        [$playlist, $upload, $uploads] = $this->resolve($playlist, $upload);

        if ($playlist instanceof ActionError) {
            return $playlist;
        }

        $from = array_search($upload, $uploads);

        if ($from === false) {
            return ActionError::PlaylistNotAdded;
        }

        $position = max(0, min(count($uploads) - 1, $position));

        array_splice($uploads, $from, 1);
        array_splice($uploads, $position, 0, [$upload]);

        $this->save($playlist["id"], $uploads);

        return null;
    }

    public function position(string $playlist, string $upload): int|ActionError
    {
        [$playlist, $upload, $uploads] = $this->resolve($playlist, $upload);

        if ($playlist instanceof ActionError) {
            return $playlist;
        }

        $position = array_search($upload, $uploads);

        if ($position === false) {
            return ActionError::PlaylistNotAdded;
        }

        return $position;
    }

    public function fix(string $playlist): ?ActionError
    {
        $playlist = $this->load($playlist);

        if ($playlist instanceof ActionError) {
            return $playlist;
        }

        $this->database->query(
            "DELETE i FROM playlist_items i LEFT JOIN uploads v ON v.id = i.upload WHERE i.playlist = ? AND v.id IS NULL",
            [$playlist["id"]]
        );

        $this->save($playlist["id"], $this->uploads($playlist["id"]));

        return null;
    }

    private function load(string $playlist): array|ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        $data = $this->database->fetch("SELECT id, author FROM playlists WHERE playlist_id = ?", [$playlist]);

        if (!$data) {
            return ActionError::InvalidPlaylist;
        }

        if (!$this->owns($data)) {
            return ActionError::NoPermission;
        }

        return $data;
    }

    private function resolve(string $playlist, string $upload): array
    {
        $playlist = $this->load($playlist);

        if ($playlist instanceof ActionError) {
            return [$playlist, null, null];
        }

        $id = Utilities::uploadStringIDToUploadNumericID($this->database, trim($upload));

        if (!$id) {
            return [ActionError::InvalidUpload, null, null];
        }

        return [$playlist, (int)$id, $this->uploads($playlist["id"])];
    }

    private function uploads(int $playlist): array
    {
        return array_map('intval', array_column($this->database->fetchArray($this->database->query(
            "SELECT upload FROM playlist_items WHERE playlist = ? ORDER BY position, id",
            [$playlist]
        )), 'upload'));
    }

    private function save(int $playlist, array $uploads): void
    {
        foreach (array_values($uploads) as $position => $upload) {
            $this->database->query(
                "UPDATE playlist_items SET position = ? WHERE playlist = ? AND upload = ?",
                [$position, $playlist, $upload]
            );
        }

        $this->database->query(
            "UPDATE playlists SET upload_count = ?, updated_timestamp = ? WHERE id = ?",
            [count($uploads), time(), $playlist]
        );
    }
}
