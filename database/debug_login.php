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
use App\Models\User;

$email = 'admin@operon.ai';
$password = 'operon123';

echo "1. Buscando usuário com email: {$email}\n";
$user = User::findByEmail($email);

if (!$user) {
    echo "❌ FALHA: Usuário não encontrado no banco de dados.\n";
    
    echo "Vamos verificar quem existe na tabela users:\n";
    $allUsers = Database::select('SELECT id, email, password FROM users');
    print_r($allUsers);
    exit(1);
}

echo "✅ Usuário encontrado!\n";
print_r($user);

echo "\n2. Verificando senha...\n";
$isValid = password_verify($password, $user['password']);

if ($isValid) {
    echo "✅ Senha correta!\n";
} else {
    echo "❌ FALHA: Senha incorreta.\n";
    echo "Hash esperado para 'operon123': " . password_hash($password, PASSWORD_BCRYPT) . "\n";
    echo "Hash no banco: " . $user['password'] . "\n";
}

echo "\n3. Verificando status ativo...\n";
if (!$user['active']) {
    echo "❌ FALHA: Usuário está inativo (active = " . $user['active'] . ").\n";
} else {
    echo "✅ Usuário está ativo!\n";
}
