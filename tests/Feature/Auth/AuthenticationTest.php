<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ログイン画面を表示できる(): void
    {
        $this->get('/login')->assertOk();
    }

    /** @test */
    public function 会員登録画面を表示できる(): void
    {
        $this->get('/register')->assertOk();
    }

    /** @test */
    public function 会員登録に成功するとログイン状態で一覧へ遷移する(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'taro@example.com', 'name' => '山田太郎']);
        $this->assertNotSame('password123', User::first()->password);
    }

    /** @test */
    public function 会員登録の入力不備は日本語のエラーになる(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'invalid',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'name' => 'お名前は必須です。',
            'email' => 'メールアドレスは有効なメールアドレス形式で入力してください。',
            'password' => 'パスワードは8文字以上で入力してください。',
        ]);
        $this->assertGuest();
    }

    /** @test */
    public function パスワード不一致は日本語のエラーになる(): void
    {
        $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ])->assertSessionHasErrors(['password' => 'パスワードが確認用の入力と一致しません。']);
    }

    /** @test */
    public function 登録済みのメールアドレスでは会員登録できない(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email' => '入力されたメールアドレスは既に使用されています。']);

        $this->assertSame(1, User::count());
    }

    /** @test */
    public function 会員登録時に入力したパスワードは入力値として保持されない(): void
    {
        $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'invalid',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->assertSame('山田太郎', session()->getOldInput('name'));
    }

    /** @test */
    public function ログインに成功すると一覧へ遷移する(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function 未認証で保護ページに来てログインすると元のページへ戻る(): void
    {
        Route::middleware(['web', 'auth'])->get('/protected', fn () => 'ok');
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->get('/protected')->assertRedirect('/login');
        $this->assertSame('http://localhost/protected', session('url.intended'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect('http://localhost/protected');
    }

    /** @test */
    public function パスワードが誤っているとログインできず日本語のエラーになる(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);

        $this->assertGuest();
    }

    /** @test */
    public function ログインの必須項目が未入力だと日本語のエラーになる(): void
    {
        $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => 'メールアドレスは必須です。',
                'password' => 'パスワードは必須です。',
            ]);
    }

    /** @test */
    public function ログアウトするとログイン画面へ遷移する(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    /** @test */
    public function ログイン済みでログイン画面と会員登録画面に来ると一覧へ遷移する(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect('/');
        $this->get('/register')->assertRedirect('/');
    }

    /** @test */
    public function 無効化した機能のルートは存在しない(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/two-factor-challenge')->assertNotFound();
        $this->get('/email/verify')->assertNotFound();
    }
}
