<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class Helper{
    /**
     * Generate a unique username from the given name
     *
     * @param string $name
     * @return string
     */
    public static function generateSlug(string $name,$table, $column="name"): string
    {
        // Clean the name: remove special characters, convert to lowercase, replace spaces with underscores
        $baseUsername = Str::slug($name, '_');

        // Remove any non-alphanumeric characters except underscores
        $baseUsername = preg_replace('/[^a-z0-9_]/', '', $baseUsername);

        // Ensure username is not empty and has minimum length
        if (empty($baseUsername) || strlen($baseUsername) < 3) {
            $baseUsername = Str::singular($table) . Str::random(6);
        }

        // If not available, append numbers until we find a unique one
        $counter = 0;
        do {

            $username = $counter == 0?$baseUsername:$baseUsername. $counter;
            $counter++;
        } while (DB::table($table)->where($column, $username)->exists());

        return $username;
    }
}












