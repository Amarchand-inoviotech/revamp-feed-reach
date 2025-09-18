<?php

namespace App\Helpers;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;

class FileUploader
{
    public static function uploadFile(
        $file,
        Model|null $parentModel,
        $uploadPath = null,
        $filename = null,
        $oldAttachment = null,
        $comment = null,
        $size = null
    ) {
        // Delete old file if specified
        if ($oldAttachment) {
            self::deleteFile($oldAttachment);
        }

        // Set default upload path
        $uploadPath = $uploadPath ? 'uploads/' . $uploadPath : 'uploads/';

        // Check for existing file by MD5 hash
        $md5 = md5_file($file->getPathname());
        if ($attachment = Attachment::where('md5', $md5)->first()) {
            return $attachment;
        }

        // Generate filename if not provided
        $filename = $filename ?: Str::random(10);
        $originalExtension = strtolower($file->getClientOriginalExtension());

        // Create storage directory if it doesn't exist
        $storageFullPath = storage_path('app/public/' . $uploadPath);
        if (!File::exists($storageFullPath)) {
            File::makeDirectory($storageFullPath, 0755, true);
        }

        // Process image files
        if (self::isImageFile($originalExtension)) {
            try {
                $result = self::convertImageToWebp(
                    $file,
                    $storageFullPath,
                    $filename,
                    $size
                );

                if ($result) {
                    list($serverPath, $imageWidth, $imageHeight, $imageOrientation) = $result;
                }
            } catch (\Exception $e) {
                // Fall through to non-image handling if conversion fails
            }
        }

        // For non-image files or if WebP conversion failed
        if (empty($serverPath)) {
            $originalFilename = $filename . '.' . $originalExtension;
            $file->storeAs($uploadPath, $originalFilename, 'public');
            $serverPath = $uploadPath . '/' . $originalFilename;
            $imageWidth = $imageHeight = $imageOrientation = null;
        }

        // Create and save attachment record
        return self::createAttachmentRecord([
            'md5' => $md5,
            'title' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'comment' => $comment,
            'parentModel' => $parentModel,
            'path' => $serverPath,
            'width' => $imageWidth,
            'height' => $imageHeight,
            'orientation' => $imageOrientation
        ]);
    }

    /**
     * Convert image to WebP format with optional resizing
     */
    private static function convertImageToWebp(
        string|UploadedFile $source,
        string $destinationDir,
        string $filename,
        ?int $targetSize = null,
        int $quality = 80
    ): ?array {
        $imagine = new Imagine();
        $sourcePath = $source instanceof UploadedFile ? $source->getPathname() : $source;


        $image = $imagine->open($source);

        // Fix orientation for JPEGs
        if (in_array(strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg'])) {
            $image = self::fixImageOrientation($sourcePath, $image);
        }

        // Get original dimensions
        $originalSize = $image->getSize();
        $width = $originalSize->getWidth();
        $height = $originalSize->getHeight();

        // Get orientation from EXIF
        $exif = @exif_read_data($sourcePath);
        $orientation = ($exif && isset($exif['Orientation'])) ? $exif['Orientation'] : null;

        // Resize if target size specified and image is larger than target
        if ($targetSize && ($width > $targetSize || $height > $targetSize)) {
            if ($width > $height) {
                $newWidth = $targetSize;
                $newHeight = (int) ($height * $targetSize / $width);
            } else {
                $newHeight = $targetSize;
                $newWidth = (int) ($width * $targetSize / $height);
            }

            $image->resize(new Box($newWidth, $newHeight));
            $width = $newWidth;
            $height = $newHeight;
        }

        // Save as WebP with specified quality
        $webpFilename = $filename . '.webp';
        $webpPath = $destinationDir . '/' . $webpFilename;
        $image->save($webpPath, [
            'format' => 'webp',
            'quality' => $quality
        ]);

        // Return relative path and image metadata
        $relativePath = str_replace(storage_path('app/public/'), '', $destinationDir);
        return [
            $relativePath . '/' . $webpFilename,
            $width,
            $height,
            $orientation
        ];
    }



    /**
     * Check if file is an image by extension
     */
    private static function isImageFile(string $extension): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
    }

    /**
     * Fix image orientation using EXIF data
     */
    private static function fixImageOrientation($filePath, ImageInterface $image): ImageInterface
    {
        $exif = @exif_read_data($filePath);
        if ($exif && isset($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $image->rotate(180);
                    break;
                case 6:
                    $image->rotate(90);
                    break;
                case 8:
                    $image->rotate(-90);
                    break;
            }
        }
        return $image;
    }

    /**
     * Create attachment record in database
     */
    private static function createAttachmentRecord(array $data): Attachment
    {
        $user = Auth::user();

        $attachment = new Attachment();
        $attachment->md5 = $data['md5'];
        $attachment->title = $data['title'];
        $attachment->type = $data['type'];
        $attachment->comment = $data['comment'] ?? null;
        $attachment->model_id = $data['parentModel']?->id ?? null;
        $attachment->model_type = $data['parentModel']?->getMorphClass() ?? null;
        $attachment->path = $data['path'];
        $attachment->height = $data['height'];
        $attachment->width = $data['width'];
        $attachment->orientation = $data['orientation'];
        $attachment->author_id = $user?->id;
        $attachment->author_type = $user ? get_class($user) : null;
        $attachment->save();

        return $attachment;
    }

    /**
     * Delete file and its attachment record
     */
    public static function deleteFile($path): void
    {
        if (is_numeric($path)) {
            $attachment = Attachment::find($path);
            if ($attachment) {
                File::delete(storage_path('app/public/' . $attachment->path));
                $attachment->delete();
            }
            return;
        }

        if (!empty($path)) {
            $fullPath = storage_path('app/public/' . $path);
            $attachment = Attachment::where('path', $path)->first();

            if ($attachment) {
                if (File::exists($fullPath)) {
                    File::delete($fullPath);
                }
                $attachment->delete();
            } elseif (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }
    }
}
