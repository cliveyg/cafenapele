<?php

namespace Tests\Feature;

use Igniter\Cart\Models\Order;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Igniter\User\Models\UserGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosStaffOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    public function test_another_server_cannot_mark_an_order_paid(): void
    {
        $order = $this->openOrderBy($this->staffUser());
        $otherServer = $this->staffUser();
        AdminAuth::login($otherServer);

        $response = $this->post(route('pos.paid', $order->getKey()), ['version' => 1]);

        $response->assertSessionHasErrors('version');
        $this->assertFalse((bool) Order::find($order->getKey())->processed);
    }

    public function test_manager_can_mark_another_servers_order_paid(): void
    {
        $order = $this->openOrderBy($this->staffUser());
        $manager = $this->staffUser();
        $manager->groups()->attach(UserGroup::factory()->create(['user_group_name' => 'Managers']));
        AdminAuth::login($manager);

        $this->post(route('pos.paid', $order->getKey()), ['version' => 1])
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue((bool) Order::find($order->getKey())->processed);
    }

    // The factory's bcrypt hash uses cost 10, but the test env uses BCRYPT_ROUNDS=4.
    private function staffUser(): User
    {
        return User::factory()->create(['password' => Hash::make('password')]);
    }

    private function openOrderBy(User $staff): Order
    {
        $order = Order::factory()->create(['processed' => false]);
        DB::table('cafenapele_pos_orders')->insert([
            'order_id' => $order->getKey(),
            'staff_id' => $staff->getKey(),
            'draft_token' => (string) \Illuminate\Support\Str::uuid(),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $order;
    }
}
