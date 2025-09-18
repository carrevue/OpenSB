<?php
// ported from principia-web by chaziz -4/20/2023
namespace OpenSB\Pages;

global $twig;

echo $twig->render('_markdown.twig', [
    'pagetitle' => 'Community Guidelines',
    'file' => 'guidelines.md'
]);
