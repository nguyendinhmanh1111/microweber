<?php

namespace MicroweberPackages\User\tests;

use function _HumbugBox58fd4d9e2a25\KevinGH\Box\unique_id;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\MailFake;
use MicroweberPackages\Core\tests\TestCase;
use MicroweberPackages\Notification\Channels\AppMailChannel;
use MicroweberPackages\Notification\Mail\SimpleHtmlEmail;
use MicroweberPackages\User\Events\UserWasRegistered;
use MicroweberPackages\User\Models\User;
use MicroweberPackages\User\Notifications\NewRegistration;
use MicroweberPackages\User\Notifications\VerifyEmail;
use MicroweberPackages\User\tests\UserTestHelperTrait;
use MicroweberPackages\User\UserManager;
use MicroweberPackages\Utils\Mail\MailSender;

/**
 * Run test
 * @author Bobi Slaveykvo Microweber
 * @command php phpunit.phar --filter UserTest
 */
class UserManagerTest extends TestCase
{
    use UserTestHelperTrait;

    public function testRegistration()
    {
        $this->_disableCaptcha();
        $this->_enableUserRegistration();
        $this->_disableRegistrationApprovalByAdmin();

        $randomInt = rand(1111, 9999);
        $password = md5($randomInt);

        // Test simple user registration
        $newUser = array();
        $newUser['username'] = 'bobi_' . $randomInt;
        $newUser['email'] = $newUser['username'] . '@microweber.com';
        $newUser['password'] = $password;
        $newUser['password_confirm'] = $password;

        $userManager = new UserManager();
        $registerStatus = $userManager->register($newUser);

        $this->assertArrayHasKey('success', $registerStatus);

        self::$_username = $newUser['username'];
        self::$_password = $newUser['password'];
        self::$_email = $newUser['email'];

    }

    /*
        public function testEmailWrongRegistration()
        {
            $data['option_value'] = 'y';
            $data['option_key'] = 'captcha_disabled';
            $data['option_group'] = 'users';
            $save = save_option($data);

            $randomInt = rand(1111, 9999);
            $password = md5($randomInt);

            // Test wrong email user registration
            $newUser = array();
            // $newUser['username'] = 'bobi_'.$randomInt;
            $newUser['email'] = 'wrong-email';
            $newUser['password'] = $password;
            $newUser['password_confirm'] = $password;

            $userManager = new UserManager();
            $registerStatus = $userManager->register($newUser);

            $this->assertArrayHasKey('success', $registerStatus);
        }
    */

    public function testLogin()
    {
        $this->_disableCaptcha();
        $this->_disableRegistrationApprovalByAdmin();
        $this->_disableEmailVerify();

        $loginDetails = array();
        $loginDetails['username'] = self::$_username;
        $loginDetails['password'] = self::$_password;

        $userManager = new UserManager();
        $loginStatus = $userManager->login($loginDetails);

        $this->assertArrayHasKey('success', $loginStatus);

    }

    public function testWrongPasswordLogin()
    {
        $this->_disableCaptcha();
        $this->_disableRegistrationApprovalByAdmin();

        $loginDetails = array();
        $loginDetails['username'] = self::$_username;
        $loginDetails['password'] = 'microweber-best';

        $userManager = new UserManager();
        $loginStatus = $userManager->login($loginDetails);

        $this->assertArrayHasKey('error', $loginStatus);

    }

    public function testWrongUsernameLogin()
    {
        $this->_disableCaptcha();
        $this->_disableRegistrationApprovalByAdmin();

        $loginDetails = array();
        $loginDetails['username'] = 'microweber-some-user';
        $loginDetails['password'] = 'microweber-some-pass';

        $userManager = new UserManager();
        $loginStatus = $userManager->login($loginDetails);

        $this->assertArrayHasKey('error', $loginStatus);

    }

    public function testWrongEmailLogin()
    {
        $this->_disableCaptcha();
        $this->_disableRegistrationApprovalByAdmin();

        $loginDetails = array();
        $loginDetails['email'] = 'microweber-some-email';
        $loginDetails['password'] = 'microweber-some-pass';

        $userManager = new UserManager();
        $loginStatus = $userManager->login($loginDetails);

        $this->assertArrayHasKey('error', $loginStatus);

    }

    public function testForgotPassword()
    {
        $this->_disableCaptcha();

        $userDetails = array();
        $userDetails['username'] = self::$_username;
        $userDetails['email'] = self::$_email;

        $userManager = new UserManager();
        $requestStatus = $userManager->send_forgot_password($userDetails);
        $this->assertArrayHasKey('success', $requestStatus);

        $checkEmailContent = MailSender::$last_send['content'];

        $findPasswordResetLink = false;
        if (strpos($checkEmailContent, 'reset_password_link=') !== false) {
            $findPasswordResetLink = true;
        }
        $findUsername = false;
        if (strpos($checkEmailContent, $userDetails['username']) !== false) {
            $findUsername = true;
        }
        $findIpAddress = false;
        if (strpos($checkEmailContent, MW_USER_IP) !== false) {
            $findIpAddress = true;
        }

        $this->assertEquals(true, $findPasswordResetLink);
        $this->assertEquals(true, $findUsername);
        $this->assertEquals(true, $findIpAddress);

    }

    public function testResetPassword()
    {
        $password_reset_hash = '[like]';

        $check = mw()->user_manager->get_all('exclude_shorthand=password_reset_hash&single=true&password_reset_hash=[not_null]&password_reset_hash=' . $password_reset_hash . '&username=' . self::$_username);

        $this->assertEquals(false, $check);

    }


    public function testDisableUserRegistration()
    {
        $this->_disableUserRegistration();

        $randomInt = rand(1111, 9999);
        $password = md5($randomInt);

        // Test simple user registration
        $newUser = array();
        $newUser['username'] = 'bobi_' . $randomInt;
        $newUser['email'] = $newUser['username'] . '@microweber.com';
        $newUser['password'] = $password;
        $newUser['password_confirm'] = $password;

        $userManager = new UserManager();
        $registerStatus = $userManager->register($newUser);
        $this->assertArrayHasKey('error', $registerStatus);
    }


    public function testDisableUserRegistrationWithDisposableEmail()
    {
        $this->_disableUserRegistrationWithDisposableEmail();
        $this->_disableCaptcha();
        $this->_enableUserRegistration();
        $this->_disableRegistrationApprovalByAdmin();

        $randomInt = rand(1111, 9999);
        $password = md5($randomInt);

        // Test simple user registration
        $newUser = array();
        $newUser['username'] = 'anon' . $randomInt;
        $newUser['email'] = $newUser['username'] . '@mailinator.com';
        $newUser['password'] = $password;
        $newUser['password_confirm'] = $password;

        $userManager = new UserManager();
        $registerStatus = $userManager->register($newUser);
        $this->assertArrayHasKey('error', $registerStatus);

    }

    public function testUserApprovalRegistration()
    {

        $fakeNotify = Notification::fake();

        $this->_enableUserRegistration();
        $this->_enableRegistrationApprovalByAdmin();
        $this->_enableEmailVerify();
        $this->_enableRegisterWelcomeEmail();
        $this->_disableCaptcha();

        $randomInt = rand(1111, 9999);
        $password = md5($randomInt);

        // Test simple user registration
        $newUser = array();
        $newUser['username'] = 'bobi_' . $randomInt;
        $newUser['email'] = $newUser['username'] . '@microweber.com';
        $newUser['password'] = $password;
        $newUser['password_confirm'] = $password;

        $userManager = new UserManager();
        $registerStatus = $userManager->register($newUser);

        $this->assertArrayHasKey('success', $registerStatus);


        $loginDetails = array();
        $loginDetails['username'] = $newUser['username'];
        $loginDetails['password'] = $newUser['password'];

        $userManager = new UserManager();
        $loginStatus = $userManager->login($loginDetails);

        $this->assertArrayHasKey('error', $loginStatus);
        $this->assertContains('verify', $loginStatus['error']);

        $user = User::find($registerStatus['id']);

        $this->assertEquals('0', $user->is_active);
        $this->assertEquals('0', $user->is_admin);
        $this->assertEquals('0', $user->is_verified);

        $fakeNotify->assertSentTo([$user], NewRegistration::class);
        $fakeNotify->assertSentTo([$user], VerifyEmail::class);
    }

    public function testUserRegistrationWithXSS()
    {
        $this->_enableUserRegistration();
        $this->_disableRegistrationApprovalByAdmin();
        $this->_enableRegisterWelcomeEmail();
        $this->_disableCaptcha();

        $unamnexss = '<a href="Boom"><font color=a"onmouseover=alert(document.cookie);"> XSxxxS-Try ME</span></font>' . uniqid();
        $registerStatus = '';
        $newUser = array();
        $newUser['username'] = $unamnexss;
        $newUser['email'] = uniqid() . '@mail.test';
        $newUser['password'] = uniqid();


        $userManager = new UserManager();
        $registerStatus = $userManager->register($newUser);


        $this->assertTrue($registerStatus['error']);
        $this->assertArrayHasKey('errors', $registerStatus);
        $this->assertArrayHasKey('username', $registerStatus['errors']);


    }


    public function testUserRegistrationForgotPasswordEmail()
    {
        $this->_enableUserRegistration();
        $this->_disableRegistrationApprovalByAdmin();
        $this->_enableRegisterWelcomeEmail();
        $this->_disableCaptcha();

        $newUser = array();
        $newUser['username'] = 'xxx'.uniqid();
        $newUser['email'] = uniqid() . '@mail.test';
        $newUser['password'] = uniqid();


        $userManager = new UserManager();
        $registerStatus = $userManager->register($newUser);





        var_dump($registerStatus);
        die();

    }


}
