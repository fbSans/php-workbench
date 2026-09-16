<?php
namespace Users\Controllers;

use Base\View;
use Base\Request;
use Users\Models\User;

use function Helpers\clean_input;
use function Helpers\escape_html;
use function Helpers\valid_email;

class UserController {
    public function login_page()
    {
        $context = [];
        if(isset($_SESSION['errors'])) {
            $context['errors'] = $_SESSION['errors'];
            unset($_SESSION['errors']);
        }
        return View::render("authentication/login.html.twig", $context);
    }

    //TODO: implement redirect
    public function login(Request $request){
        $context = [];
        $context['errors'] = [];
        $email = $request->input('email');
        $email = clean_input($email);
        $verified = false;
        if(!valid_email($email)) {
            $context['errors']['message'] = 'Invalid format for email. got "' . escape_html($email) . '"';
        } else {
            $page = 1;
            $user = User::fetchPage($page, 1, ['email'=> $email])[0];
            if(!$user){
                $context['errors']['message'] = 'Invalid email or password';
            }

            $verified = true;
            if(!$user->password !== ''){
                if(!password_verify($request->input('password'), $user->pasword)) {
                    $context['errors']['message'] = 'Invalid email or password';
                    $verified = false;
                }
            } 
        }

        if($verified) {
            assert(isset($user));
            $_SESSION['user_id'] = $user->id;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            //TODO: redirect is the logic I want here.
            header('Location: /');
            exit();
        } else {
            $_SESSION['errors'] = $context['errors'];
            header('Location: /login');
            exit();
        }
    }
}