<?php

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
    private FilesystemLoader $loader;
    private Environment $twig;

    /**
     * @throws Exception
     */
    public function __construct(SquareBracket $orange)
    {
        chdir(SB_PRIVATE_PATH);

        $options = $orange->getLocalOptions();

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
            $this->twig->addFunction(new TwigFunction('dump', function() {
                return "This function is not available outside of debug mode.";
            }));
        }

        $versionNumber = new VersionNumber;

        $this->twig->addGlobal('is_chaziz_sb', $orange->isChazizSquareBracketInstance());
        $this->twig->addGlobal('is_fulptube', $orange->isFulpTube());
        $this->twig->addGlobal('opensb_version', $versionNumber->getVersionNumber());
        $this->twig->addGlobal('website_branding', $orange->getBrandingSettings());
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