<?php
namespace Users\Controllers;

use Base\View;
use Base\Request;
use Base\Response;
use Routes\Route;
use Users\Models\User;

use function Helpers\clean_input;
use function Helpers\escape_html;
use function Helpers\valid_email;
use function Helpers\view;

class UserController {
    public function login_page()
    {
        $context = [];
        if(isset($_SESSION['errors'])) {
            $context['errors'] = $_SESSION['errors'];
            unset($_SESSION['errors']);
        }
        return view("authentication/login.html.twig", $context);
    }

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
            $user = User::fetchPage($page, 1, ['email'=> $email])['items'][0];
            
            if(!$user){
                $context['errors']['message'] = 'Invalid email or password';
            }
                
                
            $verified = true;
           
            
            if(!password_verify($request->input('password'), $user->password)) {
                $context['errors']['message'] = 'Invalid email or password';
                $verified = false;
            } 
        }

        if($verified) {
            assert(isset($user));
            $_SESSION['user_id'] = $user->id;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            return Response::redirect('/');
        } else {
            $_SESSION['errors'] = $context['errors'];
            return Response::redirect('/login');
        }
    }

    public function logout()
    {
        session_destroy();
        return Response::redirect('/login');
    }
}