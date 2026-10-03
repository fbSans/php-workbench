<?php
namespace Users\Controllers;

use Base\Request;
use Base\Response;
use Base\Session;
use Users\Models\User;

use function Helpers\view;

class ProfileController {
    public function index(Request $request){
        $csrf_token = Session::get('csrf_token');
        $user = User::find(Session::get('user_id'));

        $error_message = '';
        $success_message = '';

        if(Session::has('errors')){
            $error_message = Session::get('errors')['message'];
        }
        
        if(Session::has('data')){
            $success_message = Session::get('data')['message'];
        }

        Session::unset('errors');
        Session::unset('data');

        return view('profile/index.html.twig', compact('csrf_token', 'user', 'success_message', 'error_message'));
    }

    public function changePassword(Request $request){
        $user = User::find(Session::get('user_id'));
        
        if(!$request->input('current_password')) {
            Session::set('errors', [
                    'message' => 'Current password was not provided.',                    
            ]);
            return Response::redirect('/profile');
        }

        if(!$request->input('new_password')) {
            Session::set('errors', [
                    'message' => 'New password was not provided.',                    
            ]);
            return Response::redirect('/profile');
        }

        if(!$request->input('new_password_confirmation')) {
            Session::set('errors', [
                    'message' => 'New password confirmation was not provided.',                    
            ]);
            return Response::redirect('/profile');
        }

        if($request->input('new_password') !== $request->input('new_password_confirmation')) {
            Session::set('errors', [
                    'message' => '`New Password` and `New Password Confirmation` do not match.',                    
            ]);
            return Response::redirect('/profile');
        }

        if(!password_verify($request->input('current_password'), $user->password)){
            Session::set('errors', [
                    'message' => 'Provided current password does not match.',                    
            ]);
            return Response::redirect('/profile');
        }

        $user->update(['password' => password_hash($request->input('new_password'), PASSWORD_DEFAULT)]);

        Session::set('data', [
            'message' => 'Password changed sucessfully.',                    
        ]);

        return Response::redirect('/profile');
    }
}