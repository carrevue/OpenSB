<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

namespace OpenSB\Pages\Debug;

global $sb, $database, $auth;

use OpenSB\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

/*
at this point in development we dont need auth.
if (!$auth->isUserLoggedIn()) {
    die("NOT LOGGED IN");
}
*/
?>
<style>
    .dropzone {
        border: 1px solid black;
        background: gray;
        padding: 0.5em 1em;
    }

    #target {
        display: flex;
        min-height: 100px;
        gap: 1em;
    }

    .upload-list {
        display: flex;
        gap: 1em;
    }

    .upload {
        border: 1px solid red;
        background: black;
        color: white;
        width: 80px;
        height: 60px;
    }
</style>
<h1>Order of uploads in collection</h1>
<p>Drag the uploads around. When you're okay with the displayed order, click "Save" at the bottom.</p>
<hr>
<div class="upload-list">
    <p class="upload" id="p1" draggable="true">Upload 1</p>
    <p class="upload" id="p2" draggable="true">Upload 2</p>
    <p class="upload" id="p3" draggable="true">Upload 3</p>
    <p class="upload" id="p4" draggable="true">Upload 4</p>
    <p class="upload" id="p5" draggable="true">Upload 5</p>
    <p class="upload" id="p6" draggable="true">Upload 6</p>
</div>
<div class="dropzone">
    <div id="target">

    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const uploadElements = document.querySelectorAll('.upload');
        const target = document.getElementById('target');

        // Add dragstart event to all upload elements
        uploadElements.forEach(element => {
            element.addEventListener('dragstart', (ev) => {
                ev.dataTransfer.setData("opensb/upload", ev.target.id);
                ev.dataTransfer.effectAllowed = "move";
            });
        });

        target.addEventListener('dragover', (ev) => {
            ev.preventDefault();
            ev.dataTransfer.dropEffect = "move";
        });

        target.addEventListener('drop', (ev) => {
            ev.preventDefault();
            const data = ev.dataTransfer.getData("opensb/upload");

            if (data) {
                const draggedElement = document.getElementById(data);
                if (draggedElement && draggedElement.classList.contains('upload')) {
                    target.appendChild(draggedElement);
                }
            }
        });

        // Optional: Reset data transfer to prevent issues with multiple drags
        uploadElements.forEach(element => {
            element.addEventListener('dragend', (ev) => {
                ev.dataTransfer.clearData();
            });
        });
    });
</script>