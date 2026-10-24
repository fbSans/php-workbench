<?php
namespace Users\Controllers;

use Base\Request;
use Base\Response;
use Base\Session;
use Users\Models\User;

use function Helpers\clean_input;
use function Helpers\escape_html;
use function Helpers\valid_email;
use function Helpers\view;

class UserController {
    public function login_page()
    {
        $context = [];
        if(Session::has('errors')) {
            $context['errors'] = Session::get('errors');
            Session::unset('errors');
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
            $user = User::first(['email'=> $email]);
            if(!$user){
                $context['errors']['message'] = 'Invalid email or password';
            }
                
    
            $verified = true;

            if($user && !password_verify($request->input('password'), $user->password)) {
                $context['errors']['message'] = 'Invalid email or password';
                $verified = false;
            } 
        }

        if($user && $verified) {
            assert(isset($user));
            Session::set('user_id', $user->id);
            Session::set('csrf_token', bin2hex(random_bytes(32)));
            return Response::redirect('/');
        } else {
            Session::set('errors', $context['errors']);
            return Response::redirect('/login');
        }
    }

    public function logout()
    {
        Session::destroy();
        return Response::redirect('/login');
    }
}