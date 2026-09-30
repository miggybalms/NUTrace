<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A copied URL must not open an account's area in a browser that never signed
 * in. Before this guard, /users rendered an empty dashboard to anyone and
 * /admin/* died with "Route [login] not defined." because no route carried that
 * name. Both symptoms are covered here.
 */
class PrivateAreaGuardTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function privatePaths(): array
    {
        return [
            'admin dashboard' => ['/admin'],
            'admin disposal'  => ['/admin/disposal'],
            'admin requests'  => ['/admin/requests'],
            'user dashboard'  => ['/users'],
            'user assets'     => ['/users/assets'],
            'department head' => ['/department-head'],
        ];
    }

    #[DataProvider('privatePaths')]
    public function test_a_session_less_visit_is_sent_to_the_landing_page(string $path): void
    {
        $this->get($path)->assertRedirect('/');
    }

    #[DataProvider('privatePaths')]
    public function test_a_fetch_from_an_open_page_gets_json_instead_of_a_redirect(string $path): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->get($path)
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_the_landing_page_and_the_sign_in_page_stay_public(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_the_sign_in_route_is_named_so_auth_redirects_cannot_fail(): void
    {
        // "Route [login] not defined." is the 500 that used to greet a copied
        // admin URL; the name has to exist for Laravel's auth redirects.
        $this->assertTrue(Route::has('login'));
    }
}
