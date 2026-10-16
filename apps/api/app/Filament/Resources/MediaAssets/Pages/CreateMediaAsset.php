<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\Actions\StoreDocumentUpload;
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

    protected static ?string $title = 'Upload image or PDF';

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
        $actor = $actor instanceof User ? $actor : null;
        $isDocument = array_key_exists((string) $file->getMimeType(), StoreDocumentUpload::ALLOWED);

        if (! $isDocument && blank($data['alt_text'] ?? null)) {
            throw ValidationException::withMessages(['data.alt_text' => 'Describe the image for people who cannot see it.']);
        }

        if ($isDocument && blank($data['title'] ?? null)) {
            throw ValidationException::withMessages(['data.title' => 'Give the document a title; visitors see it on the download button.']);
        }

        try {
            return $isDocument
                ? app(StoreDocumentUpload::class)->handle($file, $actor, [
                    'title' => $data['title'] ?? null,
                    'description' => $data['description'] ?? null,
                ])
                : app(StoreMediaUpload::class)->handle($file, $actor, [
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
