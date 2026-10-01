<?php
namespace Tests\Feature;

use System\Testing\HttpTestCase;

class AuthTest extends HttpTestCase
{
    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/products')->assertRedirect('/login');
    }

    public function testLoginPageRenders(): void
    {
        $this->get('/login')->assertStatus(200)->assertSee('Sign in')->assertSee('name="_token"');
    }

    public function testAdminCanLogInAndOut(): void
    {
        $this->loginAsAdmin()->assertRedirect('/');
        $this->get('/')->assertStatus(200)->assertSee('Welcome back, Admin!')->assertSee('Revenue');

        // Logged-in users are sent away from the login page
        $this->get('/login')->assertRedirect('/');

        $this->submit('/', '/logout')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function testRedirectsToIntendedPageAfterLogin(): void
    {
        $this->get('/orders')->assertRedirect('/login');
        $this->loginAsAdmin()->assertRedirect('/orders');
    }

    public function testWrongPasswordAndUnknownEmailShowTheSameError(): void
    {
        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertRedirect('/login');
        $wrongPassword = $this->get('/login')->assertSee('These credentials do not match our records.');

        $this->submit('/login', '/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertRedirect('/login');
        $this->get('/login')->assertSee('These credentials do not match our records.')->assertSee('nobody@example.com');

        $this->get('/')->assertRedirect('/login');
    }

    public function testCustomersCannotSignIn(): void
    {
        $this->submit('/login', '/login', ['email' => 'rahim1@example.com', 'password' => 'password'])->assertRedirect('/login');
        $this->get('/login')->assertSee('Only administrators can sign in');
        $this->get('/')->assertRedirect('/login');
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
        // Use a dedicated account so other tests are not locked
        $this->db('users')->insert([
            'name' => 'Lock Test', 'email' => 'locktest@example.com', 'role' => 'admin',
            'password' => \System\Security\Hash::make('password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->submit('/login', '/login', ['email' => 'locktest@example.com', 'password' => "guess$i"]);
        }

        // Even the right password is refused while locked
        $this->submit('/login', '/login', ['email' => 'locktest@example.com', 'password' => 'password'])->assertRedirect('/login');
        $this->get('/login')->assertSee('Too many login attempts');
        $this->get('/')->assertRedirect('/login');
    }

    public function testSessionIdChangesOnLogin(): void
    {
        $this->get('/login');
        $before = $this->cookies['bongo_session'] ?? null;
        $this->loginAsAdmin();
        $this->assertNotSame($before, $this->cookies['bongo_session'] ?? null, 'Session id must be regenerated on login');
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

    public function testCsrfFailureIsLoggedAsWarning(): void
    {
        $this->get('/login');
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'x'])->assertStatus(419);

        $log = file_get_contents(BASE_PATH . '/storage/logs/testing/bongo-' . date('Y-m-d') . '.log');
        $this->assertContains('WARNING: HTTP 419 on POST /login', $log);
    }
}
