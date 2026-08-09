<?php

namespace App\Http\Requests\Ingestion;

use App\Models\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMarketListingBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'uuid'],
            'source' => ['required', 'string', 'exists:sources,code'],
            'mode' => ['required', 'string', 'in:incremental,snapshot,offline_html'],
            'parser_version' => ['nullable', 'string', 'max:50'],
            'collected_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.external_id' => ['required', 'string', 'max:255'],
            'items.*.url' => ['required', 'string', 'max:1000', 'url'],
            'items.*.brand' => ['required', 'string', 'max:180'],
            'items.*.model' => ['required', 'string', 'max:180'],
            // TZ 8.2: year majburiy; oraliq 1950..joriy yil+1 (invalid_year).
            'items.*.year' => ['required', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'items.*.price.amount' => ['required', 'integer', 'min:1'],
            'items.*.price.currency' => ['required', 'string', 'in:UZS,USD'],
            'items.*.condition' => ['nullable', 'string'],
            'items.*.seller_type' => ['nullable', 'string'],
            'items.*.location.region' => ['nullable', 'string', 'max:180'],
            'items.*.location.city' => ['nullable', 'string', 'max:180'],
            'items.*.observed_at' => ['required', 'date'],
            'items.*.published_at' => ['nullable', 'date'],
            // TZ 8.2: content_hash majburiy va 64 belgili hex (SHA-256).
            'items.*.content_hash' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.year.required' => 'invalid_year',
            'items.*.year.integer' => 'invalid_year',
            'items.*.year.min' => 'invalid_year',
            'items.*.year.max' => 'invalid_year',
            'items.*.content_hash.required' => 'invalid_content_hash',
            'items.*.content_hash.regex' => 'invalid_content_hash',
            'items.*.url.url' => 'invalid_url_domain',
        ];
    }

    /**
     * TZ 8.2/8.6: har bir item URL'i shu batch'ning MANBASIGA tegishli domenda
     * bo'lishi shart (invalid_url_domain). source=olx_uz bo'lgan batch ichida
     * boshqa saytning URL'i bo'lsa — bu parser xatosi, batch rad etiladi.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sourceCode = $this->input('source');

            if (! is_string($sourceCode) || $sourceCode === '') {
                return; // 'source' qoidasi bu holatni ushlaydi.
            }

            $source = Source::query()->where('code', $sourceCode)->first();

            if ($source === null || empty($source->base_url)) {
                return; // 'exists:sources,code' noma'lum manbani ushlaydi.
            }

            $sourceHost = $this->hostOf($source->base_url);

            if ($sourceHost === '') {
                return;
            }

            // Manba sozlamalarida aniq ruxsat etilgan domenlar bo'lsa — o'shalar,
            // aks holda manbaning ro'yxatga olinadigan (registrable) domeni.
            $allowedDomains = is_array($source->settings['allowed_domains'] ?? null)
                ? array_map(fn ($d) => strtolower((string) $d), $source->settings['allowed_domains'])
                : [$this->registrableDomain($sourceHost)];

            foreach ((array) $this->input('items', []) as $index => $item) {
                $url = is_array($item) ? ($item['url'] ?? null) : null;

                if (! is_string($url) || $url === '') {
                    continue; // 'required' qoidasi ushlaydi.
                }

                $host = $this->hostOf($url);

                if ($host === '' || ! $this->hostAllowed($host, $allowedDomains)) {
                    $validator->errors()->add("items.$index.url", 'invalid_url_domain');
                }
            }
        });
    }

    private function hostOf(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    /**
     * Domenning oxirgi ikki qismini qaytaradi (masalan www.olx.uz -> olx.uz).
     * .uz kabi ikki qismli TLD'lar uchun yetarli; frontend/manba domenlari shu
     * shaklda.
     */
    private function registrableDomain(string $host): string
    {
        $parts = explode('.', $host);

        if (count($parts) <= 2) {
            return $host;
        }

        return implode('.', array_slice($parts, -2));
    }

    /**
     * Host ruxsat etilgan domenlardan biriga to'g'ri keladimi — aynan o'zi yoki
     * subdomeni (masalan avto.uzum.uz, uzum.uz domeniga tegishli).
     *
     * @param  array<int, string>  $allowedDomains
     */
    private function hostAllowed(string $host, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $domain) {
            if ($domain === '') {
                continue;
            }

            if ($host === $domain || str_ends_with($host, '.'.$domain) || $this->registrableDomain($host) === $domain) {
                return true;
            }
        }

        return false;
    }
}
