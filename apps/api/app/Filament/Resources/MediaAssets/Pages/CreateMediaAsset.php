<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\Actions\StoreMediaUpload;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected static ?string $title = 'Upload image';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $file = $data['file'] ?? null;

        if (is_array($file)) {
            $file = reset($file);
        }

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['data.file' => 'Please choose an image to upload.']);
        }

        $actor = Auth::user();

        try {
            return app(StoreMediaUpload::class)->handle($file, $actor instanceof User ? $actor : null, [
                'title' => $data['title'] ?? null,
                'alt_text' => $data['alt_text'] ?? null,
                'description' => $data['description'] ?? null,
            ]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.file' => $e->errors()['file'] ?? $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
