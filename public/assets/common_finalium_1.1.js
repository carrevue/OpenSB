function error(error) {
    console.error("OpenSB Finalium Frontend Error: " + error);
}

function toggleElementDisplay(element) {
    if (element.style.display === "block") {
        element.style.display = "none";
    } else {
        element.style.display = "block";
    }
}

document.addEventListener("DOMContentLoaded", () => {
    // guide button
    var guide_button = document.getElementById("guide-toggle");

    if (guide_button) {
        guide_button.addEventListener("click", function() {
            var guide = document.getElementById("guide");
            if (guide) {
                toggleElementDisplay(guide);
            } else {
                error("where the fuck is the guide???")
            }
        });
    }

    var masthead_user_button = document.getElementById("masthead-loggedin");

    if (masthead_user_button) {
        masthead_user_button.addEventListener("click", function() {
            var masthead_user_menu = document.getElementById("masthead-below");
            if (masthead_user_menu) {
                toggleElementDisplay(masthead_user_menu);
            } else {
                error("where the fuck is the user menu???")
            }
        });
    }
});