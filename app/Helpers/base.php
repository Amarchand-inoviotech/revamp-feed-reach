<?php

use App\Jobs\SendEmailJob;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

if (!function_exists('successResponse')) {
    /**
     * @param string $message
     * @param array $data
     * @param int $code
     * @param bool $paginate
     * @return \Illuminate\Http\Response
     */
    function successResponse($data = [], $message = '', $code = 200, $paginate = FALSE)
    {
        if ($paginate == TRUE && is_object($data)) {
            $data =  paginate($data);
        }

        $response = responseStructure($message, $data, $code, true);

        return response()->json($response, $code);
    }
}

if (!function_exists('errorResponse')) {
    /**
     * @param string $message
     * @param int $code
     * @param array $errors
     * @return \Illuminate\Http\Response
     */
    function errorResponse($message, $code = 400, $errors = [])
    {
        $code = $code == 0 ? 400 : $code;
        $isSqlQuery = Str::startsWith($message, "SQLSTATE");
        // cutting full sql error message
        if (is_string($message) && $isSqlQuery && !app()->isProduction()) {
            $message = substr($message, 0, strpos($message,  "(Connection: mysql, SQL:"));
        }

        $response = responseStructure($message, $errors, $code, false);

        return response()->json($response, $code);
    }
}

if (!function_exists('paginate')) {
    /**
     * @param object $data
     * @return array
     */
    function paginate($data = null)
    {

        $paginationArray = NULL;
        if ($data != NULL) {
            $paginationArray = array('list' => $data->items(), 'pagination' => []);
            $paginationArray['pagination']['total'] = $data->total();
            $paginationArray['pagination']['current'] = $data->currentPage();
            $paginationArray['pagination']['first'] = 1;
            $paginationArray['pagination']['last'] = $data->lastPage();
            if ($data->hasMorePages()) {
                if ($data->currentPage() == 1) {
                    $paginationArray['pagination']['previous'] = 0;
                } else {
                    $paginationArray['pagination']['previous'] = $data->currentPage() - 1;
                }
                $paginationArray['pagination']['next'] = $data->currentPage() + 1;
            } else {
                $paginationArray['pagination']['previous'] = $data->currentPage() - 1;
                $paginationArray['pagination']['next'] =  $data->lastPage();
            }
            $paginationArray['pagination']['from'] = $data->firstItem();
            $paginationArray['pagination']['to'] = $data->lastItem();
        }
        return $paginationArray;
    }
}

/**
 * getSuccessCode
 *
 * @return int
 */
if (!function_exists('getSuccessCode')) {
    function getSuccessCode()
    {
        $trace = debug_backtrace();
        $caller = $trace[1]['function'] == 'successResponse' ? $trace[2]['function'] : $trace[1]['function'];

        return match ($caller) {
            'store' => 201,
            'update', 'show' => 200,
            'destroy' => 204,
            default => 200,
        };
    }
}



function uploadFile($file, $storagePath = 'uploads', $filename = null, $oldPath = null)
{
    if (file_exists($path = public_path('storage/' . $oldPath))) {
        unlink($path);
    }
    $filename = !is_null($filename) ? $filename : Str::random(10);
    $filename .= "." . $file->getClientOriginalExtension();
    $file = $file->storeAs($storagePath, $filename, 'public');

    return $file;
}
function responseStructure($message, $data, int $code, bool $status)
{
    return [
        'success' => $status,
        'status_code' => $code,
        'message' => $message,
        'data' => $data
    ];
}

function getPublicStoragePath(string $path = "")
{
    $path = ltrim($path, '/');
    $storage = $path ? "storage/{$path}" : "storage";
    return public_path($storage);
}
function getStoragePath(string $path = "")
{
    $path = ltrim($path, '/');
    $path = $path ? "app/public/{$path}" : "app/public";
    return storage_path($path);
}

function getPublicPath(string $path = "")
{
    $path = ltrim($path, '/');
    $path = $path ? "uploads/{$path}" : "uploads";
    return public_path($path);
}

function sendEmail(string|array $to, string $subject, string|Mailable $view, array|Model $data = [])
{
    SendEmailJob::dispatch(
        $to,
        $subject,
        $view,
        $data
    );
}
