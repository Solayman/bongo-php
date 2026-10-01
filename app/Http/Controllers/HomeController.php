<?php
namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function welcome()
    {
        echo $this->view('welcome');
    }

    public function home()
    {
        echo $this->view('home');
    }
}
