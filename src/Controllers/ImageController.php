<?php

namespace Rapidez\ImageResizer\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Rapidez\ImageResizer\Exceptions\UnreachableUrl;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class ImageController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, string $size, string $file, string $webp = '')
    {
        abort_unless(in_array($size, config('imageresizer.sizes')), 400, 'The requested size is not whitelisted.');

        foreach (config('imageresizer.external') as $placeholder => $url) {
            if (Str::startsWith($file, $placeholder)) {
                $file = str_replace($placeholder, '', $file);
                $placeholder = $placeholder;
                break;
            }
        }

        $resizedPath = 'resizes/'.$size.'/'.$file.$webp;
        if (!Storage::exists('public/'.$resizedPath)) {
            $remoteFile = isset($placeholder) ? config('imageresizer.external.'.$placeholder).$file : config('rapidez.media_url').'/'.$file;
            if (!$stream = @fopen($remoteFile, 'r')) {
                throw UnreachableUrl::create($remoteFile);
            }

            $temporaryFile = tempnam(sys_get_temp_dir(), 'rapidez');
            file_put_contents($temporaryFile, $stream);

            $image = Image::load($temporaryFile)->optimize();
            @list($width, $height) = explode('x', $size);

            if ($height) {
                $image->fit($request->has('crop') ? MANIPULATIONS::FIT_CROP : MANIPULATIONS::FIT_CONTAIN, $width, $height);
            } else {
                $image->width($width);
            }

            if (!is_dir(storage_path('app/public/'.pathinfo($resizedPath, PATHINFO_DIRNAME)))) {
                mkdir(storage_path('app/public/'.pathinfo($resizedPath, PATHINFO_DIRNAME)), 0755, true);
            }

            $image->save(storage_path('app/public/'.$resizedPath));
        }

        return response()->file(storage_path('app/public/'.$resizedPath));
    }
}
