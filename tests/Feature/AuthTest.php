<?php
namespace Tests\Feature;

use System\Testing\HttpTestCase;

class AuthTest extends HttpTestCase
{
    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('/home')->assertRedirect('/login');
        $this->get('/system')->assertRedirect('/login');
    }

    public function testLoginPageRenders(): void
    {
        $this->get('/login')->assertStatus(200)->assertSee('Sign in')->assertSee('name="_token"');
    }

    public function testUserCanLogInAndOut(): void
    {
        $this->loginAsAdmin()->assertRedirect('/home');
        $this->get('/home')->assertStatus(200)->assertSee('Welcome back, Admin!')->assertSee('admin@example.com');

        // Signed-in users are sent away from the login page
        $this->get('/login')->assertRedirect('/home');

        $this->submit('/home', '/logout')->assertRedirect('/login');
        $this->get('/login')->assertSee('You have been signed out.');
        $this->get('/home')->assertRedirect('/login');
    }

    public function testRedirectsToIntendedPageAfterLogin(): void
    {
        $this->get('/system')->assertRedirect('/login');
        $this->loginAsAdmin()->assertRedirect('/system');
    }

    public function testWrongPasswordAndUnknownEmailShowTheSameError(): void
    {
        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertRedirect('/login');
        $this->get('/login')->assertSee('These credentials do not match our records.');

        $this->submit('/login', '/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertRedirect('/login');
        $this->get('/login')->assertSee('These credentials do not match our records.')->assertSee('nobody@example.com');

        $this->get('/home')->assertRedirect('/login');
    }

    public function testValidationErrorsOnEmptyForm(): void
    {
        $this->submit('/login', '/login', ['email' => '', 'password' => ''])->assertRedirect('/login');
        $this->get('/login')->assertSee('The email field is required.')->assertSee('The password field is required.');
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->get('/login');
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertStatus(419);
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password', '_token' => 'forged'])->assertStatus(419);
    }

    public function testBruteForceIsLockedOut(): void
    {
        // A dedicated account so other tests are not locked
        $this->db('users')->insert([
            'name' => 'Lock Test', 'email' => 'locktest@example.com',
            'password' => \System\Security\Hash::make('password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->submit('/login', '/login', ['email' => 'locktest@example.com', 'password' => "guess$i"]);
        }

        // Even the right password is refused while locked
        $this->submit('/login', '/login', ['email' => 'locktest@example.com', 'password' => 'password'])->assertRedirect('/login');
        $this->get('/login')->assertSee('Too many login attempts');
        $this->get('/home')->assertRedirect('/login');
    }

    public function testSessionIdChangesOnLogin(): void
    {
        $this->get('/login');
        $before = $this->cookies['PHPSESSID'] ?? null;
        $this->loginAsAdmin();
        $this->assertNotSame($before, $this->cookies['PHPSESSID'] ?? null, 'Session id must be regenerated on login');
    }

    public function testAuthEventsAreLoggedWithoutPasswords(): void
    {
        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'wrong-secret-123']);
        $this->loginAsAdmin();

        $log = file_get_contents(BASE_PATH . '/storage/logs/testing/bongo-' . date('Y-m-d') . '.log');
        $this->assertContains('WARNING: Failed login for admin@example.com', $log);
        $this->assertContains('INFO: User 1 logged in', $log);
        $this->assertNotContains('wrong-secret-123', $log);
    }
}
