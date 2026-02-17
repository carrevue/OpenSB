function error(error) {
    console.error("OpenSB Trinium Skin Error: " + error);
}

document.addEventListener("DOMContentLoaded", () => {
    if (!document.documentElement.className) {
        return;
    }

    const page = document.documentElement.className;

    const sidebarButton = (document.getElementById('button-sidebar'));
    const hamburgerMenu = (document.getElementById('hamburger')); // mobile sidebar
    const sidebar = (document.getElementById('sidebar')); // desktop sidebar

    // profile banner
    const profile_banner = (document.getElementById('profile-banner'));
    
    const mediaQueryList = window.matchMedia("(min-width: 1024px)");
    let isMobile = false;

    function handleMediaQueryChange(event) {
        if (event.matches) {
            isMobile = false;
            if (hamburgerMenu.classList.contains("active")) {
                hamburgerMenu.classList.toggle("active");
            }
        } else {
            isMobile = true;
        }
    }

    handleMediaQueryChange(mediaQueryList);
    mediaQueryList.addEventListener("change", handleMediaQueryChange);
    
    if (sidebarButton) {
        sidebarButton.onclick = function () {
            if (hamburgerMenu && sidebar) {
                if (isMobile) {
                    hamburgerMenu.classList.toggle("active");
                } else {
                    setOptions({
                        trinium_show_sidebar: !sidebar.classList.contains("active")
                    });
                    sidebar.classList.toggle("active");
                    if (profile_banner) {
                        profile_banner.classList.toggle("sidebar-active");
                    }
                }
            } else {
                error("at least one of the two sidebars are missing");
            }
        }
    } else {
        error("where is the sidebar button");
    }

    // get those tabs in the homepage
    function bindIndexTabToggle(id, type) {
        const btn = document.getElementById(id);
        if (!btn) return;

        btn.addEventListener('click', () => {
            setOptions({
                trinium_homepage_type: type
            });

            location.reload();
        });
    }

    bindIndexTabToggle('index-list-button', 'list');
    bindIndexTabToggle('index-wavelet-button', 'wavelet');

    // Get all tab groups
    const tabGroups = document.querySelectorAll(".tab-group");
    tabGroups.forEach(tabGroup => {
        // Get all tab links in the tab group
        const tabLinks = tabGroup.querySelectorAll(".tablink")

        // open the first tab automatically
        if (tabLinks.length > 0) {
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
                    const tabContents = tabGroup.querySelectorAll(".tabcontent");
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

        let menuCaretOff = "/assets/icons.svg#caret-closed";
        let menuCaretOn = "/assets/icons.svg#caret-open";

        let actualCaret;
        if (menuCaret.length === 1) {
            actualCaret = menuCaret.item(0);
        } else if (menuCaret.length > 1) {
            // this shouldn't happen. if it does then i fucked this up. -chaziz 6/28/2024
            console.warn("There's a menu that has more than one caret? Huh?")
            actualCaret = menuCaret.item(0);
        }

        // initialize all menus with "none"
        menu.style.display = 'none';

        button.addEventListener('mousedown', () => {
            if (menu.style.display === 'none') {
                if (actualCaret) {
                    actualCaret.querySelector('use').setAttribute('href', menuCaretOn);
                }
                if (isThisTheHeaderUserMenu) {
                    button.classList.add("selected");
                }
                menu.style.display = 'block';
            } else {
                closeMenu();
            }
        });

        document.addEventListener('mousedown', (e) => {
            if (!menu.contains(e.target) && !button.contains(e.target)) {
                closeMenu();
            }
        });

        document.querySelectorAll('[onclick]').forEach(element => {
            element.addEventListener('click', () => {
                closeMenu();
            });
        });

        function closeMenu() {
            if (actualCaret) {
                actualCaret.querySelector('use').setAttribute('href', menuCaretOff);
            }
            if (isThisTheHeaderUserMenu) {
                button.classList.remove("selected");
            }
            menu.style.display = 'none';
        }
    });

    // tooltip start
    // tooltip states
    var tooltipEl = null;
    var showTimer = null;

    // tooltip helper
    function createTooltip(text) {
        var el = document.createElement("div");
        el.className = "tooltip";
        el.appendChild(document.createTextNode(text));
        document.body.appendChild(el);

        // force reflow for transition
        el.offsetHeight;
        el.style.opacity = "1";

        return el;
    }

    function destroyTooltip() {
        if (!tooltipEl) return;
        tooltipEl.parentNode.removeChild(tooltipEl);
        tooltipEl = null;
    }

    function positionTooltip(target) {
        if (!tooltipEl) return;

        var rect = target.getBoundingClientRect();
        var tipRect = tooltipEl.getBoundingClientRect();

        var top = rect.top - tipRect.height - 8;
        var left = rect.left + (rect.width / 2) - (tipRect.width / 2);

        // flip if needed
        if (top < 8) top = rect.bottom + 8;
        if (left < 8) left = 8;

        tooltipEl.style.top = top + "px";
        tooltipEl.style.left = left + "px";
    }

    function findTooltipTarget(node) {
        while (node && node !== document) {
            if (
                node.nodeType === 1 &&
                node.classList &&
                node.classList.contains("use-tooltip")
            ) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    // tooltip events
    document.addEventListener("mouseover", function (e) {
        var el = findTooltipTarget(e.target);
        if (!el || tooltipEl) return;

        // ignore moves inside the same tooltip target
        if (el.contains(e.relatedTarget)) return;

        var title = el.getAttribute("title");
        if (!title) return;

        // suppress native tooltip
        el.setAttribute("data-tooltip-title", title);
        el.removeAttribute("title");

        showTimer = setTimeout(function () {
            tooltipEl = createTooltip(title);
            positionTooltip(el);
        }, 333);
    });

    document.addEventListener("mouseout", function (e) {
        var el = findTooltipTarget(e.target);
        if (!el) return;

        // ignore moves inside the same element
        if (el.contains(e.relatedTarget)) return;

        clearTimeout(showTimer);
        destroyTooltip();

        // restore title fallback
        var oldTitle = el.getAttribute("data-tooltip-title");
        if (oldTitle) {
            el.setAttribute("title", oldTitle);
            el.removeAttribute("data-tooltip-title");
        }
    });

    document.addEventListener("scroll", destroyTooltip, true);
    // tooltip end

    function closeCommentReplyForm() {
        const openReplyForm = document.querySelectorAll(".reply-form");
        openReplyForm.forEach(form => {
            form.style.display = "none";
        });
    }

    function closeCommentForm() {
        // kinda stupid but whatever
        let new_comment_box = document.getElementById('new-comment-button');
        let new_comment_form = document.getElementById('new-comment-form');

        new_comment_box.style.display = "block";
        new_comment_form.style.display = "none";
    }

    function submitComment(type, id, content, replyTo = 0) {
        fetch("/api/skin/comment_send", {
            method: "POST",
            body: JSON.stringify({
                type: type,
                id: id,
                comment: content.value,
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

                    if (replyTo !== 0) {
                        let reply_form_error = document.getElementById(`reply-form-error-${replyTo}`);

                        if (reply_form_error) {
                            reply_form_error.innerHTML = json.error;
                        }
                    } else {
                        let comment_form_error = (document.getElementById('comment-form-error'));

                        if (comment_form_error) {
                            comment_form_error.innerHTML = json.error;
                        }
                    }
                } else {
                    if (replyTo !== 0) {
                        let repliesContainer = document.getElementById(`replies-${replyTo}`);
                        if (repliesContainer) {
                            let reply_form_error = document.getElementById(`reply-form-error-${replyTo}`);
                            if (reply_form_error) {
                                reply_form_error.style.display = "none";
                            }

                            repliesContainer.insertAdjacentHTML("beforeend", json.html);
                        } else {
                            error(`replies-${replyTo} doesn't exist.`);
                        }
                    } else {
                        let comment_field = (document.getElementById('comment_field'));

                        if (comment_field) {
                            let comment_form_error = (document.getElementById('comment-form-error'));

                            if (comment_form_error) {
                                comment_form_error.style.display = "none";
                            }

                            comment_field.insertAdjacentHTML("afterend", json.html);
                        } else {
                            error(`Comments section doesn't exist?????`);
                        }
                    }

                    closeCommentForm();
                    closeCommentReplyForm();
                    content.value = '';
                }
            });
    }

    let comment_field = (document.getElementById('comment_field'));
    if (comment_field) {
        let new_comment_box = document.getElementById('new-comment-button');
        let new_comment_form = document.getElementById('new-comment-form');

        new_comment_box.onclick = function () {
            closeCommentReplyForm();

            if (new_comment_form) {
                new_comment_box.style.display = "none";
                new_comment_form.style.display = "block";
            } else {
                error("where's the comment form???");
            }
        };

        let comment_post_button = document.getElementById('comment_post_button');
        let comment_cancel_button = document.getElementById('comment_cancel_button');
        let comment_contents = document.getElementById('comment_contents');
        comment_post_button.onclick = function () {
            submitComment(comment_type, comment_id, comment_contents);
        };
        comment_cancel_button.onclick = function () {
            closeCommentForm();
        };
    }

    document.addEventListener("click", function (event) {
        if (event.target && event.target.classList.contains("reply-button")) {
            let commentId = event.target.getAttribute("data-comment-id");

            closeCommentForm();
            closeCommentReplyForm();

            let replyForm = document.getElementById(`reply-form-${commentId}`);
            if (replyForm) {
                replyForm.style.display = "flex";
            }
        }

        if (event.target && event.target.classList.contains("submit-reply-button")) {
            let commentId = event.target.getAttribute("data-comment-id");
            let replyContents = document.getElementById(`reply_contents_${commentId}`);
            if (replyContents) {
                submitComment(comment_type, comment_id, replyContents, commentId);
            }
        }
        if (event.target && event.target.classList.contains("submit-cancel-button")) {
            closeCommentReplyForm();
        }
    });

    let follow_button = (document.getElementById('follow-user'));
    if (follow_button) {
        let follow_count = (document.getElementById('follower_count'));
        follow_button.onclick = function () {
            fetch("/api/skin/user_interaction", {
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
                    if (json["error"]) {
                        error(json["error"])
                    }
                    else {
                        if (follow_count) {
                            follow_count.textContent = json["number"];
                        }
                        follow_button.textContent = json["text"];
                        if (json["followed"]) {
                            //play('subscribe');
                        }
                    }
                }
                )
                ;

        }
    }

    let view_report_button = (document.getElementById('report-button'));
    let view_report_dialog = (document.getElementById('report-dialog'));
    let view_report_close_button = (document.getElementById('report-close-button'));

    setUpModal(view_report_button, view_report_dialog, view_report_close_button);

    // SETTINGS
    let settings_display_name_input = (document.getElementById('settings-display-name-input'));
    let settings_display_name = (document.getElementById('settings-display-name'));
    //let settings_custom_color = (document.getElementById('settings-color'));

    if (settings_display_name_input && settings_display_name) {
        settings_display_name_input.addEventListener("input", function () {
            console.log(settings_display_name_input.value);
            settings_display_name.innerHTML = settings_display_name_input.value;
        });
    }

    /*
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
    */

    let debug_button = (document.getElementById('debug-button'));
    let debug_dialog = (document.getElementById('debug-dialog'));
    let debug_close_button = (document.getElementById('debug-close-button'));

    setUpModal(debug_button, debug_dialog, debug_close_button);
});