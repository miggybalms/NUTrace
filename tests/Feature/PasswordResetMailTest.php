<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The forgotten-password email is the one step nobody can complete on their
 * own: if it silently fails, the account is simply locked out. Two promises
 * hold here:
 *
 *   1. the code that arrives in the inbox is the code that was stored, so the
 *      next screen accepts it (single use, 15 minutes),
 *   2. when the mail transport refuses the message the page says so — it does
 *      not show "we sent it" over an empty inbox. That false success is what
 *      hid the live site's missing mail configuration: with no transport
 *      variables set, the code was written to Laravel's log and the screen
 *      still claimed it had been emailed.
 *
 * The live site (Railway, Hobby) cannot use SMTP at all — outbound SMTP is
 * blocked on that plan — so production sends through Resend's HTTPS API.
 */
class PasswordResetMailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = User::create([
            'employee_numbers_id' => 1,
            'department_id' => 1,
            'email' => 'nena@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'),
            'role' => 'Employee',
            'status' => 'Active',
        ]);
    }

    /** The six digits carried by the last message the array mailer captured. */
    private function emailedCode(): string
    {
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $body = $messages->last()->getOriginalMessage()->getTextBody();

        $this->assertMatchesRegularExpression('/verification code is: \d{6}/', $body);

        preg_match('/(\d{6})/', $body, $matches);

        return $matches[1];
    }

    public function test_the_emailed_code_is_the_stored_code_and_the_page_confirms_it_was_sent(): void
    {
        $response = $this->post('/forgot-password', ['email' => $this->user->email]);

        $response->assertRedirect('/forgot-password/verify');
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('mail_error');

        $success = session('success');
        $this->assertNotNull($success, 'a delivered code is announced on the next screen');
        $this->assertStringContainsString('has been sent to ' . $this->user->email, $success);

        $reset = DB::table('password_resets')->where('email', $this->user->email)->first();
        $this->assertNotNull($reset, 'the code is stored so the next screen can check it');
        $this->assertSame(0, (int) $reset->used, 'a fresh code has not been used yet');
        $this->assertTrue(Carbon::parse($reset->expires_at)->isFuture());
        $this->assertStringStartsWith('$2y$', $reset->token, 'the stored value is the hash, never the 6 digits');

        // The code in the inbox is the code that opens the next screen.
        $this->assertTrue(
            Hash::check($this->emailedCode(), $reset->token),
            'the emailed code must be the one the database will accept'
        );

        // The verify screen therefore reads as "check your email", not as a failure.
        $this->get('/forgot-password/verify')
            ->assertOk()
            ->assertSee('Check Your Email')
            ->assertSee($this->user->email)
            ->assertDontSee('could not be emailed');
    }

    public function test_a_refused_send_is_reported_instead_of_being_called_sent(): void
    {
        Mail::shouldReceive('raw')
            ->once()
            ->andThrow(new \RuntimeException('smtp.gmail.com:587 is unreachable from this host'));

        $response = $this->post('/forgot-password', ['email' => $this->user->email]);

        $response->assertRedirect('/forgot-password/verify');
        $response->assertSessionHas('mail_error');
        $response->assertSessionMissing('success');

        // The 6-digit code is still on the server (the person may be able to
        // recover it another way), but nothing pretends it reached the inbox.
        $this->assertNotNull(DB::table('password_resets')->where('email', $this->user->email)->first());

        $page = $this->get('/forgot-password/verify')->assertOk();
        $page->assertSee('The Code Could Not Be Emailed')
            ->assertSee('refused the message')
            ->assertDontSee('We sent a 6-digit verification code');

        // The local-only fallback must stay local: the deployed site never
        // prints a live verification code on the page.
        $this->assertNull(session('dev_code'));
    }

    public function test_the_failure_notice_names_the_setting_the_mailer_actually_needs(): void
    {
        // The live site's mailer is the HTTPS API; the missing piece there is
        // the API key, not MAIL_HOST / MAIL_PASSWORD.
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('no api key'));
        config(['mail.default' => 'resend', 'services.resend.key' => '']);

        $this->post('/forgot-password', ['email' => $this->user->email]);

        $this->assertStringContainsString('RESEND_API_KEY', session('mail_error'));
        $this->assertStringNotContainsString('MAIL_PASSWORD', session('mail_error'));
    }

    public function test_an_unregistered_email_never_starts_a_reset(): void
    {
        Mail::shouldReceive('raw')->never();

        $this->post('/forgot-password', ['email' => 'stranger@nu-lipa.edu.ph'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, DB::table('password_resets')->count());
        $this->assertNull(session('password_reset_email'));
    }
}
