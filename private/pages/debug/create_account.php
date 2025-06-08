<?php

namespace OpenSB;

global $orange;

if (!$orange->isDebug()) {
    http_response_code(403);
    die();
}
?>
<h1>Create account</h1>
<form action="/debug/create_account" method="post">
    <fieldset>
        <legend>Basic Information</legend>
        <label for="name">Username:</label>
        <input type="text" id="name" name="name" maxlength="128" required><br><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" maxlength="128" required><br><br>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" maxlength="128" required><br><br>

        <label for="title">Display Name:</label>
        <input type="text" id="title" name="title"><br><br>

        <label for="birthdate">Birthdate:</label>
        <input type="date" id="birthdate" name="birthdate"><br><br>
    </fieldset>
    
    <fieldset>
        <legend>Profile Settings</legend>
        <label for="about">Description</label><br>
        <textarea id="about" name="about" rows="4" cols="50"></textarea><br><br>

        <label for="customcolor">Profile Color:</label>
        <input type="color" id="customcolor" name="customcolor" value="#523bb8"><br><br>

        <label>Sensitive rating option:</label>
        <select id="comfortable_rating" name="comfortable_rating">
            <option value="general">General</option>
            <option value="mature">Mature</option>
        </select>
        <br>
        <smalL><span>Setting this option to Mature on Chaziz mode will have it fallback to General.</span></smalL>
        <br><br>

        <label for="blacklisted_tags">Blacklisted Tags (comma separated):</label><br>
        <textarea id="blacklisted_tags" name="blacklisted_tags" rows="4" cols="50"></textarea><br><br>
    </fieldset>

    <input type="submit" value="Register">
</form>
