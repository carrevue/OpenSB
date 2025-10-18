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

namespace OpenSB\Pages\Debug;

use OpenSB\UserData;
use OpenSB\Utilities;

global $database;

if (isset($_GET["username"])) {
    $id = Utilities::usernameToUserID($database, $_GET["username"]);
} elseif (isset($_GET["id"])) {
    $id = $_GET["id"];
} else {
    $id = 0;
}

if ($id) {
    $userdata = new UserData($database, $id);

    $array = $userdata->getUserArray();

?>
    <table border=1>
        <tr>
            <?php
            foreach ($array as $key => $value) {
            ?>
                <td><?php echo $key ?></td>
            <?php
            }
            ?>
        </tr>
        <tr>
            <?php
            foreach ($array as $key => $value) {
                if ($key === 'color') {
                    $style = "style='background-color: {$value};')";
                } else {
                    $style = '';
                }
            ?>
                <td <?php echo $style ?>>
                    <?php
                    if (is_array($value)) {
                        echo '<ul>';
                        foreach ($value as $subKey => $subValue) {
                            echo '<li><strong>' . $subKey . ':</strong> ' . (is_bool($subValue) ? ($subValue ? 'true' : 'false') : $subValue) . '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo is_bool($value) ? ($value ? 'true' : 'false') : $value;
                    }
                    ?>
                </td>
            <?php
            }
            ?>
        </tr>
    </table>
<?php } else { ?>

    <form action="/debug/user_data" method="get">
        <div>
            <label for="username">Enter the username: </label>
            <input type="text" name="username" id="username" />
        </div>
        <div>
            <label for="id">Enter the ID: </label>
            <input type="text" name="id" id="id" />
        </div>
        <div>
            <input type="submit" value="Go" />
        </div>
    </form>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            var username = document.getElementById('username').value;
            var id = document.getElementById('id').value;

            if ((!username && !id) || (username && id)) {
                e.preventDefault();
                alert('do not input both');
            }
        });
    </script>
    </form>
<?php } ?>