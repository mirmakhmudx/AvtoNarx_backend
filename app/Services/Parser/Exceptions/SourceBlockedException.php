<?php

namespace App\Services\Parser\Exceptions;

/**
 * Manba haqiqatan ham bloklaganini bildiradi (403, 429 yoki CAPTCHA/robot sahifasi).
 * Bunday holatda parser TZ qoidasiga ko'ra ushbu manba bo'yicha DARHOL to'xtashi kerak
 * (qolgan targetlarni ham urinib ko'rish, aylanib o'tishga harakat qilish YO'Q).
 *
 * Oddiy vaqtinchalik xatolar (404, timeout, tarmoq xatosi) bu bilan aralashtirilmaydi —
 * ular faqat o'sha bitta targetni rad etadi, qolganlar davom etadi.
 */
class SourceBlockedException extends \RuntimeException {}
