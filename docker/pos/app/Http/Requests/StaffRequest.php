<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Igniter\User\Http\Requests\UserRequest;
use Illuminate\Validation\Rule;
use Override;

class StaffRequest extends UserRequest
{
    #[Override]
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['email'] = ['nullable', 'max:96', 'email:filter',
            Rule::unique('admin_users')->ignore($this->getRecordId(), 'user_id'),
        ];

        return $rules;
    }
}
