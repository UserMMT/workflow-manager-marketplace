<?php

declare(strict_types=1);

// Crée un compte admin/collaborator/maintainer. Pas d'inscription libre-
// service (voir UserAuthService) : le tout premier admin doit être créé ainsi
// en ligne de commande, puisque POST /api/admin/users exige déjà un admin.
//
// Usage : php cli/create_user.php <name> <email> <password> <role admin|collaborator|maintainer>

use App\Models\User;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->load();
}

$settings = require $root . '/config/settings.php';
(require $root . '/bootstrap/database.php')($settings);

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;
$role = $argv[4] ?? null;

if ($name === null || $email === null || $password === null || $role === null) {
    fwrite(STDERR, "Usage : php cli/create_user.php <name> <email> <password> <role admin|collaborator|maintainer>\n");
    exit(1);
}

$validRoles = ['admin', 'collaborator', 'maintainer'];
if (!in_array($role, $validRoles, true)) {
    fwrite(STDERR, sprintf("Rôle invalide : \"%s\" (attendu : %s).\n", $role, implode(', ', $validRoles)));
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Le mot de passe doit contenir au moins 8 caractères.\n");
    exit(1);
}
if (User::where('email', $email)->exists()) {
    fwrite(STDERR, sprintf("Un compte existe déjà avec l'email \"%s\".\n", $email));
    exit(1);
}

$user = new User([
    'name' => $name,
    'email' => $email,
    'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => $role,
]);
$user->save();

fwrite(STDOUT, sprintf("+ Utilisateur \"%s\" (%s, rôle %s) créé.\n", $user->name, $user->email, $user->role));
