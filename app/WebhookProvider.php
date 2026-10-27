<?php

namespace App;

enum WebhookProvider: string
{
    case Mailgun = 'mailgun';
    case Mailtrap = 'mailtrap';
}
