<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

  This file is based on code from Principia-web.
  Copyright (C) 2020-2026 ROllerozxa

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

namespace Pages\Forum;

use Core\Utilities;

include_once('_include.php');

global $sb, $database, $twig;

needsLogin();

$action = $_POST['action'] ?? null;
$fid = $_GET['id'] ?? null;

$forum = $database->fetch("SELECT * FROM z_forums WHERE id = ? AND ? >= minread", [$fid, $userdata['powerlevel']]);

if (!$forum)
	error('404');
if ($forum['minthread'] > $userdata['powerlevel'])
	error('403', "You have no permissions to create threads in this forum!");

$error = '';

$title = $_POST['title'] ?? '';
$message = $_POST['message'] ?? '';

if ($action == 'Submit') {
	$lastpost = $database->fetch("SELECT id, user, date FROM z_posts WHERE user = ? ORDER BY id DESC LIMIT 1", [$userdata['id']]);

	if (strlen(trim($title)) < 15)
		$error = "You need to enter a longer title.";
	if (strlen(trim($message)) == 0)
		$error = "You need to enter a message to your thread.";
	if ($lastpost['date'] > time() - (10*60) && $action == 'Submit' /*&& !IS_ROOT*/)
		$error = "Don't post threads so fast, wait a little longer.";

	if (!$error) {
		$tid = newThread([
			'forum' => $fid,
			'title' => $title,
			'message' => $message,
			'u_id' => $userdata['id'],
			'u_name' => $userdata['name']
		]);

		Utilities::redirect("thread?id=$tid");
	}
}

$topbot = [
	'breadcrumb' => ["forum?id=$fid" => $forum['title']],
	'title' => "New thread"
];

if ($action == 'Preview') {
	foreach ($userdata as $field => $val)
		$post['u'.$field] = $val;

	$post['date'] = time();
	$post['text'] = $message;
	$post['headerbar'] = 'Post preview';

	$topbot['title'] .= ' (Preview)';
}

echo $twig->render('forum/newthread.twig', [
	'post' => $post ?? null,
	'threadtitle' => $title,
	'message' => $message,
	'topbot' => $topbot,
	'action' => $action,
	'fid' => $fid,
	'error' => $error
]);
