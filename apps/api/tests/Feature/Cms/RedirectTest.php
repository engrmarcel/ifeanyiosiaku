<?php

use App\Domain\Cms\Actions\DeleteRedirect;
use App\Domain\Cms\Actions\SaveRedirect;
use App\Domain\Cms\Models\Redirect;
use App\Domain\Identity\Enums\Role;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function saveRedirect(array $data, ?Redirect $redirect = null): Redirect
{
    return app(SaveRedirect::class)->handle($redirect, ['status_code' => 301, ...$data], null);
}

/** @return array<string, list<string>> */
function redirectErrors(array $data, ?Redirect $redirect = null): array
{
    try {
        saveRedirect($data, $redirect);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('creates a manual redirect that visitors follow and that counts visits', function () {
    $redirect = saveRedirect(['from_path' => 'Old-Services/', 'to_path' => '/services', 'status_code' => 302]);

    expect($redirect->from_path)->toBe('/old-services')->and($redirect->is_automatic)->toBeFalse();

    resolvePath('/old-services')->assertExactJson(['data' => ['status' => 'redirect', 'to' => '/services', 'code' => 302]]);
    resolvePath('/old-services');

    expect($redirect->fresh()->hits)->toBe(2);
});

it('allows full https addresses as destinations', function () {
    saveRedirect(['from_path' => '/linkedin', 'to_path' => 'https://www.linkedin.com/in/example']);

    resolvePath('/linkedin')->assertJsonPath('data.to', 'https://www.linkedin.com/in/example');
});

it('rejects unsafe or invalid destinations', function (string $to) {
    expect(redirectErrors(['from_path' => '/old', 'to_path' => $to]))->toHaveKey('to_path');
})->with(['javascript:alert(1)', 'http://insecure.example', '//evil.example', 'data:text/html,hi', '/back\slash', '']);

it('rejects addresses the website owns or that a published page uses', function (string $from) {
    livePage('about');

    expect(redirectErrors(['from_path' => $from, 'to_path' => '/elsewhere']))->toHaveKey('from_path');
})->with(['/', '/admin', '/api/v1/health', '/login', '/page-not-found', '/about', '/bad path', '/a//b']);

it('refuses loops and chains', function () {
    saveRedirect(['from_path' => '/a', 'to_path' => '/b']);

    expect(redirectErrors(['from_path' => '/c', 'to_path' => '/c']))->toHaveKey('to_path')
        ->and(redirectErrors(['from_path' => '/c', 'to_path' => '/a']))->toHaveKey('to_path')
        ->and(redirectErrors(['from_path' => '/a', 'to_path' => '/x']))->toHaveKey('from_path');
});

it('points earlier redirects straight at the new destination', function () {
    $first = saveRedirect(['from_path' => '/a', 'to_path' => '/b']);
    saveRedirect(['from_path' => '/b', 'to_path' => '/c']);

    expect($first->fresh()->to_path)->toBe('/c');
});

it('redirects old case-study addresses once the project is gone', function () {
    saveRedirect(['from_path' => '/projects/old-dashboard', 'to_path' => '/projects/sales-dashboard']);

    resolvePath('/projects/old-dashboard')->assertJsonPath('data.status', 'redirect');
});

it('records changes in the audit log and clears the address cache', function () {
    resolvePath('/old')->assertJsonPath('data.status', 'missing'); // cached

    $redirect = saveRedirect(['from_path' => '/old', 'to_path' => '/new']);
    resolvePath('/old')->assertJsonPath('data.status', 'redirect');

    app(DeleteRedirect::class)->handle($redirect, null);
    resolvePath('/old')->assertJsonPath('data.status', 'missing');

    $this->assertDatabaseHas('audit_logs', ['action' => 'redirect.created']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'redirect.deleted']);
});

it('lets editors manage redirects in the admin and shows errors next to the field', function () {
    $this->actingAs(contentStaff());

    Livewire::test(ManageRedirects::class)
        ->callAction('create', ['from_path' => 'flyer', 'to_path' => '/services', 'status_code' => 301])
        ->assertHasNoFormErrors();

    expect(Redirect::query()->where('from_path', '/flyer')->exists())->toBeTrue();

    Livewire::test(ManageRedirects::class)
        ->callAction('create', ['from_path' => 'flyer-2', 'to_path' => 'javascript:alert(1)', 'status_code' => 301])
        ->assertHasFormErrors(['to_path']);

    $redirect = Redirect::query()->where('from_path', '/flyer')->firstOrFail();
    Livewire::test(ManageRedirects::class)
        ->callAction(TestAction::make('edit')->table($redirect), ['from_path' => 'flyer', 'to_path' => '/about', 'status_code' => 302])
        ->assertHasNoFormErrors();

    expect($redirect->fresh()->to_path)->toBe('/about')->and($redirect->fresh()->status_code)->toBe(302);

    Livewire::test(ManageRedirects::class)
        ->callAction(TestAction::make('edit')->table($redirect), ['from_path' => 'admin', 'to_path' => '/about', 'status_code' => 301])
        ->assertHasFormErrors(['from_path']);
});

it('hides redirects from staff without content permissions', function () {
    $this->actingAs(contentStaff(Role::Moderator))->get('/admin/redirects')->assertForbidden();
});
