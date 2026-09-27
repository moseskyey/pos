<?php

namespace App\Support;

/**
 * Click-to-chat links (https://wa.me). They open WhatsApp on the phone or
 * WhatsApp Web with the message filled in; the cashier just presses send.
 */
final class WhatsApp
{
    public static function link(?string $phone, string $text): string
    {
        $number = $phone ? PhoneNumber::normalize($phone) : null;

        return 'https://wa.me/'.($number ?: '').'?text='.rawurlencode($text);
    }
}
