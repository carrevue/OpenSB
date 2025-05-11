<?php
namespace OpenSB;

global $twig, $orange;

echo $twig->render('_markdown.twig', [
	'pagetitle' => 'Guidelines',
	'file' => 'agpl.md'
]);