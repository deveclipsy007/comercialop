<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\User;

class AuthController
{
    public function showLogin(): void
    {
        if (Session::isAuthenticated()) {
            View::redirect('/');
        }
        View::render('auth/login', ['active' => 'login'], 'layout.minimal');
    }

    public function login(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrf     = $_POST['_csrf'] ?? '';

        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Token de segurança inválido. Recarregue a página.');
            View::redirect('/login');
        }

        if (empty($email) || empty($password)) {
            Session::flash('error', 'E-mail e senha são obrigatórios.');
            View::redirect('/login');
        }

        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            Session::flash('error', 'Credenciais inválidas. Verifique e tente novamente.');
            View::redirect('/login');
        }

        if (!$user['active']) {
            Session::flash('error', 'Conta desativada. Contate o administrador.');
            View::redirect('/login');
        }

        Session::login($user);
        View::redirect('/');
    }

    public function logout(): void
    {
        Session::logout();
        View::redirect('/login');
    }
}
