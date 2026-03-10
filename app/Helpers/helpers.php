<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) return $default;
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return \App\Core\App::config($key, $default);
    }
}

if (!function_exists('session')) {
    function session(string $key, mixed $default = null): mixed
    {
        return \App\Core\Session::get($key, $default);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Core\Session::csrf();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(env('APP_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('/' . ltrim($path, '/'));
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return \App\Core\Session::user();
    }
}

if (!function_exists('isAuthenticated')) {
    function isAuthenticated(): bool
    {
        return \App\Core\Session::isAuthenticated();
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        echo '<pre style="background:#111;color:#0f0;padding:20px;font-size:12px;border-radius:8px;">';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        exit;
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo(string $datetime): string
    {
        $now  = new \DateTime();
        $then = new \DateTime($datetime);
        $diff = $now->diff($then);

        if ($diff->y > 0)  return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ' atrás';
        if ($diff->m > 0)  return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '') . ' atrás';
        if ($diff->d > 0)  return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
        if ($diff->h > 0)  return $diff->h . 'h atrás';
        if ($diff->i > 0)  return $diff->i . 'min atrás';
        return 'agora';
    }
}

if (!function_exists('scoreColor')) {
    function scoreColor(int $score): string
    {
        if ($score >= 75) return 'text-emerald-400';
        if ($score >= 50) return 'text-amber-400';
        return 'text-red-400';
    }
}

if (!function_exists('scoreBg')) {
    function scoreBg(int $score): string
    {
        if ($score >= 75) return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
        if ($score >= 50) return 'bg-amber-500/20 text-amber-400 border-amber-500/30';
        return 'bg-red-500/20 text-red-400 border-red-500/30';
    }
}

if (!function_exists('stageLabel')) {
    function stageLabel(string $stage): string
    {
        return match ($stage) {
            'new'         => 'Prospecção',
            'contacted'   => 'Contactado',
            'qualified'   => 'Qualificado',
            'proposal'    => 'Proposta',
            'closed_won'  => 'Ganho',
            'closed_lost' => 'Perdido',
            default       => $stage,
        };
    }
}

if (!function_exists('stageBadgeClass')) {
    function stageBadgeClass(string $stage): string
    {
        return match ($stage) {
            'new'         => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
            'contacted'   => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
            'qualified'   => 'bg-violet-500/20 text-violet-400 border-violet-500/30',
            'proposal'    => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
            'closed_won'  => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
            'closed_lost' => 'bg-red-500/20 text-red-400 border-red-500/30',
            default       => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
        };
    }
}
