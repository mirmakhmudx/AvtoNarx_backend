<?php

namespace App\Exceptions;

/**
 * E'lon "shubhali" deb topilib rad etilganda tashlanadi (masalan mashina
 * uchun aqlga sig'maydigan narx yoki OLX fallback natijasi).
 *
 * MUHIM: xususiyat 'code' DEB ATALMAYDI — chunki \Exception'da allaqachon
 * non-readonly $code bor va uni readonly qilib qayta e'lon qilib bo'lmaydi
 * (PHP fatal). Shu sabab UnmatchedCatalogEntityException kabi $errorCode
 * ishlatiladi va errorCode() metodi orqali o'qiladi.
 */
class SuspiciousListingRejectedException extends \RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
