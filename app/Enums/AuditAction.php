<?php

namespace App\Enums;

enum AuditAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case VIEW = 'view';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case SUBMIT = 'submit';
    case SIGN = 'sign';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case EXPORT = 'export';
}
