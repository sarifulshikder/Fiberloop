<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'download_speed' => $this->download_speed,
            'upload_speed' => $this->upload_speed,
            'download_speed_formatted' => $this->download_speed . ' Mbps',
            'upload_speed_formatted' => $this->upload_speed . ' Mbps',
            'fup_threshold' => $this->fup_threshold,
            'fup_threshold_formatted' => $this->fup_threshold ? $this->formatBytes($this->fup_threshold) : null,
            'fup_throttled_download' => $this->fup_throttled_download,
            'fup_throttled_upload' => $this->fup_throttled_upload,
            'fup_reset_cycle' => $this->fup_reset_cycle,
            'price' => $this->price / 100, // Convert from poysha to BDT
            'price_formatted' => 'BDT ' . number_format($this->price / 100, 2),
            'billing_cycle' => $this->billing_cycle?->value,
            'billing_type' => $this->billing_type?->value,
            'installation_fee' => $this->installation_fee / 100,
            'security_deposit' => $this->security_deposit / 100,
            'tax_rate' => $this->tax_rate / 100,
            'is_active' => $this->is_active,
            'is_popular' => $this->is_popular,
            'sort_order' => $this->sort_order,
            'features' => $this->features,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
