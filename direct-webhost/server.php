<?php
class BandwidthHero {
    const DEFAULT_QUALITY = 40;
    private $option;
    
    public function __construct() {
        $this->option = new stdClass(); 
        $this->option->config = require_once('config.php');
    }

    public function proxy() {
      $this->simpleValidate();
      $this->getHeader();
      $this->getQuery();
      $this->getTmpFolderEach();
      $image = self::ImageDown($this->option->query['url'], $this->option->headers);
      if (!$image || empty($image)) $image = file_get_contents($this->option->query['url']);
      if (!$image || empty($image)) {
        echo '{"success": false, "message": "Image Can\'t Be Downloaded"}';
        exit;
      }
      file_put_contents($this->option->tmp->original, $image);
      $final_path = self::convertImage($this->option->tmp->original, $this->option->tmp->convert, $this->option->query['webp'], $this->option->query['grayscale'], $this->option->query['quality']);
      $this->PromiseCleanUp();
      if ($final_path === $this->option->tmp->original) {
        $ext = $this->option->query['original_ext'];
      } elseif ($final_path === $this->option->tmp->convert) {
        $ext = $this->option->query['convert_ext'];
      } else {
        $ext = $this->option->query['convert_ext'];
      }
      header('Content-Type: image/'.$ext);
      readfile($final_path);
      exit;
    }
    
    private function simpleValidate() {
      if ( !isset($_GET['url']) ) {
        echo 'bandwidth-hero-proxy';
//         header("HTTP/1.0 404 Not Found");
//         echo json_encode(['success' => false, 'message' => 'No Url Provided']);
        exit;
      }
      if ( $this->option->config['auth'] && isset($_GET['token']) ) {
        if ( $this->option->config['token'] === isset($_GET['token']) ) {
          return;
        } else {
          echo 'bandwidth-hero-proxy';
          exit;
        }
      } elseif ( $this->option->config['auth'] && !isset($_GET['token']) ) {
        echo 'bandwidth-hero-proxy';
        exit;
      }
      return;
    }
    
    private function getClientIp() {
        $ipAddress = '';
    
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ipArray = explode(',', $ipAddress);
            $ipAddress = trim($ipArray[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }
    
        return $ipAddress;
    }
     
    private function PromiseCleanUp() {
        $original = $this->option->tmp->original;
        $convert = $this->option->tmp->convert;
        register_shutdown_function(function () use ($original, $convert) {
            if (file_exists($convert)) {
                unlink($convert);
            }
            if (file_exists($original)) {
                unlink($original);
            }
        });
    }
     
    private function getHeader() {
      $this->option->all_headers = getallheaders();
      $this->option->headers = [];
      $this->option->headers['user-agent'] = $this->option->all_headers['user-agent'] ?? '';
      $this->option->headers['cookie'] = $this->option->all_headers['cookie'] ?? '';
      $this->option->headers['dnt'] = $this->option->all_headers['dnt'] ?? '';
      $this->option->headers['referer'] = $this->option->all_headers['referer'] ?? '';
      if ($this->option->config->forward_ip) {
        $ip = getClientIp;
        $this->option->headers['X-Forwarded-For'] = $ip;
      }
    }
    
    private function getQuery() {
      $this->option->query = [];
      $this->option->query['url'] = preg_replace('/http:\/\/1\.1\.\d\.\d\/bmi\/(https?:\/\/)?/i', 'http://', $_GET['url']);
      $this->option->query['webp'] = !$_GET['jpeg'];
      $this->option->query['grayscale'] = $_GET['bw'] !== 0;
      $this->option->query['quality'] = $_GET['l'] ?: self::DEFAULT_QUALITY;
      $path_parts = pathinfo($this->option->query['url']);
      $this->option->query['original_ext'] = $path_parts['extension'];
      $this->option->query['convert_ext'] = ($this->option->query['webp'] ? 'webp' : 'jpg');
    }
    
    private function getTmpFolderEach() {
      $this->option->tmp = new stdClass(); 
      //$this->option->tmp->folder = sys_get_temp_dir();
      $this->option->tmp->folder = __DIR__.'/tmp';
      if (!file_exists($this->option->tmp->folder)) {
        mkdir($this->option->tmp->folder);
      }
      $this->option->tmp->original = $this->option->tmp->folder . DIRECTORY_SEPARATOR . uniqid().'.' . $this->option->query['original_ext'];
      $this->option->tmp->convert = $this->option->tmp->folder . DIRECTORY_SEPARATOR . uniqid().'.' . $this->option->query['convert_ext'];
    }
    
    private static function ImageDown($url, $headers = []) 
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
    private static function convertImage($sourcePath, $destinationPath, $webp, $grayscale, $quality = 80)
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
            if ($grayscale) {
                imagefilter($source, IMG_FILTER_GRAYSCALE);
            }
            
            if ($webp) {
                $result = imagewebp($source, $destinationPath, $quality);
            } else {
//                 if ($imageInfo['mime'] === 'image/png') {
//                     $result = imagepng($source, $destinationPath, round($quality / 10)); 
//                 } else {
                    $result = imagejpeg($source, $destinationPath, $quality);
//                 }
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

?>