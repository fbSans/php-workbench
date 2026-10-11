<?php

namespace Base;

use Config\Config;
use RuntimeException;

use function Helpers\join_paths;

class Storage
{
    public static function exists(string $name){
        $storage_dir = Config::get('STORAGE_DIR');

        if(!file_exists($storage_dir)) return false;

        $name = str_replace('\\', '/', $name);

        if (
            $name === '' ||
            str_starts_with($name, '/') ||
            str_contains($name, "\0")
        ) {
            return false;
        }

        $parts = explode('/', $name);

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                return false;
            }
        }

        $storage_dir = realpath($storage_dir);

        if ($storage_dir === false) {
            return false;
        }

        $path = join_paths($storage_dir, $name);

        return file_exists($path);
    }

    public static function makedirIfNotExists(
        string $dirname,
        int $permissions = 0755
    ): string {
        $storage_dir = Config::get('STORAGE_DIR');

        $dirname = str_replace('\\', '/', $dirname);

        if (
            $dirname === '' ||
            str_starts_with($dirname, '/') ||
            str_contains($dirname, "\0")
        ) {
            throw new \InvalidArgumentException('Invalid directory path.');
        }

        $parts = explode('/', $dirname);

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new \InvalidArgumentException(
                    'Directory traversal is not allowed.'
                );
            }
        }

        /*
         * Guarantee that the storage directory exists.
         */
        if (!is_dir($storage_dir)) {
            if (file_exists($storage_dir)) {
                throw new \RuntimeException(
                    'Storage directory path exists but is not a directory.'
                );
            }

            if (!mkdir(
                $storage_dir,
                Config::get('STORAGE_DIR_PERMISSIONS'),
                true
            )) {
                throw new \RuntimeException(
                    'Could not create storage directory.'
                );
            }
        }

        /*
         * Resolve the storage directory to its canonical path.
         */
        $storage_dir = realpath($storage_dir);

        if ($storage_dir === false) {
            throw new \RuntimeException(
                'Could not resolve storage directory.'
            );
        }

        $path = join_paths($storage_dir, $dirname);

        if (!is_dir($path) && !mkdir($path, $permissions, true)) {
            throw new \RuntimeException('Could not create directory.');
        }

        $real_path = realpath($path);

        if ($real_path === false) {
            throw new \RuntimeException('Could not resolve directory path.');
        }

        /*
         * Ensure the resolved path remains inside STORAGE_DIR.
         */
        $storage_prefix = rtrim($storage_dir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        if (
            $real_path !== $storage_dir &&
            !str_starts_with($real_path, $storage_prefix)
        ) {
            throw new \InvalidArgumentException(
                'Directory is outside the storage directory.'
            );
        }

        return $real_path;
    }

    public static function uploadFile(
        string $storage_subfolder = 'tmp',
        string $field = 'file',
        ?int $max_size = null
    ): array {
        $max_size ??= Config::get('UPLOAD_MAX_SIZE');

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

        /*
         * Create/validate the storage directory.
         */
        try {
            $upload_dir = self::makedirIfNotExists(
                $storage_subfolder,
                Config::get('STORAGE_DIR_PERMISSIONS')
            );
        } catch (\InvalidArgumentException $e) {
            return [
                'errors' => [
                    'message' => $e->getMessage()
                ]
            ];
        } catch (\RuntimeException $e) {
            return [
                'errors' => [
                    'message' => $e->getMessage()
                ]
            ];
        }

        $trials = 5;
        $target_file = '';
        $storage_name = '';

        assert($trials > 0);

        while ($trials-- > 0) {
            $storage_name = bin2hex(random_bytes(32));

            $target_file = join_paths(
                $upload_dir,
                $storage_name
            );

            if (!file_exists($target_file)) {
                break;
            }
        }

        if (file_exists($target_file)) {
            return [
                'errors' => [
                    'message' => 'Could not generate unique name for the file in the filesystem.'
                ]
            ];
        }

        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            return [
                'errors' => [
                    'message' => 'Failed to save uploaded file.'
                ]
            ];
        }

        return [
            'ok' => 'File saved.',
            'name' => $filename,
            'storage_name' => $storage_name,
            'mime_type' => $file['type'],
            'dirname' => $upload_dir,
            'size' => $file['size'],
        ];
    }

    public static function unlinkFile(string $filepath): bool
    {
        return unlink($filepath);
    }

    public static function storagePath(string ...$parts){
        $storage_dir = Config::get('STORAGE_DIR');
        return join_paths($storage_dir, ...$parts); //Clean this properly
    }

    public static function download(
            string $starage_name,
            string $storage_subfolder = 'tmp',
            string $name = 'document',             
            string $mime_type = 'text/plain', // change to binary
            ?int $size = null,                // will get in the filesyste,
        )
    {
        $path = join_paths($storage_subfolder, $starage_name);
        if(!self::exists($path)) {
            throw new \RuntimeException("Could not find the specified file.");
        }

        $path = self::storagePath($path);

        if(!$size) {
            $size = filesize($path);
        }

        http_response_code(200);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename=' . $name);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length:' . $size);
        header('Accept-Ranges: bytes');
        
        readfile($path);
        return;
    }

    public static function open( string $starage_name,
            string $storage_subfolder = 'tmp',
            string $name = 'document',             
            string $mime_type = 'text/plain', // change to binary
            ?int $size = null,                // will get in the filesyste,
    )
    {
        $path = join_paths($storage_subfolder, $starage_name);
        if(!self::exists($path)) {
            throw new \RuntimeException("Could not find the specified file.");
        }

        $path = self::storagePath($path);

        if(!$size) {
            $size = filesize($path);
        }

        http_response_code(200);
        header('Content-Description: Open File');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename=' . $name);
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        
        readfile($path);
        return;
    }
}