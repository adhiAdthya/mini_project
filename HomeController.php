<?php
class HomeController extends Controller {
    public function index()
    {
        $user = $_SESSION['user'] ?? null;
        return $this->view('home.index', compact('user'));
    }
}
