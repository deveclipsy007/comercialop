<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\User;

class AdminAuthController
{
    public function showLogin(): void
    {
        // Se já vez o login explícito no painel admin, vai pro dashboard
        if (Session::get('admin_auth')) {
            View::redirect('/admin');
        }

        View::render('admin/login', ['active' => 'admin_login'], 'layout.minimal');
    }

    public function login(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrf     = $_POST['_csrf'] ?? '';

        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Token de segurança inválido. Recarregue a página.');
            View::redirect('/admin/login');
        }

        if (empty($email) || empty($password)) {
            Session::flash('error', 'E-mail e senha são obrigatórios.');
            View::redirect('/admin/login');
        }

        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            Session::flash('error', 'Credenciais inválidas. Verifique e tente novamente.');
            View::redirect('/admin/login');
        }

        // Verifica se é da master class admin
        if ($user['role'] !== 'admin') {
            Session::flash('error', 'Esta área é restrita a administradores do sistema.');
            View::redirect('/admin/login');
        }

        if (!$user['active']) {
            Session::flash('error', 'Sua conta de administrador está desativada.');
            View::redirect('/admin/login');
        }

        // Se passar por tudo, seta também a sessão tradicional do painel, caso não esteja logado, e a sessão admin_auth
        if (!Session::isAuthenticated()) {
            Session::login($user);
        }
        $_SESSION['admin_auth'] = true; // Marca exclusiva do login administrativo!

        View::redirect('/admin');
    }
}
