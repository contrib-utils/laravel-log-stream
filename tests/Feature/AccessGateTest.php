<?php

namespace LogScope\Tests\Feature;

use LogScope\Auth\EnvCredentialAuthorizer;
use LogScope\Facades\LogScope;
use LogScope\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AccessGateTest extends TestCase
{
    /**
     * The Authorizer binding reads config when resolved (per request), so
     * setting it in the test body before the request is picked up.
     */
    protected function configureCredentials(): void
    {
        config([
            'logscope.auth.user' => 'admin',
            'logscope.auth.password' => 'secret',
        ]);
    }

    // --- Env credential path -------------------------------------------------

    #[Test]
    public function anonymous_ui_request_redirects_to_login_when_credentials_configured(): void
    {
        $this->configureCredentials();

        $this->get('/logscope')
            ->assertRedirect('/logscope/login');
    }

    #[Test]
    public function api_rejects_missing_or_wrong_credentials_with_401(): void
    {
        $this->configureCredentials();

        $this->getJson('/logscope/api/ping')->assertStatus(401);

        $this->withHeaders($this->basic('admin', 'wrong'))
            ->getJson('/logscope/api/ping')
            ->assertStatus(401);
    }

    #[Test]
    public function api_accepts_valid_basic_credentials(): void
    {
        $this->configureCredentials();

        $this->withHeaders($this->basic('admin', 'secret'))
            ->getJson('/logscope/api/ping')
            ->assertOk()
            ->assertJson(['data' => ['ok' => true]]);
    }

    #[Test]
    public function login_form_grants_session_access(): void
    {
        $this->configureCredentials();

        $this->post('/logscope/login', [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect('/logscope');

        $this->get('/logscope')->assertOk();
    }

    #[Test]
    public function login_form_rejects_bad_credentials(): void
    {
        $this->configureCredentials();

        $this->from('/logscope/login')
            ->post('/logscope/login', [
                'username' => 'admin',
                'password' => 'nope',
            ])
            ->assertRedirect('/logscope/login')
            ->assertSessionHasErrors('username');

        $this->get('/logscope')->assertRedirect('/logscope/login');
    }

    // --- Production lockdown --------------------------------------------------

    #[Test]
    public function production_with_no_credentials_is_locked_down(): void
    {
        // Default testbench env is "testing" (non-local) and no creds set.
        $this->getJson('/logscope/api/ping')->assertStatus(401);
        $this->get('/logscope')->assertForbidden();
    }

    #[Test]
    public function local_with_no_credentials_is_open(): void
    {
        $this->app['env'] = 'local';

        $this->get('/logscope')->assertOk();
        $this->getJson('/logscope/api/ping')->assertOk();
    }

    // --- Custom authorizer precedence ----------------------------------------

    #[Test]
    public function custom_authorizer_takes_precedence_and_can_allow(): void
    {
        // Even with no env credentials in a non-local env, a custom callback wins.
        LogScope::auth(fn () => true);

        $this->getJson('/logscope/api/ping')->assertOk();
    }

    #[Test]
    public function custom_authorizer_can_deny_and_returns_403_without_login_redirect(): void
    {
        $this->configureCredentials();

        LogScope::auth(fn () => false);

        // No interactive login when a custom authorizer is active → 403, not redirect.
        $this->get('/logscope')->assertForbidden();
    }

    #[Test]
    public function session_key_constant_is_stable(): void
    {
        $this->assertSame('logscope_authenticated', EnvCredentialAuthorizer::SESSION_KEY);
    }
}
