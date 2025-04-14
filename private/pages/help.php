<?php

namespace OpenSB;

global $twig;

echo $twig->render('_markdown.twig', [
    'pagetitle' => 'Help',
    'file' => 'help.md'
]);