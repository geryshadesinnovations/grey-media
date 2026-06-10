<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\StreamToken;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) redirect('/dashboard');
        echo view('auth/login', [
            'error'    => flash('error'),
            'username' => old('username', ''),
        ]);
    }

    public function login(): void
    {
        Csrf::verifyOrFail();

        $username = trim((string) ($_POST['username'] ?? ''));
        $pass     = (string) ($_POST['password'] ?? '');

        // Throttle: more than 5 failed attempts from this IP in last 15 min -> reject
        $recent = (int) Database::scalar(
            "SELECT COUNT(*) FROM failed_logins WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)",
            [client_ip()]
        );
        if ($recent >= 5) {
            flash('error', 'Too many failed login attempts. Please try again later.');
            $_SESSION['__old']['username'] = $username;
            redirect('/login');
        }

        // Usernames are letters + numbers only.
        if (!preg_match('/^[A-Za-z0-9]+$/', $username) || $pass === '') {
            flash('error', 'Please enter a valid username and password.');
            $_SESSION['__old']['username'] = $username;
            redirect('/login');
        }

        if (!Auth::attempt($username, $pass)) {
            flash('error', 'Invalid credentials.');
            $_SESSION['__old']['username'] = $username;
            redirect('/login');
        }

        unset($_SESSION['__old']);
        redirect('/dashboard');
    }

    public function logout(): void
    {
        Csrf::verifyOrFail();
        if (Auth::id()) StreamToken::revokeForUser(Auth::id());
        Auth::logout();
        redirect('/login');
    }
}
