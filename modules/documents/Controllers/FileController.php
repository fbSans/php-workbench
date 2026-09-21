<?php

namespace Documents\Controllers;

use Base\Request;
use Base\Response;
use Base\Session;
use Documents\Models\Directory;
use Documents\Models\File;
use Users\Models\GroupUser;

use function Helpers\auth_user;

class FileController
{
    public function save(Request $request, array $metadata, ?callable $make_submodel = null, ?callable $unwind = null): Response
    {
        $parent_id = $request->input('parent_id', null);
        $parent_group_id = null;
        if($parent_id) {
            $parent = Directory::find($parent_id);
            if($parent === null) {
                Session::set('errors', [
                    'message' => 'The provided parent folder does not exist.',                    
                ]);

                if($unwind) $unwind();
                return Response::redirect('/documents');
            } else {
                //With mechanism is needed
                $parent_file = File::find($parent_id);
                $parent_group_id = $parent_file->group_id;
            }

        }

        Session::set('parent_id', $parent_id);

        $user = auth_user();
        if ($user === null) {
            Response::text('Not authorized', 400); //TODO: check the status for not authorized people.
        }

        // if (!$request->has('directory') && !$request->has('file')) {
        //     Session::set('errors', [
        //         'message' => 'Missing directory or document name',                
        //     ]);
        //     if($unwind) $unwind();
        //     return Response::redirect('/documents');
        // }

        if ($request->has('group_id') && $request->input('group_id') !== null) {
            $user_group = GroupUser::find(['user_id' => $user->id, 'group_id' => $request->input('group_id')]);

            if (!$user_group) {
                Session::set('errors', [
                    'message' => 'Group is missing or user does not have permission to access it.',                    
                ]);

                if($unwind) $unwind();
                return Response::redirect('/documents');
            }
            
            $group_id = $user_group->group_id;
        } else {
            $group_id = $parent_group_id ?? $user->group()->id;
        }


        // $name = $request->input('directory') ?? $request->input('file');

        $file = new File([
            'name' => $metadata['name'],
            'parent_id' => $parent_id,
            'owner_id' => $user->id,
            'group_id' => $group_id,
            'size' => $metadata['size'],
            'storage_name' => $metadata['storage_name'],
        ]);

        if($file->create()) {
            if($make_submodel && !$make_submodel($file)){
                if($unwind) $unwind();
                $file->delete();

                Session::set('errors', [
                    'message' => 'System failed to create file or document.',                    
                ]);

                return Response::redirect('/documents');
            }
        } else {
            if($unwind) $unwind();
            return Response::redirect('/documents');
        }

        Session::set('data', [
                'message' => 'File saved successfully',
        ]);
        return Response::redirect('/documents');
    }
}
