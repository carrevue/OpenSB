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

use PHPMailer\PHPMailer\PHPMailer;

/**
 * class Mail
 *
 * Simple PHPMailer wrapper.
 */
class Mail
{
    private PHPMailer $mailer;
    private MailTemplating $twig;
    private Localization $localization;

    public function __construct(SquareBracket $sb, array $config)
    {
        $this->mailer = new PHPMailer(true);
        $this->twig = new MailTemplating($sb); // much simpler than if we were to use Templating.
        $this->localization = $sb->getLocalizationClass();

        if ($sb->isChazizInstance() && !$sb->isFulpTubeMode()) {
            // for emails intended for squarebracket users, refer to the site
            // like this, as the emails come from fulptube.rocks.
            $name = $sb->getLocalizationClass()->translate('site1_aka_site2', 'squareBracket', 'FulpTube');
        } else {
            $name = $sb->getBrandingSettings()["name"];
        }

        $this->mailer->SMTPDebug = false;
        $this->mailer->isSMTP();
        $this->mailer->Host       = $config["host"];
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $config["username"];
        $this->mailer->Password   = $config["password"];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mailer->Port       = 465;

        $this->mailer->setFrom($config['email'], $name);
    }

    public function sendVerificationMail(string $email, string $username, string $link)
    {
        $this->mailer->addAddress($email, $username);

        $this->mailer->isHTML(true);
        $this->mailer->Subject = $this->localization->translate('email_verify_title');
        $this->mailer->Body    = $this->twig->render("unverified.twig", [
            "username" => $username,
            "link" => $link,
        ]);

        $this->mailer->send();
    }
}