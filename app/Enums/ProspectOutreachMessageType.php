<?php

namespace App\Enums;

enum ProspectOutreachMessageType: string
{
    case Initial = 'initial';
    case ColdFollowUp = 'cold_follow_up';
    case FinalFollowUp = 'final_follow_up';
    case PersonalisedVideo = 'personalised_video';
    case PostVideoFollowUp = 'post_video_follow_up';
}
