<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Igniter\Admin\Helpers\AdminHelper;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Http\Controllers\Login;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Login
{
    public function onLogin(): RedirectResponse
    {
        $data = $this->validate(post(), [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'min:6'],
        ], [], [
            'username' => 'Username',
            'password' => lang('igniter.user::default.login.label_password'),
        ]);

        Event::dispatch('igniter.admin.beforeAuthenticate', [$data]);

        if (!AdminAuth::check() && !AdminAuth::attempt(array_only($data, ['username', 'password']), true)) {
            throw ValidationException::withMessages(['username' => lang('igniter.user::default.login.alert_login_failed')]);
        }

        session()->regenerate();

        return ($redirectUrl = input('redirect'))
            ? AdminHelper::redirect($redirectUrl)
            : AdminHelper::redirectIntended('dashboard');
    }
}
