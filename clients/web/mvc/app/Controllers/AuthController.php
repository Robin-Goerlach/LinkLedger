\
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\UserModel;

/**
 * Class AuthController
 *
 * Web Auth: Login / Register / Logout
 */
final class AuthController extends BaseController
{
    public function __construct(
        \App\Core\App $app,
        \App\Core\View $view,
        private UserModel $users
    ) {
        parent::__construct($app, $view);
    }

    public function showLogin(): void
    {
        $this->view->render('auth/login');
    }

    public function login(): void
    {
        $this->requireCsrf();

        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        $u = $this->users->findByEmail($email);
        if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
            Session::flash('error', 'Login fehlgeschlagen (E-Mail/Passwort).');
            $this->redirect('/login');
        }

        Session::set('user_id', (int)$u['id']);
        Session::flash('success', 'Willkommen!');
        $this->redirect('/app');
    }

    public function showRegister(): void
    {
        $this->view->render('auth/register');
    }

    public function register(): void
    {
        $this->requireCsrf();

        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($email === '' || $pass === '') {
            Session::flash('warn', 'Bitte E-Mail und Passwort ausfüllen.');
            $this->redirect('/register');
        }

        if ($this->users->findByEmail($email)) {
            Session::flash('warn', 'E-Mail ist bereits registriert.');
            $this->redirect('/register');
        }

        $this->users->create($email, $pass);
        Session::flash('success', 'Registriert! Bitte einloggen.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        Session::start();
        Session::forget('user_id');
        Session::flash('success', 'Du bist ausgeloggt.');
        $this->redirect('/login');
    }
}
