function error(error) {
    play('error');
    console.error("OpenSB Charla Frontend Error: " + error);
}

let uiSounds = false;
document.addEventListener("DOMContentLoaded", () => {
    let hamburgerButton = (document.getElementById('button-hamburger')); // TEMPORARY
    let hamburgerMenu = (document.getElementById('hamburger')); // TEMPORARY

    if (hamburgerButton) {
        hamburgerButton.onclick = function() {
            if (hamburgerMenu) {
                hamburgerMenu.classList.toggle("active");
            } else {
                console.error("where the fuck is the hamburger menu");
            }
        }
    }

    // get those two buttons in the homepage
    let indexListButton = (document.getElementById('index-list-button'));
    let indexGridButton = (document.getElementById('index-grid-button'));

    if (indexListButton) {
        indexListButton.onclick = function () {
            updateConfig("charla_homepage_type", "list");
            location.reload();
        }
    }

    if (indexGridButton) {
        indexGridButton.onclick = function () {
            updateConfig("charla_homepage_type", "grid");
            location.reload();
        }
    }

    // Get all tab links
    const tabLinks = document.querySelectorAll(".tablink");

    // open the first tab automatically
    if (tabLinks.length !== 0) {
        const firstTab = tabLinks.item(0);

        if (firstTab) {
            const tabId = firstTab.getAttribute("data-tab");
            if (tabId) {
                document.getElementById(tabId).style.display = "block";
                firstTab.classList.add("active");
            }
        } else {
            error("THIS SHOULD NOT HAPPEN.");
        }
    }

    tabLinks.forEach(tabLink => {
        // check if this tab has "data-tab". if it doesn't then don't bother.
        const tabId = tabLink.getAttribute("data-tab");

        if (tabId) {
            tabLink.addEventListener("click", function () {
                // Hide all tab content
                const tabContents = document.querySelectorAll(".tabcontent");
                tabContents.forEach(tabContent => {
                    tabContent.style.display = "none";
                });

                // Remove 'active' class from all tab links
                tabLinks.forEach(link => {
                    link.classList.remove("active");
                });

                // Show the selected tab content and mark the button as active
                document.getElementById(tabId).style.display = "block";
                this.classList.add("active");
            });
        }
    });

    // Get all menu buttons
    const menuButtons = document.querySelectorAll('.menu-button');

    // Add event listeners for each menu button
    menuButtons.forEach(button => {
        const menuId = button.getAttribute('data-menu-id');
        const menu = document.getElementById(menuId);

        // check if this menu button is the one in the header.
        const isThisTheHeaderUserMenu = button.classList.contains("user-menu-button");

        // get the caret if that exists. this is primarily for the one in the header.
        const menuCaret = button.getElementsByClassName("menu-caret");

        let menuCaretOff= "biscuit-icon caret-closed menu-caret";
        let menuCaretOn = "biscuit-icon caret-open menu-caret";

        let actualCaret;
        if (menuCaret.length === 1) {
            actualCaret = menuCaret.item(0);
        } else if(menuCaret.length > 1) {
            // this shouldn't happen. if it does then i fucked this up. -chaziz 6/28/2024
            console.warn("There's a menu that has more than one caret? Huh?")
            actualCaret = menuCaret.item(0);
        }

        // initialize all menus with "none"
        menu.style.display = 'none';

        button.addEventListener('mousedown', () => {
            if (menu.style.display === 'none') {
                if (actualCaret) {
                    actualCaret.className = menuCaretOn;
                }
                if (isThisTheHeaderUserMenu) {
                    button.classList.add("selected");
                }
                menu.style.display = 'block';
            } else {
                if (actualCaret) {
                    actualCaret.className = menuCaretOff;
                }
                if (isThisTheHeaderUserMenu) {
                    button.classList.remove("selected");
                }
                menu.style.display = 'none';
            }
        });
    });

    function closeCommentReplyForm() {
        const openReplyForm = document.querySelectorAll(".reply-form");
        openReplyForm.forEach(form => {
            form.style.display = "none";
        });
    }

    function submitComment(type, id, content, replyTo = 0) {
        play('click');
        fetch("/api/biscuit/commenting", {
            method: "POST",
            body: JSON.stringify({
                type: type,
                id: id,
                comment: content,
                reply_to: replyTo
            }),
            headers: {
                "Content-type": "application/json; charset=UTF-8"
            }
        })
            .then(response => response.json())
            .then(json => {
                if (json.error) {
                    error(json.error);
                } else {
                    play('comment');
                    if (replyTo !== 0) {
                        const repliesContainer = document.getElementById(`replies-${replyTo}`);
                        if (repliesContainer) {
                            repliesContainer.insertAdjacentHTML("beforeend", json.html);
                        } else {
                            error(`replies-${replyTo} doesn't exist. Biscuit fucked up.`);
                        }
                    } else {
                        let comment_field = (document.getElementById('comment_field'));

                        if (comment_field) {
                            comment_field.insertAdjacentHTML("afterend", json.html);
                        } else {
                            error(`Comments section doesn't exist????? Charla fucked up.`);
                        }
                    }

                    closeCommentReplyForm();
                }
            });
    }

    let comment_field = (document.getElementById('comment_field'));
    if (comment_field) {
        let comment_button = document.getElementById('comment_button');
        let comment_contents = document.getElementById('comment_contents');
        comment_button.onclick = function() {
            submitComment(comment_type, comment_id, comment_contents.value);
        };
    }

    document.addEventListener("click", function(event) {
        if (event.target && event.target.classList.contains("reply-button")) {
            let commentId = event.target.getAttribute("data-comment-id");

            closeCommentReplyForm();

            let replyForm = document.getElementById(`reply-form-${commentId}`);
            if (replyForm) {
                replyForm.style.display = "block";
            }
        }

        if (event.target && event.target.classList.contains("submit-reply-button")) {
            let commentId = event.target.getAttribute("data-comment-id");
            let replyContents = document.getElementById(`reply_contents_${commentId}`);
            if (replyContents) {
                submitComment(comment_type, comment_id, replyContents.value, commentId);
            }
        }
    });

    let follow_button = (document.getElementById('follow-user'));
    if (follow_button) {
        let follow_count = (document.getElementById('follower_count'));
        follow_button.onclick = function () {
            play('click');
            fetch("/api/biscuit/user_interaction", {
                method: "POST",
                body: JSON.stringify({
                    action: "follow",
                    member: user_id,
                }),
                headers: {
                    "Content-type": "application/json; charset=UTF-8"
                }
            })
                .then((response) => response.json())
                .then((json) => {
                        if(json["error"])
                        {
                            error(json["error"])
                        }
                        else
                        {
                            if (follow_count) {
                                follow_count.textContent = json["number"];
                            }
                            follow_button.textContent = json["text"];
                            if (json["followed"]) {
                                play('subscribe');
                            }
                        }
                    }
                )
            ;

        }
    }

    // SETTINGS
    let settings_display_name_input = (document.getElementById('settings-display-name-input'));
    let settings_display_name = (document.getElementById('settings-display-name'));
    let settings_custom_color = (document.getElementById('settings-color'));

    if (settings_display_name_input && settings_display_name) {
        settings_display_name_input.addEventListener("input", function () {
            console.log(settings_display_name_input.value);
            settings_display_name.innerHTML = settings_display_name_input.value;
        });
    }

    if (settings_custom_color) {
        if (settings_display_name) {
            settings_custom_color.addEventListener("input", function () {
                settings_display_name.style.color = settings_custom_color.value;
            });
        }
        settings_custom_color.addEventListener("input", function () {
            document.documentElement.style.setProperty('--link-color', settings_custom_color.value);
        });
    }
});

function play(sound) {
    if (JSON.parse(uiSounds) === true) {
        console.warn("play() is deprecated and should be removed before OpenSB 1.3 final release! -Chaziz 12/31/2024")
    }
}