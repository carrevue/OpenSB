<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

namespace OpenSB;

use Exception;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * class ErrorTemplating
 * 
 * Alternate Twig wrapper for certain errors, like 404 or geoblocking.
 */
class ErrorTemplating
{
    /**
     * @var SquareBracket The core SquareBracket class.
     */
    private SquareBracket $sb;

    /**
     * @var FilesystemLoader Twig's Filesystem Loader
     */
    private FilesystemLoader $loader;

    /**
     * @var Environment The Twig environment
     */
    private Environment $twig;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     *
     * @return string
     */
    public function __construct(SquareBracket $sb)
    {
        chdir(BLUFF_PRIVATE_PATH);

        $this->sb = $sb;
        //$options = $this->sb->getLocalOptions();

        $skinPath = 'skins/error';

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            throw new Exception("The error skin does not exist.");
        }

        $this->twig = new Environment($this->loader, ['debug' => $sb->isDebug(), 'cache' => false]);

        if ($sb->isDebug()) {
            $this->twig->addExtension(new DebugExtension());
        } else {
            $this->twig->addFunction(new TwigFunction('dump', function () {
                return "This function is not available outside of debug mode.";
            }));
        }

        $versionNumber = new VersionNumber;

        $this->twig->addGlobal('is_chaziz_sb', $sb->isChazizSquareBracketInstance());
        $this->twig->addGlobal('is_fulptube', $sb->isFulpTube());
        $this->twig->addGlobal('opensb_version', $versionNumber->getVersionNumber());
        $this->twig->addGlobal('website_branding', $sb->getBrandingSettings());

        $this->twig->addFunction(new TwigFunction('localize', [$this, 'localize']));
    }

    // copied from squarebrackettwigextension

    /**
     * function localize
     *
     * @param mixed $key
     * @param mixed $args
     *
     * @return mixed
     */
    public function localize($key, ...$args)
    {
        return $this->sb->getLocalizationClass()->translate($key, ...$args);
    }

    /**
     * function render
     *
     * @param mixed $template
     * @param array $data
     *
     * @return string
     */
    public function render($template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }
}
