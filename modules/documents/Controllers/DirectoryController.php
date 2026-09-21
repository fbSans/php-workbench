<?php

namespace Documents\Controllers;

use Base\Request;
use Base\Response;
use Base\Session;
use Base\Storage;
use Documents\Models\Directory;
use Documents\Models\File;

use function Helpers\auth_user;
use function Helpers\clean_input;
use function Helpers\join_paths;

class DirectoryController
{
    public function save(Request $request)
    {
        Session::set('parent_id', $request->input('parent_id', null));

        if (!$request->has('directory')) {
            Session::set('errors', ['message' => 'Directory name was not provided for creation']);
            return Response::redirect('/documents');
        }

        $files = File::with('directory')->get([
            'name' => clean_input($request->input('directory')),
            'parent_id' => $request->input('parent_id'),
        ]);
        $matching_simblings = array_filter($files, function (File $file) {
            return !is_null($file->directory);
        });

        if (count($matching_simblings) > 0) {
            Session::set('errors', [
                'message' => 'This name is being used by another directory.'
            ]);

            return Response::redirect('/documents');
        }

        //TODO: move this into Storage::class
        $trials = 5;
        $storage_name = '';
        while ($trials-- > 0) {
            $storage_name = bin2hex(random_bytes(32));

            $target_file = join_paths(
                'documents',
                $storage_name
            );

            if (!Storage::exists($target_file)) {
                break;
            }
            $storage_name = '';
        }

        if ($storage_name === '') {
            Session::set('errors', ['message' => 'System failed to create folder']);
            return Response::redirect('/documents');
        }

        $metadata = [
            'name' => $request->input('directory'),
            'size' => 0,
            'storage_name' => $storage_name,
        ];
     
        $make_submodel = function (File $file) use ($metadata) {
            $directory = new Directory([
                'id' => $file->id,
            ]);

            return $directory->create();
        };

        $filecontroller = new FileController();
        return $filecontroller->save(
            $request,
            $metadata,
            $make_submodel,
        );
    }
}
