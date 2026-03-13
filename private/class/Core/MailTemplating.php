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

namespace Core;

use Exception;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * class MailTemplating
 * 
 * Alternate Twig wrapper for the Mail class.
 */
class MailTemplating
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
     * @var Localization The Localization class.
     */
    private Localization $localization;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     *
     * @return string
     */
    public function __construct(SquareBracket $sb)
    {
        chdir(SB_PRIVATE_PATH);

        $this->sb = $sb;

        $skinPath = 'skins/mail';

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            throw new Exception("The mail skin does not exist.");
        }

        $this->twig = new Environment($this->loader, ['debug' => $sb->isDebug(), 'cache' => false]);

        $this->localization = $this->sb->getLocalizationClass();

        if ($sb->isChazizInstance() && !$sb->isFulpTubeMode()) {
            // for emails intended for squarebracket.pw users, refer to the site
            // like this, as the emails come from fulptube.rocks.
            $name = $this->localization->translate('site1_aka_site2', 'squareBracket', 'FulpTube');
        } else {
            $name = $sb->getBrandingSettings()["name"];
        }

        $this->twig->addGlobal('website_name', $name);

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
