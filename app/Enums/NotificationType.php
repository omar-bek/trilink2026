<?php

namespace App\Enums;

enum NotificationType: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
}
