<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private bool $enabled;

    public function __construct()
    {
        $this->cloudName = (string) config('services.cloudinary.cloud_name', '');
        $this->apiKey = (string) config('services.cloudinary.api_key', '');
        $this->apiSecret = (string) config('services.cloudinary.api_secret', '');
        $this->enabled = (bool) config('services.cloudinary.enabled', false);
    }

    public function enabled(): bool
    {
        return $this->enabled && $this->cloudName !== '' && $this->apiKey !== '' && $this->apiSecret !== '';
    }

    public function upload(UploadedFile $file, string $folder = 'agripulse', string $resourceType = 'auto'): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException('Cloudinary is not configured.');
        }

        $timestamp = time();
        $params = [
            'folder' => trim($folder, '/'),
            'timestamp' => $timestamp,
        ];

        $signature = $this->sign($params);
        $endpoint = sprintf(
            'https://api.cloudinary.com/v1_1/%s/%s/upload',
            $this->cloudName,
            $resourceType
        );

        $response = Http::attach(
            'file',
            fopen($file->getRealPath(), 'r'),
            $file->getClientOriginalName()
        )->post($endpoint, [
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'folder' => $params['folder'],
            'signature' => $signature,
        ]);

        if (!$response->successful()) {
            Log::error('Cloudinary upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'folder' => $folder,
            ]);

            throw new RuntimeException('Cloudinary upload failed.');
        }

        $data = $response->json();

        return [
            'url' => $data['secure_url'] ?? $data['url'] ?? null,
            'public_id' => $data['public_id'] ?? null,
            'resource_type' => $data['resource_type'] ?? $resourceType,
            'raw' => $data,
        ];
    }

    public function uploadUrl(UploadedFile $file, string $folder = 'agripulse', string $resourceType = 'auto'): string
    {
        $uploaded = $this->upload($file, $folder, $resourceType);

        if (empty($uploaded['url'])) {
            throw new RuntimeException('Cloudinary did not return an URL.');
        }

        return $uploaded['url'];
    }

    public function uploadImageUrl(UploadedFile $file, string $folder = 'agripulse'): string
    {
        return $this->uploadUrl($file, $folder, 'image');
    }

    public function deleteByUrl(?string $url, string $resourceType = 'image'): void
    {
        $publicId = $this->publicIdFromUrl($url);

        if (!$publicId) {
            return;
        }

        $this->delete($publicId, $resourceType);
    }

    public function delete(string $publicId, string $resourceType = 'image'): void
    {
        if (!$this->enabled()) {
            return;
        }

        $timestamp = time();
        $params = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $endpoint = sprintf(
            'https://api.cloudinary.com/v1_1/%s/%s/destroy',
            $this->cloudName,
            $resourceType
        );

        Http::asForm()->post($endpoint, [
            'api_key' => $this->apiKey,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'signature' => $this->sign($params),
        ]);
    }

    public function isCloudinaryUrl(?string $url): bool
    {
        return is_string($url) && str_contains($url, 'res.cloudinary.com');
    }

    private function sign(array $params): string
    {
        ksort($params);

        $payload = collect($params)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&');

        return sha1($payload . $this->apiSecret);
    }

    private function publicIdFromUrl(?string $url): ?string
    {
        if (!$this->isCloudinaryUrl($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (!$path || !str_contains($path, '/upload/')) {
            return null;
        }

        $afterUpload = explode('/upload/', $path, 2)[1] ?? '';
        $afterUpload = preg_replace('#^v\d+/#', '', $afterUpload);

        if (!$afterUpload) {
            return null;
        }

        return preg_replace('/\.[a-zA-Z0-9]+$/', '', $afterUpload);
    }
}
