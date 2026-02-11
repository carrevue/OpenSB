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

namespace OpenSB;

use DOMDocument;
use DOMXPath;

/*
{
  "head": "value",
  "body": {
    "player-playlist": "value",
    "header": "value",
    "early-body": "value",
    "content": "value",
    "player-unavailable": "value",
    "debug": "value",
    "appbar-content": "value",
    "alerts": "value"
  },
  "url": "/feed/trending",
  "attr": {
    "player-playlist": {
      "class": "value"
    },
    "footer-logo-link": {
      "data-sessionlink": "value"
    },
    "masthead-search": {
      "data-visibility-tracking": "value",
      "data-clicktracking": "value",
      "class": "value"
    },
    "page": {
      "class": "value"
    },
    "content": {
      "class": "value"
    },
    "player-unavailable": {
      "class": "value"
    },
    "appbar-content": {
      "class": "value"
    },
    "body": {
      "class": "value"
    },
    "logo-container": {
      "data-sessionlink": "value"
    },
    "player": {
      "class": "value"
    }
  },
  "name": "value",
  "foot": "value",
  "title": "value"
}
*/

/**
 * class StupidFuckingClassThatIllNameLater
 */
class StupidFuckingClassThatIllNameLater
{
    private DOMDocument $dom;
    private DOMXPath $xpath;

    public function __construct(string $html) {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $this->dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $this->xpath = new DOMXPath($this->dom);
    }

    public function getTitle() {
        $titleNodes = $this->xpath->query('//title');
        if ($titleNodes->length > 0) {
            return $titleNodes->item(0)->textContent;
        }
    }
}