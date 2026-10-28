<?php
namespace App\Controllers;

use App\Services\AuthService;

class AuthController
{
    public function showLogin(): void
    {
        if (AuthService::check()) {
            redirect(url('dashboard'));
        }

        view('auth.login', [], 'none');
    }

    public function login(): void
    {
        AuthService::verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            flash('danger', 'Please enter both username and password.');
            redirect(url('login'));
        }

        $result = AuthService::attempt($username, $password);

        if ($result['success']) {
            flash('success', 'Welcome back! You are now logged in.');
            redirect(url('dashboard'));
        } else {
            flash('danger', $result['error'] ?? 'Authentication failed.');
            redirect(url('login'));
        }
    }

    public function logout(): void
    {
        AuthService::verifyCsrf();
        AuthService::logout();
        flash('info', 'You have been successfully logged out.');
        redirect(url('login'));
    }
}
