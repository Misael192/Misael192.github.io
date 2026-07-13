<?php

declare(strict_types=1);

/** Helpers globais do MVP — pequenos, previsíveis e sem mágica. */

/** Escapa saída para HTML — usar em TODA variável impressa nas views. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
    $section = $GLOBALS['config'][$file] ?? [];

    return $item === null ? $section : ($section[$item] ?? $default);
}

function redirect(string $to): never
{
    header("Location: {$to}");
    exit;
}

/** Renderiza uma view dentro do layout do app (header + sidebar + footer). */
function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_PATH."/views/{$name}.php";
}

/** Usuário autenticado (ou null). */
function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Mensagens flash (sucesso/erro) entre redirects. */
function flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;

        return null;
    }
    $value = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);

    return $value;
}

/** Token CSRF por sessão; campo oculto em todo formulário POST. */
function csrf_token(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
}

/** Formata data ISO → dd/mm/aaaa. */
function br_date(?string $iso): string
{
    return $iso ? date('d/m/Y', strtotime($iso)) : '—';
}
