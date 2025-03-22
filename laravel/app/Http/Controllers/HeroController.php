<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HeroController extends Controller
{
    const DEFAULT_QUALITY = 40;
    public function Get( Request $request ) {
        $url_image = $request->query('url');
        if(!$url_image || empty($url_image)) {
          return response()->json([
              'success' => false,
              'message' => 'No Url Image Provided'
          ]); 
        }
        $url_image = preg_replace('/http:\/\/1\.1\.\d\.\d\/bmi\/(https?:\/\/)?/i', 'http://', $url_image);
        $params = [
            'url' => $url_image,
            'webp' => !$request->query('jpeg') && !$request->query('jpg'), 
            'grayscale' => $request->query('bw') != 0, 
            'quality' => (int) $request->query('l') ?: self::DEFAULT_QUALITY,
        ];
        $headers = [
            'user-agent' => $request->header('user-agent'),
            'cookie' => $request->header('cookie'), 
            'dnt' => $request->header('dnt'), 
            'referer' => $request->header('referer')
        ];
        
        $image = self::ImageDown($params['url'], $headers);
        $tmp_dir = sys_get_temp_dir();
        $path_parts = pathinfo($params['url']);
        $extension = $path_parts['extension'];
        $extension_convert = ($params['webp'] ? 'webp' : 'jpg');
        $image_path = $tmp_dir . DIRECTORY_SEPARATOR . uniqid().'.' . $extension;
        file_put_contents($image_path, $image);
        $image_convert_path = $tmp_dir . DIRECTORY_SEPARATOR . uniqid().'.' . $extension_convert;
        $final_path = self::convertImage($image_path, $image_convert_path, $params['webp'], $params['quality']);
        $imageStream = fopen($final_path, 'rb'); 
        register_shutdown_function(function () use ($image_convert_path, $image_path) {
            if (file_exists($image_convert_path)) {
                unlink($image_convert_path);
            }
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        });
        return response()->stream(function() use ($imageStream) {
            fpassthru($imageStream); 
            fclose($imageStream); 
        }, 200, [
            'Content-Type' => 'image/'.$extension_convert, 
        ]);
    }
    public static function ImageDown($url, $headers = '') 
    {
        $headers['Accept'] = 'image/avif,image/webp,*/*';
        $headers['Accept-Language'] = 'en-US,en;q=0.5';
        $headers['Accept-Encoding'] = 'gzip, deflate, br';
        $headers['Connection'] = 'keep-alive';
        $headers['Sec-Fetch-Dest'] = 'image';
        $headers['Sec-Fetch-Mode'] = 'no-cors';
        $headers['Sec-Fetch-Site'] = 'cross-site';
    
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, ''); 
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
    
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) {
          curl_close($ch);
            return false;
        } else {
          curl_close($ch);
            return $response;
        }
    
        
    }
    
    public static function convertImage($sourcePath, $destinationPath, $webp, $quality = 80)
    {
        try {
            $imageInfo = getimagesize($sourcePath);
            if ($imageInfo === false) {
                throw new Exception('Gagal mendapatkan informasi gambar.');
            }

            switch ($imageInfo['mime']) {
                case 'image/jpeg':
                    $source = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $source = imagecreatefrompng($sourcePath);
                    imagealphablending($source, false);
                    imagesavealpha($source, true);
                    break;
                case 'image/gif':
                    $source = imagecreatefromgif($sourcePath);
                    break;
                case 'image/bmp':
                    $source = imagecreatefrombmp($sourcePath);
                    break;
                case 'image/webp':
                    $source = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    throw new Exception('Tipe gambar tidak didukung: ' . $imageInfo['mime']);
            }

            if (imageistruecolor($source) === false) {
                $truecolorImage = imagecreatetruecolor(imagesx($source), imagesy($source));

                if ($imageInfo['mime'] === 'image/png' || $imageInfo['mime'] === 'image/gif') {
                    imagealphablending($truecolorImage, false);
                    imagesavealpha($truecolorImage, true);
                    $transparent = imagecolorallocatealpha($truecolorImage, 0, 0, 0, 127);
                    imagefilledrectangle($truecolorImage, 0, 0, imagesx($truecolorImage), imagesy($truecolorImage), $transparent);
                }
                imagecopy($truecolorImage, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
                imagedestroy($source);
                $source = $truecolorImage;
            }
            if ($webp) {
                $result = imagewebp($source, $destinationPath, $quality);
            } else {
                if ($imageInfo['mime'] === 'image/png') {
                    $result = imagepng($source, $destinationPath, round($quality / 10)); 
                } else {
                    $result = imagejpeg($source, $destinationPath, $quality);
                }
            }

            if ($result === false) {
                throw new Exception('Gagal mengkonversi gambar.');
            }

            imagedestroy($source);

            return $destinationPath;

        } catch (Exception $e) {
            error_log('Error: ' . $e->getMessage());
            return $sourcePath;
        }
    }
}
