<?php

use App\Domain\Identity\Enums\Role;
use Filament\Facades\Filament;

/*
| Opens every admin list screen and custom page as a Super Administrator. Catches screens that
| crash on load (for example a table callback that receives the wrong argument), which unit-level
| tests of a screen's actions can miss.
*/

it('opens every admin screen without errors', function () {
    $this->actingAs(contentStaff(Role::SuperAdmin));
    $panel = Filament::getDefaultPanel();
    $failures = [];

    $resources = $panel->getResources();
    foreach ($resources as $resource) {
        $url = $resource::getUrl('index');
        $status = $this->get($url)->getStatusCode();
        if ($status !== 200) {
            $failures[] = "{$url} → {$status}";
        }

        if (array_key_exists('create', $resource::getPages())) {
            $create = $resource::getUrl('create');
            $status = $this->get($create)->getStatusCode();
            if ($status !== 200) {
                $failures[] = "{$create} → {$status}";
            }
        }
    }

    foreach ($panel->getPages() as $page) {
        $url = $page::getUrl();
        $status = $this->get($url)->getStatusCode();
        if ($status !== 200) {
            $failures[] = "{$url} → {$status}";
        }
    }

    expect($failures)->toBe([]);
    expect(count($resources))->toBeGreaterThan(10);
});
