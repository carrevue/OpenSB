<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

namespace Pages\Debug;

global $sb, $auth, $database;

// /my_messages points to this page so don't check for this yet
/*
if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}
*/

if (!$auth->isUserLoggedIn()) {
    die("NOT LOGGED IN");
}

use OpenSB\UserData;

// think of this as data from the database.
$database_data = [
    "1" => [
        "id" => 1,
        "reply_to_id" => null,
        "title" => "OpenSB Private Message Test",
        "contents" => "This is a private message. You should see a small portion of the contents of this private 
        message.",
        "author" => -1000,
        "recipient" => 1,
        "date" => time() - 1200,
    ],
    "2" => [
        "id" => 2,
        "reply_to_id" => null,
        "title" => "OpenSB Private Message Test #2",
        "contents" => "This is another private message. Something something lorem ipsum. You should see a small portion 
        of the contents of this private message. Foo!",
        "author" => -1000,
        "recipient" => 1,
        "date" => time() - 700,
    ],
];

// then turn that shit into more usable data?

$messageArray = [];

foreach ($database_data as $message) {
    $userData = new UserData($database, $message["author"]);

    $messageArray[] =
        [
            "id" => $message["id"],
            "title" => $message["title"],
            "contents" => $message["contents"],
            "published" => $message["date"],
            "author" => [
                "id" => $message["author"],
                "info" => $userData->getUserArray(),
            ],
        ];
}
?>

<body>
    <h1>inbox</h1>
    <a href="/">go back home</a>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="80%" valign="top">
                <!--
                <table width="100%" border="1" cellpadding="5" cellspacing="0">
                    <tr>
                        <td>
                            <span>Write</span>
                            <span>Delete</span>
                        </td>
                        <td align="right">
                            <span>amount</span>
                        </td>
                    </tr>
                </table>-->

                <table width="100%" border="1" cellpadding="1" cellspacing="0">
                    <?php
                    // holy subrocks
                    foreach ($messageArray as $message) {
                    ?>
                        <tr>
                            <td width="24px"><input type="checkbox"></td>
                            <td width="240px"><?php echo $message["author"]["info"]["username"] ?></td>
                            <td>
                                <?php echo $message["title"] ?>
                            </td>
                            <td width="150px" align="right"><?php echo date('Y-m-d H:i:s', $message["published"]) ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>