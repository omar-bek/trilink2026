<?php

namespace App\Enums;

enum DisputeDecisionOutcome: string
{
    case FOR_CLAIMANT = 'for_claimant';
    case FOR_RESPONDENT = 'for_respondent';
    case SPLIT = 'split';
    case SETTLED = 'settled';       // parties reached their own settlement
    case DISMISSED = 'dismissed';   // dismissed on merit
    case WITHDRAWN = 'withdrawn';   // claimant withdrew
}
