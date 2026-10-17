<?php

namespace Documents\Controllers;

use Base\Controller;

use function Helpers\join_paths;
use function Helpers\view;

use Base\Request;
use Base\Response;
use Base\Session;
use Base\Storage;
use Documents\Models\Document;
use Documents\Models\File;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 1);

        $parent_id = $request->input('parent_id') ?? Session::get('parent_id');

        $files = File::fetchPage($page, 20, ['parent_id' => $parent_id]);
        foreach ($files['items'] as $file) {
            /** @var File $file */
            $file->update(['accessed_at' => date('Y-m-d H:i:s')]);
        }

        $files['items'] = File::load(
            $files['items'],
            ['document', 'directory'],
        );

        $error_message = '';
        $success_message = '';

        if (
            Session::has('errors') &&
            isset(Session::get('errors')['message'])
        ) {
            $error_message = Session::get('errors')['message'];
        }

        if (
            Session::has('data') &&
            isset(Session::get('data')['message'])
        ) {
            $success_message = Session::get('data')['message'];
        }

        Session::unset('errors');
        Session::unset('data');

        $csrf_token = Session::get('csrf_token');

        return view(
            'documents/index.html.twig',
            compact(
                'csrf_token',
                'files',
                'error_message',
                'success_message',
                'parent_id',
            )
        );
    }

    public function save(Request $request): Response
    {
        $metadata = Storage::uploadFile('documents/', 'file');

        if (isset($metadata['errors'])) {
            Session::set('errors', $metadata['errors']);
            Session::set('parent_id', $request->input('parent_id', null));
            return Response::redirect('/documents');
        }

        $unwind = function () use ($metadata) {
            $filepath = join_paths($metadata['dirname'], $metadata['name']);
            return Storage::unlinkFile($filepath);
        };

        $make_submodel = function (File $file) use ($metadata) {
            $document = new Document([
                'id' => $file->id,
                'mime_type' => $metadata['mime_type'],
            ]);

            return $document->create();
        };

        $filecontroller = new FileController();
        return $filecontroller->save(
            $request,
            $metadata,
            $make_submodel,
            $unwind
        );
    }

    public function download(int $id)
    {
        $document = Document::find($id);
       
        if(!$document || !$document->file) {
            Session::set('errors', [
                'message' => 'Could not find the requested document.',
            ]);
            return Response::redirect('/documents');
        }

        $file = $document->file;
        $mime_type = $document->mime_type;
        $size = $file->size;
        $storage_name = $file->storage_name;
        $name = $file->name;


        Storage::download($storage_name, 'documents', $name, $mime_type, $size);
        exit;
    }

    public function open(int $id)
    {
        $document = Document::find($id);
       
        if(!$document || !$document->file) {
            Session::set('errors', [
                'message' => 'Could not find the requested document.',
            ]);
            return Response::redirect('/documents');
        }

        $file = $document->file;
        $mime_type = $document->mime_type;
        $size = $file->size;
        $storage_name = $file->storage_name;
        $name = $file->name;


        Storage::open($storage_name, 'documents', $name, $mime_type, $size);
        exit;
    }
}
