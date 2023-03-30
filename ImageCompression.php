<?php
/**
 *
 * @author Milton Paiva <miltonpaiva@opovodigital.com | miltonpaiva268@gmail.com>
 * COMPRIME UMA IMAGEM COM BASE NA URL
 */

class ImageCompression{

    public $file;
    public $defalt_compressed_dir;
    public $defalt_img_lifetime; // 2 seconds | 30 minutes | 1 hour | 2 hours | 1 day | 2 days

    function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');

        $this->defalt_compressed_dir = __DIR__ . "/compressed_imgs/";
        $this->defalt_img_lifetime   = '6 hours';
    }

    /**
     * FAZ AS VALIDAÇÕES E A COMPRESSÃO DA IMAGEM INFORMADA PELA URL
     * @param  string      $origin_img_url
     * @param  int|integer $percentage_compres_quality
     * @param  string      $additional_dir
     * @author Milton Paiva
     * @return bool
     */
    public function compress(string $origin_img_url, int $percentage_compres_quality = 50, string $additional_dir = ''): bool
    {
        $img_info = pathinfo($origin_img_url);

        $is_gif = (strrpos($origin_img_url, '.gif') !== false);

        $no_compres =
        (
            !isset($img_info['extension']) ||
            !isset($img_info['dirname'])   ||
            $is_gif
        );

        // VALIDANDO SE A URL É UMA IMAGEM E SE NÃO É UM GIF
        if ($no_compres) { throw new Exception('url incompleta ou tipo de imagem não suportado'); }

        // INCREMENTANDO O DIRETORIO ADICIONAL, SE HOUVER
        $this->defalt_compressed_dir .= $additional_dir;

        // TRATANDO O NOME DO ARQUIVO
        $this->file = strtok($img_info['basename'], '?');

        $destination = "{$this->defalt_compressed_dir}{$this->file}";

        // VERIFICANDO SE O ARQUIVO JA EXISTE
        $compressed_file_exist = file_exists($destination);
        if ($compressed_file_exist) { $no_compres = $this->check_lifetime($destination); }

        // VALIDANDO SE A IMG AINDA ESTA NO TEMPO DE VIDA
        if ($no_compres) { throw new Exception('imagem ainda dentro do tempo de vida'); }

        // VALIDANDO A EXISTENCIA OU CRIANDO O DIRETORIO DAS IMAGEMS COMPRIMIDAS
        $dir_ok = self::checkCreateDir($this->defalt_compressed_dir);
        if (!$dir_ok) { throw new Exception("diretorio [{$this->defalt_compressed_dir}] inexistente e/ou não foi possivel ser criado"); }

        // REALIZNADO A COMPRESSÃO
        $compressed = self::compressImage( $origin_img_url, $destination, $percentage_compres_quality );

        return $compressed;
    }

    /**
     * VERIFICA A CRIAÇÃO/ATUALIZAÇÃO DO ARQUIVO COMPRIMIDO PARA SABER SE O MESMO JA DEVE SER ATUALIZADO
     * @param  string $file
     * @author Milton Paiva
     * @return bool
     */
    public function check_lifetime(string $file): bool
    {
        $now_time = strtotime("now");

        $created_at_time = filectime($file);
        $updated_at_time = filemtime($file);

        $updated_at_date = date("Y-m-d H:i:s", $updated_at_time);

        $valid_time = strtotime("{$updated_at_date} + {$this->defalt_img_lifetime}");

        $is_valid_time = $now_time <= $valid_time;

        return $is_valid_time;
    }

    /**
     * VERIFICA A EXISTENCIA DO DIRETORIO DOS ARQUIVOS E CRIA O MESMO SE NÃO EXISTIR
     * @param  string $file_path
     * @return bool
     */
    public static function checkCreateDir(string $file_path): bool
    {
        try {
            $dir_created = false;
            //check existence of the directory
            $dir_exist = is_dir($file_path);
            if (!$dir_exist) {
                $dir_created = mkdir($file_path, 0775, true);
            }

            return ($dir_created || $dir_exist);

        } catch (Exception $e) {
            throw new Exception("diretorio [{$file_path}] inexistente e/ou não foi possivel ser criado - " . $e->getMessage());
        }
    }

    /**
     * FAZ A COMPRESSÃO E CONVERSÃO DA IMAGEM CONFORME A URL E O TIPO INFORMADO
     * @param  string $origin
     * @param  string $destination
     * @param  int    $quality
     * @param  string $type
     * @return bool
     */
    public static function compressImage(string $origin, string $destination, int $quality, string $type = 'webp'): bool
    {

        // PEGANDO OS DADOS DA IMAGEM
        $info = getimagesize($origin);

        try {

            if ($info['mime'] == 'image/jpeg'){ $image = imagecreatefromjpeg($origin); }
            if ($info['mime'] == 'image/gif'){  $image = imagecreatefromgif($origin);  }
            if ($info['mime'] == 'image/png'){  $image = imagecreatefrompng($origin);  }

        } catch (Exception $e) {
            throw new Exception("(Exception) a imagem virtual do tipo [{$info['mime']}] não pode ser criada - " . $e->getMessage());
        } catch (Error $e) {
            throw new Exception("(Error) a imagem virtual do tipo [{$info['mime']}] não pode ser criada - " . $e->getMessage());
        }

        if ($type == 'webp' && in_array($info['mime'], ['image/png', 'image/gif'])) {
            @$reduced = imagewebp($image, $destination, $quality);
        }else{
            @$reduced = imagejpeg($image, $destination, $quality);
        }

        // Free up memory
        if (@$image) { imagedestroy($image); }

        if (!$reduced){
            $args =
                [
                    'message'  => "a imagem de path [{$destination}] ({$type}) não pode ser comprimida",
                    'type'     => 'error',
                    'origin'   => $origin,
                ];
        }

      return $reduced;
    }

    public static function response(bool $success = true, array $data = [], string $message = ''): void
    {
        $default_message = $success? 'sucesso' : 'erro' ;
        empty($message)? $message = $default_message : null ;

        $response =
        [
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    }

    public function compressAction(): void
    {
        try {
            $result = $this->compress($_REQUEST['origin_img_url']);
            self::response($result, [$_REQUEST['origin_img_url']], "compressão de imagem");
        } catch (Exception $e) {
            self::response(false, [$_REQUEST['origin_img_url']], "não foi possivel comprimir a imagem: " . $e->getMessage());
        }
    }
}


$c = new ImageCompression();
$c->compressAction();
