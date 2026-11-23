<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2026 Chaziz

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
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

/**
 * class DashboardTemplating
 * 
 * Alternate Twig wrapper for the dashboard.
 */
class DashboardTemplating
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
        chdir(SB_PRIVATE_PATH);

        $this->sb = $sb;
        //$options = $this->sb->getLocalOptions();

        $skinPath = 'skins/dashboard';

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            throw new Exception("The dashboard skin does not exist.");
        }

        $doCache = !$sb->isTemplateCachingEnabled() ? false : 'skins/cache/';

        $this->loader->addPath('skins/common/');
        $this->twig = new Environment($this->loader, ['debug' => $sb->isDebug(), 'cache' => $doCache]);

        if ($sb->isDebug()) {
            $this->twig->addExtension(new DebugExtension());
        } else {
            $this->twig->addFunction(new TwigFunction('dump', function () {
                return "This function is not available outside of debug mode.";
            }));
        }

        $versionNumber = new VersionNumber;

        $this->twig->addGlobal('is_chaziz_sb', $sb->isChazizInstance());
        $this->twig->addGlobal('is_fulptube', $sb->isFulpTubeMode());
        $this->twig->addGlobal('opensb_version', $versionNumber->getVersionArray());
        $this->twig->addGlobal('website_branding', $sb->getBrandingSettings());

        $this->twig->addFunction(new TwigFunction('localize', [$this, 'localize']));
        $this->twig->addFunction(new TwigFunction('get_css_file_timestamp', [$sb, 'getCurrentSkinCssTimestamp']));
        $this->twig->addFunction(new TwigFunction('icon', function () {
            return "icon";
        }));
        $this->twig->addFunction(new TwigFunction('thumbnail', function () {
            return "thumbnail";
        }));
        $this->twig->addFunction(new TwigFunction('pagination', function () {
            return "pagination";
        }));
        $this->twig->addFunction(new TwigFunction('upload_view', function () {
            return "upload_view";
        }));
        $this->twig->addFunction(new TwigFunction('profile_picture', function () {
            return "profile_picture";
        }));
        $this->twig->addFunction(new TwigFunction('profile_banner', function () {
            return "profile_banner";
        }));
        $this->twig->addFunction(new TwigFunction('user_link', function () {
            return "user_link";
        }));

        $this->twig->addFilter(new TwigFilter('format_date', function () {
            return "format_date";
        }));
        $this->twig->addFilter(new TwigFilter('relative_time', function () {
            return "relative_time";
        }));
        $this->twig->addFilter(new TwigFilter('markdown_user_written', function () {
            return "markdown_user_written";
        }));

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
