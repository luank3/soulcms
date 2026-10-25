<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Media\Controllers;

use SoulCMS\App\Modules\Media\Repositories\MediaRepository;
use SoulCMS\Foundation\Container;
use SoulCMS\Http\Controller;
use SoulCMS\Http\Request;
use SoulCMS\Http\Response;
use SoulCMS\Http\Session\SessionManager;
use SoulCMS\Http\UploadedFile;
use SoulCMS\Support\Lang;
use SoulCMS\Support\S3Storage;

final class MediaController extends Controller
{
    public function __construct(
        Container $container,
        private MediaRepository $media,
        private S3Storage $storage,
        private SessionManager $session,
    ) {
        parent::__construct($container);
    }

    public function library(Request $request): Response
    {
        $viewer = $this->auth->user();
        $files = $this->media->library($viewer->nick());
        foreach ($files as $index => $file) {
            $file['is_image'] = in_array(strtolower((string) ($file['filecate'] ?? '')), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);
            if ($file['is_image']) {
                $file['preview_url'] = $this->storage->presignedGetUrl(
                    (string) $this->media->objectKey($file),
                    300,
                    (string) $file['filename'],
                    (string) $file['filecate'],
                    true
                );
            } else {
                $file['preview_url'] = null;
            }
            $file['url'] = url('/media/download/' . (int) $file['id'] . '/' . rawurlencode((string) $file['filename']));
            $files[$index] = $file;
        }

        if ($this->wantsJson()) {
            return $this->json(['status' => 'success', 'data' => $files]);
        }

        return $this->html('media/library', [
            'page_title' => Lang::get('media.library.title'),
            'files' => $files,
        ]);
    }

    public function upload(Request $request): Response
    {
        if (!$this->storage->isConfigured()) {
            return $this->json(['status' => 'error', 'message' => Lang::get('media.upload.storage_unconfigured')], 503);
        }

        if (!$this->isPost() || !$this->session->validateToken(is_string($this->input('_token')) ? $this->input('_token') : null)) {
            return $this->json(['status' => 'error', 'message' => Lang::get('media.upload.invalid_request')], 403);
        }

        $allowedExtensions = (array) $this->config->get('app.uploads.allowed_extensions', []);
        $allowedMimeTypes = (array) $this->config->get('app.uploads.allowed_mime_types', []);
        $maxBytes = (int) $this->config->get('app.uploads.max_bytes', 10485760);
        $file = $request->getUploadedFiles()['document'] ?? null;
        $upload = is_array($file) ? UploadedFile::fromArray($file, $allowedExtensions, $allowedMimeTypes, $maxBytes) : null;
        if ($upload === null) {
            return $this->json(['status' => 'error', 'message' => Lang::get('media.upload.invalid_file')], 422);
        }

        $id = $this->media->insertMedia($this->auth->user()->nick(), $upload->originalName, $upload->extension, $upload->size);
        $pending = $this->media->file($id);
        $key = $pending === null ? null : $this->media->pendingObjectKey($pending);
        if ($key === null || !$this->storage->putFile($upload->temporaryPath, $key, $upload->mimeType) || !$this->media->markUploaded($id)) {
            return $this->json(['status' => 'error', 'message' => Lang::get('media.upload.save_failed')], 502);
        }

        if ($this->storage->presignedGetUrl($key, 300, $upload->originalName, $upload->extension) === null) {
            return $this->json(['status' => 'error', 'message' => Lang::get('media.upload.link_failed')], 503);
        }

        return $this->json([
            'status' => 'success',
            'id' => $id,
            'filename' => $upload->originalName,
            'filesize' => $upload->size,
            'url' => url('/media/download/' . $id . '/' . rawurlencode($upload->originalName)),
            'is_image' => str_starts_with($upload->mimeType, 'image/'),
        ], 201);
    }

    public function download(Request $request): Response
    {
        $id = (int) $this->param('id', 0);
        $filename = UploadedFile::sanitiseName(rawurldecode((string) $this->param('filename', 'file')));
        $media = $this->media->publicMedia($id);
        if ($media === null || $filename !== UploadedFile::sanitiseName((string) $media['filename'])) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        $key = $this->media->objectKey($media);
        $url = $key === null ? null : $this->storage->presignedGetUrl(
            $key,
            300,
            $filename,
            (string) $media['filecate'],
            in_array(strtolower((string) $media['filecate']), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)
        );
        if ($url === null) {
            return Response::html(Lang::get('error.404.title'), 404);
        }

        return Response::redirect($url, 302);
    }
}
