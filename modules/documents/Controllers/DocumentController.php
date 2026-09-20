<?php

namespace Documents\Controllers;

use Base\Controller;
use Config\Config;
use function Helpers\join_paths;
use function Helpers\view;

use Base\Request;
use Base\Response;
use Base\Session;
use Documents\Models\Document;
use Documents\Models\File;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $documents = Document::fetchPage($page, 20);
        
        $error_message = '';
        $sucess_message = '';
        if(Session::has('errors') && isset(Session::get('errors')['message'])){
            $error_message = Session::get('errors')['message'];
        } else if (Session::has('data') && isset(Session::get('data')['message'])) {
            $sucess_message = Session::get('data')['message'];
        }

        $parent_id = Session::get('parent_id');

        return view('documents/index.html.twig', 
            compact(
                'documents',
                'error_message',
                'sucess_message',
                'parent_id'
            ));
    }

    public function save(Request $request): Response
    {
        $file_metadata = $this->uploadFile('/documents', 'file');

        if(isset($file_metadata['errors'])) {
            Session::set('errors', $file_metadata['errors']);
            Session::set('parent_id', $request->input('parent_id', null));
            return Response::redirect('/documents');
        }

        $unwind = function () use($file_metadata){
            return $this->unlinkFile($file_metadata['dirname'], $file_metadata['filename']);
        };

        $make_submodel = function (File $file) use ($file_metadata) {
            $document = new Document([
                'id' => $file->id,
                'mime_type' => $file_metadata['mime_type'],
            ]);

            return $document->create();
        };

        $filecontroller = new FileController();
        return $filecontroller->save(
                                $request,
                                $file_metadata['size'],
                                $file_metadata['storage_size'],
                                $make_submodel,
                                $unwind
                            );
    }


    public function unlinkFile(string $dirname, string $filename){
        $filepath = join_paths($dirname, $filename);
        return unlink($filepath);
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