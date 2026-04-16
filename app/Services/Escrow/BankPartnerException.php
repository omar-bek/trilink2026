<?php

namespace App\Services\Escrow;

use RuntimeException;

/**
 * Thrown by any BankPartnerInterface implementation when the bank refuses
 * an operation (validation, KYB, insufficient funds, network error). The
 * EscrowController catches it and surfaces the message to the buyer
 * through the standard error flash channel.
 */
class BankPartnerException extends RuntimeException {}
