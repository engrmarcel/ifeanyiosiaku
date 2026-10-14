<?php

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Support\Facades\Route;

it('reports health without internal details to the public', function () {
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

it('shows detailed health checks to staff with permission', function () {
    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->withHeaders($this->apiHeaders())
        ->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonStructure(['status', 'checks' => ['database', 'cache'], 'queue' => ['pending_jobs', 'failed_jobs'], 'scheduler']);
});

it('serves the framework liveness probe', function () {
    $this->get('/up')->assertOk();
});

it('returns the JSON error envelope with a request ID', function () {
    $response = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found')
        ->assertJsonStructure(['error' => ['code', 'message', 'request_id']]);

    expect($response->headers->get('X-Request-Id'))->toBe($response->json('error.request_id'));
});

it('propagates a well-formed incoming request ID and replaces a malformed one', function () {
    $this->withHeaders([...$this->apiHeaders(), 'X-Request-Id' => 'web-12345678'])
        ->getJson('/api/v1/health')
        ->assertHeader('X-Request-Id', 'web-12345678');

    $response = $this->withHeaders([...$this->apiHeaders(), 'X-Request-Id' => "bad\nvalue"])->getJson('/api/v1/health');
    expect($response->headers->get('X-Request-Id'))->not->toBe("bad\nvalue")->toHaveLength(36);
});

it('sends security headers', function () {
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/health')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
});

it('marks authenticated responses as not cacheable', function () {
    $response = $this->actingAs(User::factory()->create())->withHeaders($this->apiHeaders())->getJson('/api/v1/me');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('does not reveal internals in server errors when debug is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/api/v1/_boom', fn () => throw new RuntimeException('secret detail'));

    $response = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/_boom')
        ->assertStatus(500)
        ->assertJsonPath('error.code', 'server_error');

    expect($response->getContent())->not->toContain('secret detail');
});

it('ships with the announcement popup switched off', function () {
    $data = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/settings/public')->assertOk()->json('data');

    expect($data['announcements.enabled'])->toBeFalse();
});

it('exposes only whitelisted settings publicly', function () {
    app(SettingsRepository::class)->set('security.require_staff_two_factor', false);

    $data = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/settings/public')->json('data');

    expect($data)->not->toHaveKey('security.require_staff_two_factor');
});

it('audits settings changes', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    app(SettingsRepository::class)->set('announcements.enabled', true, $admin);

    expect(app(SettingsRepository::class)->bool('announcements.enabled'))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'actor_id' => $admin->id]);
});

it('keeps audit log entries immutable', function () {
    $entry = app(AuditLogger::class)->record('test.event');

    expect(fn () => $entry->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('returns the current user without sensitive fields', function () {
    $user = User::factory()->withTwoFactor()->withProfile()->create();

    $response = $this->actingAs($user->fresh())->withHeaders($this->apiHeaders())->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->ulid)
        ->assertJsonPath('data.two_factor_enabled', true);

    expect($response->getContent())
        ->not->toContain('password')
        ->not->toContain('two_factor_secret')
        ->not->toContain('recovery');
});
