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

namespace SquareBracket;

use Exception;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class ErrorTemplating
{
    private SquareBracket $orange;
    private FilesystemLoader $loader;
    private Environment $twig;

    /**
     * @throws Exception
     */
    public function __construct(SquareBracket $orange)
    {
        chdir(SB_PRIVATE_PATH);

        $this->orange = $orange;
        //$options = $this->orange->getLocalOptions();

        $skinPath = 'skins/error';

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            throw new Exception("The error skin does not exist.");
        }

        $this->twig = new Environment($this->loader, ['debug' => $orange->isDebug(), 'cache' => false]);

        if ($orange->isDebug()) {
            $this->twig->addExtension(new DebugExtension());
        } else {
            $this->twig->addFunction(new TwigFunction('dump', function () {
                return "This function is not available outside of debug mode.";
            }));
        }

        $versionNumber = new VersionNumber;

        $this->twig->addGlobal('is_chaziz_sb', $orange->isChazizSquareBracketInstance());
        $this->twig->addGlobal('is_fulptube', $orange->isFulpTube());
        $this->twig->addGlobal('opensb_version', $versionNumber->getVersionNumber());
        $this->twig->addGlobal('website_branding', $orange->getBrandingSettings());

        $this->twig->addFunction(new TwigFunction('localize', [$this, 'localize']));
    }

    // copied from squarebrackettwigextension
    public function localize($key, ...$args)
    {
        return $this->orange->getLocalizationClass()->translate($key, ...$args);
    }

    /**
     *
     * @param $template
     * @param array $data
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     */
    public function render($template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }
}
