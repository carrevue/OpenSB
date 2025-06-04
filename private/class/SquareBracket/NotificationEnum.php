<?php

namespace SquareBracket;

enum NotificationEnum: int
{
    case CommentUpload = 0;
    case CommentProfile = 1;
    case CommentJournal = 2;
    case NewUpload = 3;
    case Follow = 4;
    case NewJournal = 5;
    case UserRename = 6;
}