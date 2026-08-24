<?php

namespace App\Enums;

enum ProspectSequenceStep: string
{
    case AwaitingInitialEmail = 'awaiting_initial_email';
    case InitialEmail = 'initial_email';
    case ColdFollowUp = 'cold_follow_up';
    case FinalFollowUp = 'final_follow_up';
    case AwaitingPersonalisedVideo = 'awaiting_personalised_video';
    case PersonalisedVideo = 'personalised_video';
    case PostVideoFollowUp = 'post_video_follow_up';
    case Complete = 'complete';
}
