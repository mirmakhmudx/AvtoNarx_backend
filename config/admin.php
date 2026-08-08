<?php

/*
|--------------------------------------------------------------------------
| Admin panel sozlamalari
|--------------------------------------------------------------------------
| mfa_required: yoqilsa (true), administratorlar 2FA'ni tasdiqlamaguncha
| admin paneldan foydalana olmaydi — sozlash sahifasiga yo'naltiriladi.
| Standart holatda o'chiq (false), shunda joriy adminlar 2FA'ni yoqib
| ulgurgunicha qulflanib qolmaydi. Rollout tugagach .env'da yoqing.
*/

return [
    'mfa_required' => (bool) env('ADMIN_MFA_REQUIRED', false),
    'mfa_setup_path' => env('ADMIN_MFA_SETUP_PATH', 'admin/two-factor-authentication'),
];
