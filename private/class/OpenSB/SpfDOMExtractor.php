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
 * class SpfDOMExtractor
 * 
 * Handles extracting certain parts of the DOM for SPF (Structured Page 
 * Fragments).
 * 
 * @note This is currently hardcoded for Finalium.
 */
class SpfDOMExtractor
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

    public function getElementContentsFromID($id) {
        $element = $this->getElementByID($id);

        if (!$element) {
            return null;
        }

        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    public function getElementClassesFromID($id) {
        $element = $this->getElementByID($id);

        if (!$element) {
            return null;
        }

        return $element->getAttribute('class');
    }

    public function getTitle() {
        $nodes = $this->xpath->query('//title');
        return ($nodes->length > 0) ? $nodes->item(0)->textContent : null;
    }

    private function getElementByID($id) {
        $nodes = $this->xpath->query("//*[@id='$id']");
        return ($nodes->length > 0) ? $nodes->item(0) : null;
    }
}