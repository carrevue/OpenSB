function error(error) {
    play('error');
    console.error("OpenSB Biscuit Frontend Error: " + error);
}

let uiSounds = false;
document.addEventListener("DOMContentLoaded", () => {
    // Get all tab links
    const tabLinks = document.querySelectorAll(".tablink");

    // open the first tab automatically
    if (tabLinks.length !== 0) {
        const firstTab = tabLinks.item(0);

        if (firstTab) {
            const tabId = firstTab.getAttribute("data-tab");
            if (tabId) {
                const firstTabId = document.getElementById(tabId);
                if (firstTabId) {
                    firstTabId.style.display = "block";
                    firstTab.classList.add("active");
                } else {
                    error(`Tab ${tabId} is missing contents.`)
                }
            } //else {
            //    error("THIS SHOULD NOT HAPPEN. (tab code fail 1)");
            //}
            // actually that's fine. some pages use javascript-less tabs (browse and admin for example)
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
                const selectedTabId = document.getElementById(tabId);

                if (selectedTabId) {
                    selectedTabId.style.display = "block";
                    this.classList.add("active");
                } else {
                    error(`Tab ${tabId} is missing contents.`)
                }
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

        const menuCaretOff= "biscuit-icon caret-closed menu-caret";
        const menuCaretOn = "biscuit-icon caret-open menu-caret";

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
                        const commentsSection = document.getElementById('new-comments-here');
                        if (commentsSection) {
                            commentsSection.insertAdjacentHTML("afterbegin", json.html);
                        } else {
                            error(`Comments section doesn't exist????? Biscuit fucked up.`);
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

    let debug_button = (document.getElementById('debug-button'));
    let debug_close_button = (document.getElementById('debug-close-button'));
    let debug_dialog = (document.getElementById('debug-dialog'));

    if (debug_button) {
        debug_button.addEventListener("click", () => {
            debug_dialog.showModal();
        });

        debug_close_button.addEventListener("click", () => {
            debug_dialog.close();
        });
    }
});

function play(sound) {
    if (JSON.parse(uiSounds) === true) {
        console.warn("play() is deprecated and should be removed before OpenSB 1.3 final release! -Chaziz 12/31/2024")
    }
}