<?php

namespace App\Services\Sources;

use App\Models\ParserClient;

class ParserClientService
{
    public function createWithToken(string $name, array $allowedSourceIds)
    {
        $client = ParserClient::create([
            'name' => $name,
            'is_active' => true,
            'allowed_source_ids' => $allowedSourceIds,
        ]);
        $token = $client->createToken('parser-client-token')->plainTextToken;

        return [
            'client' => $client,
            'token' => $token,
        ];
    }

    public function revoke(ParserClient $client)
    {
        $client->tokens()->delete();
        $client->update(['is_active' => false]);
    }
}
