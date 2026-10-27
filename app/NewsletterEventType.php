<?php

namespace App;

enum NewsletterEventType: string
{
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';
    case Opened = 'opened';
    case Clicked = 'clicked';
}
