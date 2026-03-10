<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once APP_PATH . '/Helpers/helpers.php';

use App\Core\Database;

try {
    $password = 'operon123';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    echo "Gerando novo hash para '{$password}': {$hash}\n";

    echo "Atualizando senha do admin no banco...\n";
    Database::execute('UPDATE users SET password = ? WHERE email = ?', [$hash, 'admin@operon.ai']);
    
    echo "Verificando se salvou certo...\n";
    $user = Database::selectFirst('SELECT password FROM users WHERE email = ?', ['admin@operon.ai']);
    
    if (password_verify($password, $user['password'])) {
        echo "✅ SUCESSO! A senha foi atualizada e testada com o password_verify.\n";
    } else {
        echo "❌ FALHA: A senha salva não passa na verificação.\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
