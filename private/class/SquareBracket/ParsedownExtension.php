<?php

namespace SquareBracket;

use Parsedown;

class ParsedownExtension extends Parsedown
{

    // We don't need headers within comments
    protected function blockHeader($Line): void {}

    protected function blockSetextHeader($Line, array|null $Block = []): void {}
}