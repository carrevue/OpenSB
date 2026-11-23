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

if (!$auth->isLoggedIn()) {
    die("NOT LOGGED IN");
}

use Actions\ActionError;
use Actions\MessageActions;
use Core\Utilities;
use Data\Message\MessageData;

$actions = new MessageActions($sb);
$messages = new MessageData($database);

$fail = fn(ActionError $error) => die($sb->getLocalizationClass()->translate($error->value));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        foreach ((array)($_POST['ids'] ?? []) as $id) {
            $error = $actions->delete((int)$id);

            if ($error) {
                $fail($error);
            }
        }

        Utilities::redirect('/debug/inbox' . (isset($_GET['view']) ? '?view=sent' : ''));
    }

    $recipient = Utilities::usernameToUserID($database, trim($_POST['to'] ?? ''));

    if (!$recipient) {
        $fail(ActionError::InvalidUser);
    }

    $id = $actions->send((int)$recipient, $_POST['title'] ?? '', $_POST['contents'] ?? '', (int)($_POST['reply_to'] ?? 0) ?: null);

    if ($id instanceof ActionError) {
        $fail($id);
    }

    Utilities::redirect('/debug/inbox?view=sent');
}

$sent = ($_GET['view'] ?? '') === 'sent';
$messageArray = $sent ? $messages->getSent($auth->getUserID()) : $messages->getInbox($auth->getUserID());

$open = null;

if (isset($_GET['id'])) {
    $open = $actions->open((int)$_GET['id']);

    if ($open instanceof ActionError) {
        $fail($open);
    }
}

$write = isset($_GET['write']) || $open;
$other = $open ? ($open["author"]["id"] == $auth->getUserID() ? $open["recipient"] : $open["author"]) : null;
?>

<body>
    <h1>inbox</h1>
    <a href="/">go back home</a>
    <p>Logged in as <?php echo htmlspecialchars($auth->getUserData()["name"]) ?></p>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="80%" valign="top">
                <?php if ($open) { ?>
                    <table width="100%" border="1" cellpadding="5" cellspacing="0">
                        <tr>
                            <td>
                                <b><?php echo htmlspecialchars($open["title"]) ?: "(no subject)" ?></b><br>
                                from <?php echo htmlspecialchars($open["author"]["info"]["username"]) ?>
                                to <?php echo htmlspecialchars($open["recipient"]["info"]["username"]) ?>
                                <?php if ($open["reply_to"]) { ?>
                                    in reply to <a href="/debug/inbox?id=<?php echo (int)$open["reply_to"] ?>">#<?php echo (int)$open["reply_to"] ?></a>
                                <?php } ?>
                            </td>
                            <td align="right" valign="top"><?php echo date('Y-m-d H:i:s', $open["published"]) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"><?php echo nl2br(htmlspecialchars($open["contents"])) ?></td>
                        </tr>
                    </table>
                    <br>
                <?php } ?>

                <?php if ($write) { ?>
                    <form method="post" action="/debug/inbox">
                        <input type="hidden" name="reply_to" value="<?php echo $open ? (int)$open["id"] : "" ?>">
                        <table width="100%" border="1" cellpadding="5" cellspacing="0">
                            <tr>
                                <td width="80px">To</td>
                                <td><input type="text" name="to" value="<?php echo $other ? htmlspecialchars($other["info"]["username"]) : htmlspecialchars($_GET["to"] ?? "") ?>"></td>
                            </tr>
                            <tr>
                                <td>Subject</td>
                                <td><input type="text" name="title" maxlength="128" placeholder="<?php echo $open ? "Re: " . htmlspecialchars($open["title"]) : "" ?>"></td>
                            </tr>
                            <tr>
                                <td colspan="2"><textarea name="contents" rows="6" cols="80"></textarea></td>
                            </tr>
                            <tr>
                                <td colspan="2"><input type="submit" value="<?php echo $open ? "Reply" : "Send" ?>"></td>
                            </tr>
                        </table>
                    </form>
                    <br>
                <?php } ?>

                <form method="post" action="/debug/inbox<?php echo $sent ? "?view=sent" : "" ?>">
                    <table width="100%" border="1" cellpadding="5" cellspacing="0">
                        <tr>
                            <td>
                                <span><a href="/debug/inbox?write">Write</a></span>
                                <span><input type="submit" name="delete" value="Delete"></span>
                                <span><?php echo $sent ? '<a href="/debug/inbox">Inbox</a>' : '<a href="/debug/inbox?view=sent">Sent</a>' ?></span>
                            </td>
                            <td align="right">
                                <span><?php echo count($messageArray) ?></span>
                            </td>
                        </tr>
                    </table>

                    <table width="100%" border="1" cellpadding="1" cellspacing="0">
                        <?php
                        // holy subrocks
                        foreach ($messageArray as $message) {
                            $unread = !$sent && !$message["read"];
                        ?>
                            <tr>
                                <td width="24px"><input type="checkbox" name="ids[]" value="<?php echo (int)$message["id"] ?>"></td>
                                <td width="240px"><?php echo htmlspecialchars($message[$sent ? "recipient" : "author"]["info"]["username"]) ?></td>
                                <td>
                                    <a href="/debug/inbox?id=<?php echo (int)$message["id"] ?>"><?php echo $unread ? "<b>" : "" ?><?php echo htmlspecialchars($message["title"]) ?: "(no subject)" ?><?php echo $unread ? "</b>" : "" ?></a>
                                </td>
                                <td width="150px" align="right"><?php echo date('Y-m-d H:i:s', $message["published"]) ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </table>
                </form>
            </td>
        </tr>
    </table>
</body>

</html>