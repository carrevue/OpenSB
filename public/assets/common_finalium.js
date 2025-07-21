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

    // user button
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

    // logged out error?
    const actionUnlogged = document.getElementById('action_unlogged');
    if (actionUnlogged) {
        actionUnlogged.addEventListener('click', function() {
            alert('you must be logged in.');
        });
    }

    // comments
    // NOTE: this references a bunch of leftovers from the bootstrap frontend.
    const commentContents = document.getElementById('commentContents');
    const postButton = document.getElementById('post');
    const postUserButton = document.getElementById('post-user');
    const commentPostingSpinner = document.getElementById('commentPostingSpinner');
    const commentSection = document.getElementById('comment');

    if (commentContents) {
        let contents = commentContents.value.trim();
        if ((contents === null || contents === "") && !postButton.classList.contains('disabled')) {
            postButton.classList.add('disabled');
        }

        // slightly fucking stupid but this was in the jquery version
        commentContents.addEventListener('keydown', function(e) {
            let contents;
            if (e.key === "Backspace") {
                contents = this.value.trim().slice(0, -1);
            } else if (e.key.length === 1) {
                contents = this.value.trim() + e.key;
            } else {
                contents = this.value.trim();
            }

            if (postButton) {
                if (contents === "") {
                    postButton.classList.add('disabled');
                } else {
                    postButton.classList.remove('disabled');
                }
            }
        });
    }

    // post comment (upload)
    if (postButton) {
        postButton.addEventListener('click', function() {
            if (commentPostingSpinner) {
                commentPostingSpinner.classList.remove('d-none');
            }

            const commentText = commentContents ? commentContents.value.trim() : '';
            if (!commentText) {
                return alert('you must put something to comment!');
            }

            fetch("/api/legacy/comment", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `comment=${encodeURIComponent(commentText)}&vidid=${submission_id}&really=ofcourse&type=video`
            })
            .then(response => response.text())
            .then(data => {
                console.log("Commented " + commentText);
                if (commentSection) {
                    commentSection.insertAdjacentHTML('afterbegin', data);
                }
                if (commentContents) {
                    commentContents.value = '';
                }
                postButton.classList.add('disabled');
                if (commentPostingSpinner) {
                    commentPostingSpinner.classList.add('d-none');
                }
            })
        });
    }

    // post comment (upload)
    if (postUserButton) {
        postUserButton.addEventListener('click', function() {
            if (commentPostingSpinner) {
                commentPostingSpinner.classList.remove('d-none');
            }

            const commentText = commentContents ? commentContents.value.trim() : '';
            fetch("/api/legacy/comment", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `comment=${encodeURIComponent(commentText)}&uid=${user_id}&really=ofcourse&type=profile`
            })
            .then(response => response.text())
            .then(data => {
                console.log("Commented " + commentText);
                if (commentSection) {
                    commentSection.insertAdjacentHTML('afterbegin', data);
                }
                if (commentContents) {
                    commentContents.value = '';
                }
                if (postButton) {
                    postButton.classList.add('disabled');
                }
                if (commentPostingSpinner) {
                    commentPostingSpinner.classList.add('d-none');
                }
            })
        });
    }

    // subscribe button (main)
    const subscribeBtn = document.getElementById('subscribe');
    if (subscribeBtn) {
        subscribeBtn.addEventListener('click', function() {
            fetch("/api/legacy/subscribe", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `subscription=${user_id}`
            })
            .then(response => response.text())
            .then(data => {
                if (data === subscribe_string) {
                    subscribeBtn.textContent = subscribe_string;
                    subscribeBtn.className = "button button-primary";
                    console.log("Unsubscribed " + user_id);
                } else if (data === unsubscribe_string) {
                    subscribeBtn.textContent = unsubscribe_string;
                    subscribeBtn.className = "button button-secondary";
                    console.log("Subscribed " + user_id);
                } else {
                    alert('unexpected output! report to https://github.com/bluffingo/OpenSB/issues');
                }
            })
        });
    }

    // subscribe button (watch page variant?)
    const subscribeWatchBtn = document.getElementById('subscribe-watch');
    if (subscribeWatchBtn) {
        subscribeWatchBtn.addEventListener('click', function() {
            fetch("/api/legacy/subscribe", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `subscription=${user_id}`
            })
            .then(response => response.text())
            .then(data => {
                if (data === subscribe_string) {
                    subscribeWatchBtn.textContent = subscribe_string;
                    subscribeWatchBtn.className = "button button-primary button-small";
                    console.log("Unsubscribed " + user_id);
                } else if (data === unsubscribe_string) {
                    subscribeWatchBtn.textContent = unsubscribe_string;
                    subscribeWatchBtn.className = "button button-secondary button-small";
                    console.log("Subscribed " + user_id);
                } else {
                    alert('unexpected output! report to https://github.com/bluffingo/OpenSB/issues');
                }
            })
        });
    }

    // like/dislike 
    // NOTE: this is based on the original shitty jquery implementation from 2021. it is fucked up (you cant unrate shit).
    // i'll fix this later. -chaziz 2025/07/20
    const likeButton = document.getElementById('like');
    const dislikeButton = document.getElementById('dislike');

    let likeCount = document.getElementById('like-count');
    let dislikeCount = document.getElementById('dislike-count');

    if (likeButton) {
        likeButton.addEventListener('click', function() {
            if (!this.classList.contains('button-toggled')) {
                fetch("/api/legacy/rate", {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `rating=5&vidid=${submission_id}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data == 1) {
                        this.className = "button button-like button-secondary-invis button-toggled";
                        likeCount.textContent = parseInt(likeCount.textContent) + 1;
                        dislikeCount.textContent = parseInt(dislikeCount.textContent) - 1;
                        document.getElementById('dislike').className = "button button-dislike button-secondary-invis";
                    } else if (data == 0) {
                        this.click();
                    } else {
                        alert('unexpected output! report to https://github.com/bluffingo/OpenSB/issues');
                    }
                })
            }
        });
    }

    if (dislikeButton) {
        dislikeButton.addEventListener('click', function() {
            if (!this.classList.contains('button-toggled')) {
                fetch("/api/legacy/rate", {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `rating=1&vidid=${submission_id}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data == 1) {
                        this.className = "button button-dislike button-secondary-invis button-toggled";
                        likeCount.textContent = parseInt(likeCount.textContent) - 1;
                        dislikeCount.textContent = parseInt(dislikeCount.textContent) + 1;
                        document.getElementById('like').className = "button button-like button-secondary-invis";
                    } else if (data == 0) {
                        this.click();
                    } else {
                        lert('unexpected output! report to https://github.com/bluffingo/OpenSB/issues');
                    }
                })
            }
        });
    }

    // debug button
    const debugButton = document.getElementById('debug-button');
    const debugModal = document.getElementById('debugModal');
    
    if (debugModal) {
        debugButton.addEventListener('click', function() {
            toggleElementDisplay(debugModal);
        });
    }
});

// some weird fucking shit that was defined like this
let index = 0;

function showReplies(id) {
    fetch("/api/legacy/get_replies", {
        method: "POST",
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `comment_id=${id}`
    })
    .then(response => response.text())
    .then(data => {
        const commentElement = document.getElementById(id);
        if (commentElement) {
            commentElement.insertAdjacentHTML('beforeend', data);
        }
    })
}

function showMoreVideos() {
    const fromUserVideoList = document.getElementById('fromUserVideoList');
    if (!fromUserVideoList) return;

    if (!fromUserVideoList.classList.contains('card-body')) {
        fetch("/api/legacy/ajax_watch", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `from=${index}&limit=10`
        })
        .then(response => response.text())
        .then(data => {
            index += 10;
            fromUserVideoList.insertAdjacentHTML('beforeend', data);
            fromUserVideoList.classList.remove("collapsed");
            
            const fromUserElement = document.getElementById('fromUser');
            if (fromUserElement) {
                fromUserElement.remove();
            }
        })
    } else {
        fromUserVideoList.innerHTML = '';
        fromUserVideoList.classList.add("collapsed");
    }
}

// there should be code for replies, but those broke on finalium 1 when i redid the css for it