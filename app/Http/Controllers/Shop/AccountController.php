<?php
namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use System\Auth\Auth;
use System\Auth\LoginThrottle;
use System\Http\HttpException;
use System\Http\Request;
use System\Http\Session;
use System\Security\Hash;

/**
 * Customer accounts on the storefront: register, sign in/out (the "customer" guard), own orders.
 */
class AccountController extends Controller
{
    public function showLogin()
    {
        return $this->view('shop.auth.login');
    }

    public function login()
    {
        $data = $this->validate(['email' => 'required|email|max:150', 'password' => 'required|string|max:255']);
        $email = strtolower($data['email']);
        $throttle = new LoginThrottle();

        if ($throttle->tooManyAttempts($email, Request::ip())) {
            $this->fail(__('Too many login attempts. Try again in :minutes minute(s).', ['minutes' => (int) ceil($throttle->availableIn($email, Request::ip()) / 60)]), $email);
        }
        if (!Auth::guard('customer')->attempt($email, $data['password'])) {
            $throttle->hit($email, Request::ip());
            $this->fail(__('These credentials do not match our records.'), $email);
        }
        $throttle->clear($email, Request::ip());

        $intended = Session::get('url.intended', '/shop');
        Session::forget('url.intended');
        flash('success', __('Welcome back, :name!', ['name' => Auth::guard('customer')->user()->name]));
        redirect(url(str_starts_with($intended, '/shop') ? $intended : '/shop'));
    }

    public function showRegister()
    {
        return $this->view('shop.auth.register');
    }

    public function register()
    {
        $data = $this->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'role'     => 'customer',   // never from the form
        ]);

        Auth::guard('customer')->login($user);
        flash('success', __('Welcome, :name! Your account is ready.', ['name' => $user->name]));
        redirect(route('shop.index'));
    }

    public function logout()
    {
        Auth::guard('customer')->logout();
        flash('success', __('You have been signed out.'));
        redirect(route('shop.index'));
    }

    /** GET /shop/orders */
    public function orders()
    {
        return $this->view('shop.orders', [
            'orders' => Order::where('user_id', Auth::guard('customer')->id())->latest()->get(),
        ]);
    }

    /** GET /shop/orders/{order}: only the customer's own orders */
    public function order(Order $order)
    {
        if ((int) $order->user_id !== Auth::guard('customer')->id()) {
            throw new HttpException(404, 'Order not found'); // 404, not 403: do not reveal other people's orders exist
        }
        return $this->view('shop.order', ['order' => $order, 'items' => $order->items]);
    }

    protected function fail(string $message, string $email): void
    {
        Session::flash('_errors', ['email' => $message]);
        Session::flash('_old', ['email' => $email]);
        redirect(route('shop.login'));
    }
}
