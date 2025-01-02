<?php

namespace SquareBracket;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension;
use Twig\Extra\String\StringExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class ErrorTemplating
{
    private FilesystemLoader $loader;
    private Environment $twig;

    /**
     * @throws LoaderError
     */
    public function __construct(SquareBracket $orange)
    {
        global $isDebug, $branding, $isChazizSB;
        chdir(SB_PRIVATE_PATH);

        $options = $orange->getLocalOptions();

        $skinPath = 'skins/error';

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            trigger_error("Error skin does not exist.", E_USER_ERROR);
        }

        $this->twig = new Environment($this->loader, ['debug' => $isDebug, 'cache' => false]);

        if ($isDebug) {
            $this->twig->addExtension(new DebugExtension());
        } else {
            $this->twig->addFunction(new TwigFunction('dump', function() {
                return "This function is not available outside of debug mode.";
            }));
        }

        // override squarebracket branding with fulptube branding if accessed via fulptube.rocks.
        // this fulptube branding is meant to look like the squarebracket branding on purpose, since
        // both squarebracket.pw and fulptube.rocks lead to the same site.
        if (Utilities::isFulpTube()) {
            $isFulpTube = true;
            $branding = [
                "name" => "FulpTube",
                "assets_location" => "/assets/sb_branding/fulp",
            ];
        } else {
            $isFulpTube = false;
        }

        $versionNumber = new VersionNumber;

        $this->twig->addGlobal('is_chaziz_sb', $isChazizSB);
        $this->twig->addGlobal('is_fulptube', $isFulpTube);
        $this->twig->addGlobal('opensb_version', $versionNumber->getVersionNumber());
        $this->twig->addGlobal('website_branding', $branding);
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