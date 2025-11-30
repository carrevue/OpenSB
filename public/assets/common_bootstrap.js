$(document).ready(function () {
    $(window).click(function () {
        $("#mainMenu").removeClass("show");
        $("#themeSelection").removeClass("show");
    });
    $("#action_unlogged").click(function () {
        alert('You must be logged in to do that.');
    });
    contents = $.trim($("#commentContents").val());
    if (contents === null || contents == "" && $("#post").attr("class") != "btn btn-primary disabled") {
        $("#post").addClass("disabled");
    }
    $("#commentContents").keydown(function (e) {
        if (e.key == "Backspace") {
            contents = $.trim($("#commentContents").val()).slice(0, -1);
        } else {
            contents = $.trim($("#commentContents").val()) + e.key;
        }
        if (contents == "") {
            $("#post").addClass("disabled");
        } else if (contents != "") {
            $("#post").removeClass("disabled")
        }
    });
    $("#post").click(function () {
        $("#commentPostingSpinner").removeClass('d-none');
        $.post("/api/legacy/comment",
            {
                comment: $.trim($('#commentContents').val()),
                vidid: video_id,
                really: "ofcourse",
                type: "video"
            },
            function (data, status) {
                if (status == "success") {
                    console.log("Commented " + $('#commentContents').val());
                    $('#comment').prepend(data);
                    $("#commentContents").val('');
                    $("#post").addClass("disabled");
                    $("#commentPostingSpinner").addClass('d-none');
                }
            });
    });
    $("#subscribe").click(function () {
        $.post("/api/legacy/subscribe",
            {
                subscription: user_id
            },
            function (data, status) {
                if (status == "success") {
                    if (data == subscribe_string) {
                        $("#subscribe").text(subscribe_string);
                        $("#subscribe").attr("class", "btn btn-primary");
                        console.log("Unsubscribed " + user_id);
                    } else if (data == unsubscribe_string) {
                        $("#subscribe").text(unsubscribe_string);
                        $("#subscribe").attr("class", "btn btn-secondary");
                        console.log("Subscribed " + user_id);
                    } else {
                        console.error('unexpected output! report to https://github.com/bluffingo/OpenSB/issues');
                    }
                }
            });
    });
    $("#like").click(function () {
        if ($("#like").attr("class") != "text-success") {
            $.post("/api/legacy/rate",
                {
                    rating: 5, // sb ratings are internally 5 stars
                    vidid: video_id
                },
                function (data, status) {
                    if (status == "success") {
                        if (data == 1) {
                            $("#like").attr("class", "text-success");
                            $("#likes").text(parseInt($("#likes").text()) + 1)
                            $("#dislikes").text(parseInt($("#dislikes").text()) - 1)
                            $("#dislike").attr("class", "text-body");
                        } else if (data == 0) {
                            $("#like").click();
                        } else {
                            console.error('unexpected output! report to https://github.com/bluffingo/OpenSB/issues');
                        }
                    }
                });
        }
    });
    $("#dislike").click(function () {
        if ($("#dislike").attr("class") != "text-danger") {
            $.post("/api/legacy/rate",
                {
                    rating: 1, // sb ratings are internally 5 stars
                    vidid: video_id
                },
                function (data, status) {
                    if (status == "success") {
                        if (data == 1) {
                            $("#dislike").attr("class", "text-danger");
                            $("#dislikes").text(parseInt($("#dislikes").text()) + 1)
                            $("#likes").text(parseInt($("#likes").text()) - 1)
                            $("#like").attr("class", "text-body");
                        } else if (data == 0) {
                            $("#dislike").click();
                        } else {
                            console.error('unexpected output! report to https://github.com/bluffingo/OpenSB/issues');
                        }
                    }
                });
        }
    });
});
