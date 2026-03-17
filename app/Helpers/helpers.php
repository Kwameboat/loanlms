<?php

if (! function_exists('money')) {
    /**
     * Format a number as Ghana Cedi amount.
     */
    function money(float|int|string $amount, int $decimals = 2): string
    {
        $symbol = config('bigcash.company.currency_symbol', '₵');
        return $symbol . number_format((float) $amount, $decimals);
    }
}

if (! function_exists('gh_date')) {
    /**
     * Format a date in Ghana dd/mm/YYYY format.
     */
    function gh_date($date, string $format = null): string
    {
        if (! $date) return '—';
        $fmt = $format ?? config('bigcash.company.date_format', 'd/m/Y');
        return \Carbon\Carbon::parse($date)->format($fmt);
    }
}

if (! function_exists('percentage')) {
    function percentage(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals) . '%';
    }
}

if (! function_exists('status_badge')) {
    /**
     * Return a Bootstrap badge HTML for a loan status.
     */
    function status_badge(string $status): string
    {
        $colors = config('bigcash.loan.status_colors', []);
        $labels = config('bigcash.loan.statuses', []);
        $color  = $colors[$status] ?? 'secondary';
        $label  = $labels[$status] ?? ucfirst($status);
        return "<span class=\"badge bg-{$color}\">{$label}</span>";
    }
}

if (! function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $ini   = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $ini .= strtoupper(substr($p, 0, 1));
        }
        return $ini;
    }
}

if (! function_exists('file_size_human')) {
    function file_size_human(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
