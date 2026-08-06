<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->session_id ?? null,
            'username' => $this->username ?? null,
            'is_online' => $this->is_online ?? false,
            'session_start' => $this->session_start ? $this->session_start->toDateTimeString() : null,
            'session_duration' => $this->session_duration ?? 0,
            'session_duration_formatted' => $this->formatDuration($this->session_duration ?? 0),
            'data_used' => $this->data_used ?? 0,
            'data_used_formatted' => $this->formatBytes($this->data_used ?? 0),
            'data_uploaded' => $this->data_uploaded ?? 0,
            'data_uploaded_formatted' => $this->formatBytes($this->data_uploaded ?? 0),
            'data_downloaded' => $this->data_downloaded ?? 0,
            'data_downloaded_formatted' => $this->formatBytes($this->data_downloaded ?? 0),
            'download_speed' => $this->download_speed ?? 0,
            'upload_speed' => $this->upload_speed ?? 0,
            'nas_ip_address' => $this->nas_ip_address ?? null,
            'nas_port' => $this->nas_port ?? null,
            'framed_ip_address' => $this->framed_ip_address ?? null,
            'fup_limit' => $this->fup_limit ?? 0,
            'fup_limit_formatted' => $this->formatBytes($this->fup_limit ?? 0),
            'fup_usage_percentage' => $this->fup_limit > 0 ? min(100, ($this->data_used ?? 0) / $this->fup_limit * 100) : 0,
            'fup_remaining' => $this->fup_limit > 0 ? max(0, $this->fup_limit - ($this->data_used ?? 0)) : 0,
            'fup_remaining_formatted' => $this->formatBytes($this->fup_limit > 0 ? max(0, $this->fup_limit - ($this->data_used ?? 0)) : 0),
            'last_updated' => $this->last_updated ? $this->last_updated->toDateTimeString() : null,
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

    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return round($seconds / 60, 0) . 'm';
        }
        if ($seconds < 86400) {
            return round($seconds / 3600, 1) . 'h';
        }
        return round($seconds / 86400, 1) . 'd';
    }
}
