<?php

namespace Documents\Controllers;

use Config\Config;
use function Helpers\join_paths;
use function Helpers\view;

use Base\Request;
use Base\Response;
use Base\View;
use Documents\Models\Document;

class DocumentController
{
    public function index(Request $request){
        $page = $request->input('page', 1);
        $documents = Document::fetchPage($page, 20);

        return view('documents/index.html.twig', compact('documents'));
    }

    public function uploadFile(
        string $storage_subfolder = 'tmp',
        string $field = 'file',
        ?int $max_size = null
    ): array {
        $max_size ??= Config::get('UPLOAD_MAX_SIZE');

        /*
         * Prevent directory traversal through the storage subfolder.
         */
        if (
            str_contains($storage_subfolder, '..') ||
            str_contains($storage_subfolder, "\0") ||
            str_starts_with($storage_subfolder, '/')
        ) {
            return [
                'errors' => [
                    'message' => 'Invalid storage subfolder.'
                ]
            ];
        }

        $storage_dir = Config::get('STORAGE_DIR');
        $upload_dir = join_paths($storage_dir, $storage_subfolder);

        if (!isset($_FILES[$field])) {
            return [
                'errors' => [
                    $field => 'Not uploaded'
                ]
            ];
        }

        $file = $_FILES[$field];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'errors' => [
                    $field => 'File upload failed.'
                ]
            ];
        }

        if ($file['size'] > $max_size) {
            return [
                'errors' => [
                    'message' => "Maximum file size exceeded. Maximum allowed size is $max_size bytes.",
                    'size' => $file['size'],
                    'max-size' => $max_size,
                ]
            ];
        }

        /*
         * Prevent directory traversal through the uploaded filename.
         */
        $filename = basename($file['name']);

        if (
            $filename === '' ||
            $filename === '.' ||
            $filename === '..' ||
            str_contains($filename, "\0")
        ) {
            return [
                'errors' => [
                    'message' => 'Invalid filename.'
                ]
            ];
        }

        $target_file = join_paths($upload_dir, $filename);

        if (file_exists($target_file)) {
            return [
                'errors' => [
                    'message' => 'Could not upload file because the file already exists.'
                ]
            ];
        }

        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, Config::get('STORAGE_DIR_PERMISSIONS'), true)) {
                return [
                    'errors' => [
                        'message' => 'Failed to create storage directory.'
                    ]
                ];
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            return [
                'errors' => [
                    'message' => 'Failed to save uploaded file'
                ]
            ];
        }

        return [
            'ok' => 'File saved.',
            'filename' => $filename,
            'mime_type' => $file['type'],
            'dirname' => $upload_dir,
            'size' => $file['size'],
        ];
    }
}