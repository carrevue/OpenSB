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

namespace Actions;

enum ActionError: string
{
    case LoginRequired = "notify_login_required";
    case Banned = "error_banned";
    case Unverified = "error_unverified";
    case NoPermission = "notify_no_permission";
    case InvalidUpload = "notify_invalid_upload";
    case InvalidUser = "notify_invalid_user";
    case InvalidRating = "error_invalid_rating";
    case CannotFollowSelf = "error_cannot_follow_self";
    case CommentEmpty = "error_comment_empty";
    case CommentTooLong = "error_comment_too_long";
    case CommentRatelimit = "error_comment_ratelimit";
    case CommentsDisabled = "comments_disabled";
    case WriteDisabled = "notify_write_disabled";
    case WriteRatelimit = "notify_write_ratelimit";
    case AlreadyTakenDown = "notify_dashboard_upload_takedown_already";
    case RestoreFailed = "notify_dashboard_upload_restore_failed";
    case InvalidPlaylist = "invalid_playlist";
    case PlaylistMissingTitle = "error_playlist_missing_title";
    case PlaylistAlreadyAdded = "error_playlist_already_added";
    case PlaylistNotAdded = "error_playlist_not_added";
    case UploadDisabled = "notify_upload_disabled";
    case UploadRatelimit = "notify_upload_ratelimit";
    case InvalidFormat = "notify_invalid_format";
    case AudioUnimplemented = "notify_upload_audio_unimplemented";
    case InvalidMessage = "error_invalid_message";
    case CannotMessageSelf = "error_cannot_message_self";
    case MessageEmpty = "error_message_empty";
    case MessageTooLong = "error_message_too_long";
    case MessageTitleTooLong = "error_message_title_too_long";
    case MessageRatelimit = "error_message_ratelimit";
}
