<?php
class BandwidthHero {
    const DEFAULT_QUALITY = 40;
    private $option;
    
    public function __construct() {
        $this->option = new stdClass(); 
    }

    public function proxy() {
      $this->simpleValidate();
      $this->getHeader();
      $this->getQuery();
      $this->getTmpFolderEach();
      $image = self::ImageDown($this->option->query['url'], $this->option->headers);
      file_put_contents($this->option->tmp->original, $image);
      $final_path = self::convertImage($this->option->tmp->original, $this->option->tmp->convert, $this->option->query['webp'], $this->option->query['quality']);
      $this->CleanUp();
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
      if (!isset($_GET['url'])) {
        header("HTTP/1.0 404 Not Found");
        echo json_encode(['success' => false, 'message' => 'No Url Provided']);
        exit;
      }
    }
     
    private function CleanUp() {
        register_shutdown_function(function () use ($this) {
            if (file_exists($this->option->tmp->convert)) {
                unlink($this->option->tmp->convert);
            }
            if (file_exists($this->option->tmp->original)) {
                unlink($this->option->tmp->original);
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
      $this->option->tmp->folder = sys_get_temp_dir();
      $this->option->tmp->original = $this->option->tmp->folder . DIRECTORY_SEPARATOR . uniqid().'.' . $this->option->query['original_ext'];
      $this->option->tmp->convert = $this->option->tmp->folder . DIRECTORY_SEPARATOR . uniqid().'.' . $this->option->query['convert_ext'];
    }
    
    private static function ImageDown($url, $headers = '') 
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
    private static function convertImage($sourcePath, $destinationPath, $webp, $quality = 80)
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

?>