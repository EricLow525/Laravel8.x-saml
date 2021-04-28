<?php

namespace App\Providers;

use Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Aacotroneo\Saml2\Events\Saml2LoginEvent;
use App\Models\User;

class SAML2ServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen('Aacotroneo\Saml2\Events\Saml2LoginEvent', function (Saml2LoginEvent $event) {
            $messageId = $event->getSaml2Auth()->getLastMessageId();
            // Add your own code preventing reuse of a $messageId to stop replay attacks

            $user = $event->getSaml2User();
            $userData = [
                'id' => $user->getUserId(),
                'attributes' => $user->getAttributes(),
                'assertion' => $user->getRawSamlAssertion()
            ];

            $inputs = [
                'sso_user_id'  => $user->getUserId(),
                'username'     => $user->getAttribute('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name')[0],
                'email'        => $user->getAttribute('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress')[0],
                'name'         => $user->getAttribute('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname')[0] . ' ' . $user->getAttribute('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname')[0],
                'password'     => Hash::make('anything'),
            ];

            $user = User::where('sso_user_id', $inputs['sso_user_id'])->where('email', $inputs['email'])->first();
            if(!$user) {
                $res = User::store($inputs);
                if($res['status'] == 'success'){
                    $user  = $res['data'];
                    Auth::guard('web')->login($user);
                } else {
                    Log::info('SAML USER Error '.$res['messages']);
                }
            } else {
                Auth::guard('web')->login($user);
            }

        });
    }
}
