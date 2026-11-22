<?php

function uploadPengumumanImages($fileInput, $multiple, &$error)
{
    if (!is_array($fileInput) || !isset($fileInput['error'])) {
        return [];
    }

    $files = [];

    if ($multiple) {
        if (
            !is_array($fileInput['error']) ||
            !is_array($fileInput['tmp_name']) ||
            !is_array($fileInput['size'])
        ) {
            $error = 'Data gambar tidak valid.';
            return [];
        }

        foreach ($fileInput['error'] as $index => $uploadError) {
            $files[] = [
                'error' => $uploadError,
                'tmp_name' => $fileInput['tmp_name'][$index] ?? '',
                'size' => $fileInput['size'][$index] ?? 0
            ];
        }
    } else {
        $files[] = $fileInput;
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    $toStore = [];

    foreach ($files as $file) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Gagal mengunggah gambar.';
            return [];
        }

        if ((int) $file['size'] > 3 * 1024 * 1024) {
            $error = 'Ukuran setiap gambar maksimal 3 MB.';
            return [];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!isset($allowedTypes[$mime])) {
            $error = 'Format gambar harus JPG, PNG, atau WEBP.';
            return [];
        }

        $toStore[] = [
            'tmp_name' => $file['tmp_name'],
            'extension' => $allowedTypes[$mime]
        ];
    }

    if (!$toStore) {
        return [];
    }

    $uploadDirectory = __DIR__ . '/../uploads/pengumuman/';

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
        $error = 'Folder penyimpanan gambar gagal dibuat.';
        return [];
    }

    if (!is_writable($uploadDirectory)) {
        $error = 'Folder penyimpanan gambar tidak dapat ditulis.';
        return [];
    }

    $storedFiles = [];

    foreach ($toStore as $file) {
        $filename = bin2hex(random_bytes(16)) . '.' . $file['extension'];

        if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . $filename)) {
            hapusFileGambarPengumuman($storedFiles);
            $error = 'Gambar gagal disimpan.';
            return [];
        }

        $storedFiles[] = $filename;
    }

    return $storedFiles;
}

function hapusFileGambarPengumuman($filenames)
{
    $uploadDirectory = __DIR__ . '/../uploads/pengumuman/';

    foreach ($filenames as $filename) {
        $path = $uploadDirectory . basename($filename);

        if (is_file($path)) {
            unlink($path);
        }
    }
}
